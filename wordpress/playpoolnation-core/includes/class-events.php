<?php
/**
 * Events: tournaments and other pool events posted by venues and the community.
 *
 * Events are My Listing listings of the `tournament` type (shown to visitors as
 * "Events"), linked to a venue through My Listing's relations table and dated in
 * its events table, so the theme's own dashboard can edit them afterwards.
 *
 * Who can publish: administrators and editors, and the owner of a claimed venue
 * posting for that venue. Everyone else's events are saved as pending and wait in
 * the review queue. Posting uses the same anti-spam guard as the other forms.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Format;

defined( 'ABSPATH' ) || exit;

final class Events {

	public const PAGE = 'post-an-event';
	public const RELATIONS = [ 'organizer' => 'I am organizing it', 'staff' => 'I work at the venue', 'player' => 'I am a player', 'other' => 'Other' ];
	public const TABLE_SIZES = [ '7-foot' => "7' tables", '8-foot' => "8' tables", '9-foot' => "9' tables", 'mixed' => 'Mixed sizes' ];
	private const MAX_DAYS_AHEAD = 730;

	public static function boot(): void {
		add_shortcode( 'ppn_post_event', [ __CLASS__, 'form' ] );
		add_action( 'admin_post_nopriv_ppn_post_event', [ __CLASS__, 'handle' ] );
		add_action( 'admin_post_ppn_post_event', [ __CLASS__, 'handle' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( Play::CRON, [ __CLASS__, 'daily' ], 20 );
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'account_menu' ], 60 );
		add_filter( 'woocommerce_get_endpoint_url', [ __CLASS__, 'account_menu_url' ], 10, 2 );
	}

	public static function page_url( int $venue_id = 0 ): string {
		$page = get_page_by_path( self::PAGE );
		$url = $page ? get_permalink( $page ) : home_url( '/' . self::PAGE . '/' );
		return $venue_id ? add_query_arg( 'venue', $venue_id, $url ) : (string) $url;
	}

	public static function event_type_label( int $event_id ): string {
		$terms = wp_get_object_terms( $event_id, Pool_Schema::TAX_EVENT_TYPE, [ 'fields' => 'slugs' ] );
		$slug = is_array( $terms ) && $terms ? $terms[0] : 'tournament';
		return Pool_Schema::EVENT_TYPES[ $slug ] ?? 'Event';
	}

	/* ------------------------------------------------------- permissions */

	/** @return array<int,string> venue ID => label, for claimed venues the user owns */
	public static function owned_venues( int $user_id ): array {
		if ( ! $user_id ) {
			return [];
		}
		$ids = get_posts( [
			'post_type'      => Pool_Schema::POST_TYPE,
			'post_status'    => 'publish',
			'author'         => $user_id,
			'fields'         => 'ids',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [
				[ 'key' => '_case27_listing_type', 'value' => Pool_Schema::VENUE_TYPE ],
				[ 'key' => '_claimed', 'value' => '1' ],
			],
		] );
		$out = [];
		foreach ( $ids as $id ) {
			$out[ (int) $id ] = self::venue_label( (int) $id );
		}
		return $out;
	}

	public static function can_publish( int $user_id, int $venue_id ): bool {
		if ( $user_id && user_can( $user_id, 'edit_others_posts' ) ) {
			return true;
		}
		return $user_id && isset( self::owned_venues( $user_id )[ $venue_id ] );
	}

	public static function venue_label( int $venue_id ): string {
		$v = Venue::get( $venue_id );
		if ( ! $v ) {
			return '';
		}
		return $v->name() . ( $v->city_state() ? ' (' . $v->city_state() . ')' : '' );
	}

	/* ------------------------------------------------------- venue search */

	public static function routes(): void {
		register_rest_route( 'ppn/v1', '/venues', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => [ 'q' => [ 'type' => 'string', 'required' => true ] ],
			'callback'            => static function ( \WP_REST_Request $request ) {
				$res = new \WP_REST_Response( self::search_venues( (string) $request->get_param( 'q' ) ) );
				$res->header( 'Cache-Control', 'public, max-age=300' );
				return $res;
			},
		] );
	}

	/** Published venues whose name matches, for the event form's venue picker. */
	public static function search_venues( string $q, int $limit = 10 ): array {
		global $wpdb;
		$q = trim( sanitize_text_field( $q ) );
		if ( mb_strlen( $q ) < 2 ) {
			return [];
		}
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_case27_listing_type' AND m.meta_value = %s
			 WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_title LIKE %s
			 ORDER BY p.post_title ASC LIMIT %d",
			Pool_Schema::VENUE_TYPE,
			Pool_Schema::POST_TYPE,
			'%' . $wpdb->esc_like( $q ) . '%',
			$limit
		) );
		Venue::prime( array_map( 'intval', $ids ) );
		return array_map( static fn( $id ) => [ 'id' => (int) $id, 'label' => html_entity_decode( self::venue_label( (int) $id ), ENT_QUOTES ) ], $ids );
	}

	/* -------------------------------------------------------------- form */

	private static function message(): string {
		$msg = isset( $_GET['ppn_msg'] ) ? sanitize_key( wp_unslash( $_GET['ppn_msg'] ) ) : '';
		$event = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
		$texts = [
			'published' => [ 'ok', 'Your event is live.' ],
			'pending'   => [ 'ok', 'Thanks. Your event was sent to our team and will appear once it is checked.' ],
			'invalid'   => [ 'error', 'Please fill in the event name, type, venue and date.' ],
			'venue'     => [ 'error', 'Please choose the venue from the list. If it is not listed yet, add the venue first.' ],
			'date'      => [ 'error', 'Please choose a date between today and two years from now, and a start time.' ],
			'until'     => [ 'error', 'For a repeating event, choose the last date it repeats (after the first date).' ],
			'expired'   => [ 'error', 'The form expired. Please try again.' ],
			'limit'     => [ 'error', 'Too many submissions from your connection. Please try again later.' ],
			'spam'      => [ 'error', 'Your submission could not be accepted.' ],
		];
		if ( ! isset( $texts[ $msg ] ) ) {
			return '';
		}
		[ $type, $text ] = $texts[ $msg ];
		$link = ( 'published' === $msg && $event && 'publish' === get_post_status( $event ) ) ? ' <a href="' . esc_url( get_permalink( $event ) ) . '">View your event</a>' : '';
		return '<p class="ppn-notice ppn-notice--' . esc_attr( $type ) . '" role="' . ( 'ok' === $type ? 'status' : 'alert' ) . '">' . esc_html( $text ) . $link . '</p>';
	}

	public static function form(): string {
		$user = get_current_user_id();
		$owned = self::owned_venues( $user );
		$admin = $user && user_can( $user, 'edit_others_posts' );
		$prefill = isset( $_GET['venue'] ) ? absint( $_GET['venue'] ) : 0;
		$prefill = $prefill && Venue::is_venue( $prefill ) && 'publish' === get_post_status( $prefill ) ? $prefill : 0;
		$today = wp_date( 'Y-m-d' );
		$max = wp_date( 'Y-m-d', time() + self::MAX_DAYS_AHEAD * DAY_IN_SECONDS );

		$games = [];
		foreach ( get_terms( [ 'taxonomy' => 'game-type', 'hide_empty' => false, 'orderby' => 'term_order' ] ) as $term ) {
			if ( $term instanceof \WP_Term ) {
				$games[ $term->slug ] = html_entity_decode( $term->name, ENT_QUOTES );
			}
		}
		$repeats = array_map( static fn( $r ) => $r[0], Pool_Schema::EVENT_REPEATS );

		$h = '<div class="ppn-post-event" id="post-event">' . self::message();
		$h .= '<form class="ppn-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ppn-event-form>';
		$h .= '<input type="hidden" name="action" value="ppn_post_event">' . Forms::common_fields();

		$h .= '<fieldset><legend>The event</legend>';
		$h .= '<p><label for="ppn-e-type">Type of event</label>' . Forms::select( 'event_type', 'ppn-e-type', Pool_Schema::EVENT_TYPES, 'Choose one', true, 'tournament' ) . '</p>';
		$h .= '<p><label for="ppn-e-name">Event name</label><input id="ppn-e-name" type="text" name="name" required maxlength="150" placeholder="For example: Friday Night 9-Ball"></p>';

		// Venue: owners pick from their claimed venues; anyone can search all venues.
		$h .= '<div class="ppn-venue-pick">';
		if ( $owned ) {
			$selected = isset( $owned[ $prefill ] ) ? (string) $prefill : (string) array_key_first( $owned );
			$h .= '<p><label for="ppn-e-venue">Venue</label>' . Forms::select( 'venue_id', 'ppn-e-venue', $owned + [ 'other' => 'A different venue…' ], 'Choose your venue', false, $selected ) . '</p>';
		}
		$prefill_label = $prefill && ! isset( $owned[ $prefill ] ) ? html_entity_decode( self::venue_label( $prefill ), ENT_QUOTES ) : '';
		$h .= '<p class="ppn-venue-search"' . ( $owned ? ' data-ppn-other-venue hidden' : '' ) . '><label for="ppn-e-venue-q">' . ( $owned ? 'Other venue' : 'Venue' ) . '</label>'
			. '<input id="ppn-e-venue-q" type="text" name="venue_label" list="ppn-e-venue-list" autocomplete="off" maxlength="200" placeholder="Start typing the venue name" value="' . esc_attr( $prefill_label ) . '"' . ( $owned ? '' : ' required' ) . ' data-ppn-venue-search>'
			. '<datalist id="ppn-e-venue-list"></datalist>'
			. '<input type="hidden" name="venue_pick" value="' . esc_attr( $prefill_label ? (string) $prefill : '' ) . '" data-ppn-venue-id>'
			. '<span class="ppn-fineprint">Not listed yet? <a href="' . esc_url( home_url( '/add-a-venue/' ) ) . '">Add the venue first</a>.</span></p>';
		$h .= '</div>';

		$h .= '<div class="ppn-form-row">'
			. '<p><label for="ppn-e-date">Date</label><input id="ppn-e-date" type="date" name="date" required min="' . esc_attr( $today ) . '" max="' . esc_attr( $max ) . '"></p>'
			. '<p><label for="ppn-e-start">Start time</label><input id="ppn-e-start" type="time" name="start_time" required></p>'
			. '<p><label for="ppn-e-end">End time <span class="ppn-optional">(optional)</span></label><input id="ppn-e-end" type="time" name="end_time"></p>'
			. '</div>';
		$h .= '<div class="ppn-form-row">'
			. '<p><label for="ppn-e-repeat">Repeats</label>' . Forms::select( 'repeat', 'ppn-e-repeat', $repeats, '', false, 'none' ) . '</p>'
			. '<p data-ppn-until hidden><label for="ppn-e-until">Last date</label><input id="ppn-e-until" type="date" name="until" min="' . esc_attr( $today ) . '" max="' . esc_attr( $max ) . '"></p>'
			. '</div>';
		$h .= '</fieldset>';

		$h .= '<fieldset><legend>Details <span class="ppn-optional">(fill in what applies)</span></legend>';
		if ( $games ) {
			$h .= '<div class="ppn-checks" role="group" aria-labelledby="ppn-e-games-l"><p id="ppn-e-games-l" class="ppn-label">Game</p>';
			foreach ( $games as $slug => $label ) {
				$h .= '<label class="ppn-check"><input type="checkbox" name="games[]" value="' . esc_attr( $slug ) . '"> ' . esc_html( $label ) . '</label>';
			}
			$h .= '</div>';
		}
		$h .= '<div class="ppn-form-row">'
			. '<p><label for="ppn-e-size">Table size</label>' . Forms::select( 'table_size', 'ppn-e-size', self::TABLE_SIZES, 'Not specified', false ) . '</p>'
			. '<p><label for="ppn-e-fee">Entry fee (USD)</label><input id="ppn-e-fee" type="number" name="entry_fee" min="0" max="100000" step="0.01" inputmode="decimal"></p>'
			. '<p><label for="ppn-e-added">Added money (USD)</label><input id="ppn-e-added" type="number" name="added_money" min="0" max="1000000" step="0.01" inputmode="decimal"></p>'
			. '</div>';
		$h .= '<p><label for="ppn-e-elig">Who can play</label><input id="ppn-e-elig" type="text" name="eligibility" maxlength="200" placeholder="For example: Open, or APA 5 and under"></p>';
		$h .= '<p><label for="ppn-e-reg">How to register</label><textarea id="ppn-e-reg" name="registration" rows="2" maxlength="1000" placeholder="For example: Sign up at the bar by 6:30 PM"></textarea></p>';
		$h .= '<p><label for="ppn-e-link">Registration or info link</label><input id="ppn-e-link" type="url" name="link" maxlength="300" placeholder="https://"></p>';
		$h .= '<p><label for="ppn-e-desc">Description</label><textarea id="ppn-e-desc" name="description" rows="4" maxlength="3000" placeholder="Format, payouts, rules, anything players should know"></textarea></p>';
		$h .= '</fieldset>';

		if ( ! $owned && ! $admin ) {
			$h .= '<fieldset><legend>About you</legend><div class="ppn-form-row">';
			$h .= '<p><label for="ppn-e-rel">Your connection to the event</label>' . Forms::select( 'relation', 'ppn-e-rel', self::RELATIONS, 'Choose one', true ) . '</p>';
			$h .= '<p><label for="ppn-e-email">Email <span class="ppn-optional">(optional, only used if we have a question)</span></label><input id="ppn-e-email" type="email" name="email" maxlength="200" autocomplete="email"></p>';
			$h .= '</div></fieldset>';
		}

		$h .= '<p><button type="submit" class="ppn-button">' . ( $owned || $admin ? 'Post event' : 'Submit event' ) . '</button></p>';
		$h .= '<p class="ppn-fineprint">' . ( $owned
			? 'Events for your claimed venues go live right away. Events for other venues are checked first.'
			: 'Every event is checked before it appears. Venue owners who claim their listing can post events instantly.' ) . '</p>';
		return $h . '</form></div>';
	}

	/* ------------------------------------------------------------ submit */

	public static function handle(): void {
		$return = self::page_url();
		$error = Forms::guard( 'event' );
		if ( $error ) {
			Forms::back( $return, $error, 'post-event' );
		}
		$in = static fn( string $key, int $max = 200 ) => isset( $_POST[ $key ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ), 0, $max ) : '';
		$text = static fn( string $key, int $max ) => isset( $_POST[ $key ] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ), 0, $max ) : '';
		$money = static function ( string $key ): string {
			$raw = isset( $_POST[ $key ] ) ? trim( (string) wp_unslash( $_POST[ $key ] ) ) : '';
			return ( '' !== $raw && is_numeric( $raw ) && (float) $raw >= 0 ) ? (string) round( (float) $raw, 2 ) : '';
		};
		$games = isset( $_POST['games'] ) && is_array( $_POST['games'] ) ? array_map( 'sanitize_title', wp_unslash( $_POST['games'] ) ) : [];

		$data = [
			'name'        => $in( 'name', 150 ),
			'event_type'  => sanitize_key( $in( 'event_type', 40 ) ),
			'venue_id'    => self::resolve_venue(),
			'date'        => $in( 'date', 10 ),
			'start_time'  => $in( 'start_time', 5 ),
			'end_time'    => $in( 'end_time', 5 ),
			'repeat'      => sanitize_key( $in( 'repeat', 20 ) ) ?: 'none',
			'until'       => $in( 'until', 10 ),
			'games'       => $games,
			'table_size'  => sanitize_key( $in( 'table_size', 20 ) ),
			'entry_fee'   => $money( 'entry_fee' ),
			'added_money' => $money( 'added_money' ),
			'eligibility' => $in( 'eligibility', 200 ),
			'registration'=> $text( 'registration', 1000 ),
			'link'        => isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : '',
			'description' => $text( 'description', 3000 ),
			'relation'    => sanitize_key( $in( 'relation', 20 ) ),
			'email'       => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		];
		if ( '' === $data['name'] || ! isset( Pool_Schema::EVENT_TYPES[ $data['event_type'] ] ) ) {
			Forms::back( $return, 'invalid', 'post-event' );
		}
		if ( ! $data['venue_id'] ) {
			Forms::back( $return, 'venue', 'post-event' );
		}
		$user = get_current_user_id();
		$publish = self::can_publish( $user, $data['venue_id'] );
		if ( ! $publish && ! $user && ! isset( self::RELATIONS[ $data['relation'] ] ) ) {
			Forms::back( $return, 'invalid', 'post-event' );
		}
		$dates = self::date_row( $data );
		if ( is_string( $dates ) ) {
			Forms::back( $return, $dates, 'post-event' );
		}
		$id = self::create( $data, $dates, $publish ? 'publish' : 'pending', $user );
		if ( ! $id ) {
			Forms::back( $return, 'invalid', 'post-event' );
		}
		wp_safe_redirect( add_query_arg( [ 'ppn_msg' => $publish ? 'published' : 'pending', 'event' => $publish ? $id : null ], $return ) . '#post-event' );
		exit;
	}

	/** Venue chosen in the form: an owned-venue select, a picked search result, or an exact name match. */
	private static function resolve_venue(): int {
		foreach ( [ 'venue_id', 'venue_pick' ] as $key ) {
			$id = isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;
			if ( $id && Venue::is_venue( $id ) && 'publish' === get_post_status( $id ) ) {
				return $id;
			}
		}
		$label = isset( $_POST['venue_label'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['venue_label'] ) ) ) : '';
		if ( '' === $label ) {
			return 0;
		}
		$name = trim( preg_replace( '/\s*\([^)]*\)\s*$/', '', $label ) );
		foreach ( self::search_venues( $name, 20 ) as $match ) {
			if ( 0 === strcasecmp( $match['label'], $label ) ) {
				return (int) $match['id'];
			}
		}
		return 0;
	}

	/**
	 * Build the My Listing event row from the form, in the site's timezone.
	 *
	 * @return array|string Row values, or an error code.
	 */
	public static function date_row( array $d, ?\DateTimeImmutable $now = null ) {
		$tz = wp_timezone();
		$now = $now ?? new \DateTimeImmutable( 'now', $tz );
		$start = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $d['date'] . ' ' . $d['start_time'], $tz );
		if ( ! $start || $start->format( 'Y-m-d' ) !== $d['date'] ) {
			return 'date';
		}
		$today = $now->setTime( 0, 0 );
		if ( $start < $today || $start > $today->modify( '+' . self::MAX_DAYS_AHEAD . ' days' ) ) {
			return 'date';
		}
		$end = $start;
		if ( '' !== $d['end_time'] ) {
			$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $d['date'] . ' ' . $d['end_time'], $tz );
			if ( $parsed ) {
				$end = $parsed <= $start ? $parsed->modify( '+1 day' ) : $parsed; // Ends after midnight.
			}
		}
		$repeat = Pool_Schema::EVENT_REPEATS[ $d['repeat'] ] ?? Pool_Schema::EVENT_REPEATS['none'];
		$row = [
			'start_date'  => $start->format( 'Y-m-d H:i:s' ),
			'end_date'    => $end->format( 'Y-m-d H:i:s' ),
			'frequency'   => $repeat[1],
			'repeat_unit' => $repeat[2],
			'repeat_end'  => $start->format( 'Y-m-d H:i:s' ),
		];
		if ( 'NONE' !== $repeat[2] ) {
			$until = \DateTimeImmutable::createFromFormat( '!Y-m-d', $d['until'], $tz );
			if ( ! $until || $until->format( 'Y-m-d' ) !== $d['until'] || $until <= $start->setTime( 0, 0 ) || $until > $today->modify( '+' . self::MAX_DAYS_AHEAD . ' days' ) ) {
				return 'until';
			}
			$row['repeat_end'] = $until->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
		}
		return $row;
	}

	/** Create the event listing, its venue link and its date row. Returns the post ID or 0. */
	public static function create( array $d, array $row, string $status, int $author ): int {
		global $wpdb;
		$venue = Venue::get( (int) $d['venue_id'] );
		if ( ! $venue ) {
			return 0;
		}
		$parts = $venue->address_parts();
		$id = wp_insert_post( [
			'post_type'    => Pool_Schema::POST_TYPE,
			'post_status'  => $status,
			'post_title'   => $d['name'],
			'post_name'    => Format::slugify( $d['name'] . ( $parts['city'] ? ' ' . $parts['city'] : '' ) ),
			'post_content' => $d['description'] ?? '',
			'post_author'  => $author,
		], true );
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		$id = (int) $id;
		update_post_meta( $id, '_case27_listing_type', Pool_Schema::TOURNAMENT_TYPE );
		update_post_meta( $id, '_tournament-status', 'scheduled' );
		$meta = [
			'_entry-fee'               => $d['entry_fee'] ?? '',
			'_added-money'             => $d['added_money'] ?? '',
			'_skill-restrictions'      => $d['eligibility'] ?? '',
			'_registration-info'       => $d['registration'] ?? '',
			'_job_website'             => $d['link'] ?? '',
			'_tournament-table-size'   => isset( self::TABLE_SIZES[ $d['table_size'] ?? '' ] ) ? $d['table_size'] : '',
		];
		foreach ( $meta as $key => $value ) {
			if ( '' !== (string) $value ) {
				update_post_meta( $id, $key, $value );
			}
		}
		wp_set_object_terms( $id, $d['event_type'], Pool_Schema::TAX_EVENT_TYPE );
		$games = array_values( array_filter( (array) ( $d['games'] ?? [] ), static fn( $slug ) => (bool) get_term_by( 'slug', $slug, 'game-type' ) ) );
		if ( $games ) {
			wp_set_object_terms( $id, $games, 'game-type' );
		}
		$wpdb->insert( $wpdb->prefix . 'mylisting_relations', [ 'parent_listing_id' => $venue->id(), 'child_listing_id' => $id, 'field_key' => Pool_Schema::TOURNAMENT_VENUE_FIELD, 'item_order' => 0 ] );
		$wpdb->insert( $wpdb->prefix . 'mylisting_events', array_merge( $row, [ 'listing_id' => $id, 'field_key' => 'event-date' ] ) );

		$owner = 'publish' === $status && ! user_can( $author, 'edit_others_posts' );
		Provenance::set_origin( $id, $owner ? 'owner' : ( $author && user_can( $author, 'edit_others_posts' ) ? 'admin' : 'community' ) );
		if ( 'pending' === $status ) {
			update_post_meta( $id, '_ppn_submission', [
				'relation' => $d['relation'] ?? '',
				'email'    => is_email( $d['email'] ?? '' ) ? $d['email'] : '',
				'at'       => gmdate( 'Y-m-d H:i:s' ),
			] );
			Moderation::notify( 'New event submitted: ' . $d['name'], admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		}
		Play::on_save( $id );
		Stats::flush();
		return $id;
	}

	/* ------------------------------------------------- ended events */

	/** True when an event has no future occurrence (one-off in the past, or a series that has finished). */
	public static function is_ended( int $event_id, ?\DateTimeImmutable $now = null ): bool {
		global $wpdb;
		$now = $now ?? new \DateTimeImmutable( 'now', wp_timezone() );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT start_date, end_date, frequency, repeat_unit, repeat_end FROM {$wpdb->prefix}mylisting_events WHERE listing_id = %d", $event_id ) );
		if ( ! $rows ) {
			return false; // Undated: nothing to judge.
		}
		foreach ( $rows as $row ) {
			// An event that started today stays current until its end time.
			$end = new \DateTimeImmutable( $row->end_date ?: $row->start_date, $now->getTimezone() );
			if ( Play::next_occurrence( $row, $now ) || $end >= $now ) {
				return false;
			}
		}
		return true;
	}

	/** Daily: mark ended events, and once per event remind the person who posted it. */
	public static function daily(): void {
		$ids = get_posts( [
			'post_type'      => Pool_Schema::POST_TYPE,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'meta_query'     => [
				[ 'key' => '_case27_listing_type', 'value' => Pool_Schema::TOURNAMENT_TYPE ],
				[ 'key' => '_ppn_event_ended', 'compare' => 'NOT EXISTS' ],
			],
		] );
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( ! self::is_ended( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_ppn_event_ended', gmdate( 'Y-m-d' ) );
			self::remind( $id );
			$venue = (int) get_post_meta( $id, '_ppn_venue_id', true );
			if ( $venue ) {
				Play::sync_venue( $venue );
			}
		}
	}

	private static function remind( int $event_id ): void {
		if ( get_post_meta( $event_id, '_ppn_reminder_sent', true ) ) {
			return;
		}
		$author = get_userdata( (int) get_post_field( 'post_author', $event_id ) );
		if ( ! $author || ! is_email( $author->user_email ) || user_can( $author, 'manage_options' ) ) {
			return;
		}
		$venue = (int) get_post_meta( $event_id, '_ppn_venue_id', true );
		wp_mail(
			$author->user_email,
			'Post your next event on PlayPoolNation',
			sprintf(
				"Hi %s,\n\n\"%s\" has finished. Players can't see past events in search, so if you have another one coming up, post it here:\n%s\n\nThanks,\nPlayPoolNation",
				$author->display_name,
				html_entity_decode( get_the_title( $event_id ), ENT_QUOTES ),
				self::page_url( $venue )
			)
		);
		update_post_meta( $event_id, '_ppn_reminder_sent', gmdate( 'Y-m-d' ) );
	}

	/** Ended event IDs, for the sitemap. */
	public static function ended_ids(): array {
		return array_map( 'intval', get_posts( [
			'post_type'      => Pool_Schema::POST_TYPE,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_ppn_event_ended',
		] ) );
	}

	/* ------------------------------------------------------- account */

	public static function account_menu( array $items ): array {
		$out = [];
		foreach ( $items as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'my-listings' === $key ) {
				$out['ppn-post-event'] = 'Post an event';
			}
		}
		if ( ! isset( $out['ppn-post-event'] ) ) {
			$out = array_slice( $out, 0, 1, true ) + [ 'ppn-post-event' => 'Post an event' ] + array_slice( $out, 1, null, true );
		}
		return $out;
	}

	public static function account_menu_url( string $url, string $endpoint ): string {
		return 'ppn-post-event' === $endpoint ? self::page_url() : $url;
	}
}
