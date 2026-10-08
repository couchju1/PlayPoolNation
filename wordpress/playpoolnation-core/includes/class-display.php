<?php
/**
 * Front-end output: venue page sections (shortcodes used in My Listing "Static Code"
 * blocks), discovery helpers, and assets.
 *
 * Sections render nothing visible when there is no real data. They emit an empty
 * marker, and Listing_Page removes the surrounding My Listing block (title included),
 * so pages never show "No information available".
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Format;
use PlayPoolNation\Core\Helpers\Tri_State;

defined( 'ABSPATH' ) || exit;

final class Display {

	public const EMPTY = '<span class="ppn-empty" hidden></span>';

	public static function boot(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_shortcode( 'ppn_venue_summary', [ __CLASS__, 'venue_summary' ] );
		add_shortcode( 'ppn_pool_tables', [ __CLASS__, 'pool_tables' ] );
		add_shortcode( 'ppn_amenities', [ __CLASS__, 'amenities' ] );
		add_shortcode( 'ppn_leagues', [ __CLASS__, 'leagues' ] );
		add_shortcode( 'ppn_tournaments', [ __CLASS__, 'tournaments' ] );
		add_shortcode( 'ppn_tournament_details', [ __CLASS__, 'tournament_details' ] );
		add_shortcode( 'ppn_league_details', [ __CLASS__, 'league_details' ] );
		add_shortcode( 'ppn_trust', [ __CLASS__, 'trust' ] );
		add_shortcode( 'ppn_near_me', [ __CLASS__, 'near_me' ] );
		add_shortcode( 'ppn_quick_links', [ __CLASS__, 'quick_links' ] );
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'account_menu' ], 50 );
		add_filter( 'gettext_my-listing', [ __CLASS__, 'theme_strings' ], 10, 2 );
		add_action( 'admin_bar_menu', [ __CLASS__, 'tidy_admin_bar' ], 999 );
	}

	/**
	 * Administrators' toolbar on the public site: with every plugin's item it wrapped onto a
	 * second line that covered the site header. Drop the core items that also live in the
	 * dashboard and shorten the site name so it stays on one line. Plugin items are untouched.
	 */
	public static function tidy_admin_bar( \WP_Admin_Bar $bar ): void {
		if ( is_admin() ) {
			return;
		}
		foreach ( [ 'customize', 'comments', 'new-content', 'search' ] as $node ) {
			$bar->remove_node( $node );
		}
		$site = $bar->get_node( 'site-name' );
		if ( $site ) {
			$bar->add_node( [ 'id' => 'site-name', 'title' => esc_html( Seo_Meta::brand() ) ] );
		}
	}

	/** Account area: favorites are "Saved Places"; purchase-only tabs are hidden (listings are free). */
	public static function account_menu( array $items ): array {
		$labels = [ 'my-bookmarks' => 'Saved Places', 'my-listings' => 'My Listings', 'edit-account' => 'Profile' ];
		foreach ( $labels as $key => $label ) {
			if ( isset( $items[ $key ] ) ) {
				$items[ $key ] = $label;
			}
		}
		foreach ( [ 'promotions', 'downloads', 'orders' ] as $key ) {
			unset( $items[ $key ] );
		}
		return $items;
	}

	/** The homepage search button says what it does. */
	public static function theme_strings( string $translation, string $text ): string {
		if ( 'Search' === $text && ! is_admin() && is_front_page() ) {
			return 'Find places';
		}
		return $translation;
	}

	public static function assets(): void {
		wp_enqueue_style( 'ppn-core', PPN_CORE_URL . '/assets/ppn.css', [], PPN_CORE_VERSION );
		wp_enqueue_script( 'ppn-core', PPN_CORE_URL . '/assets/ppn.js', [], PPN_CORE_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
		wp_localize_script( 'ppn-core', 'ppnCore', [
			'exploreUrl' => self::explore_url(),
			'tokenUrl'   => esc_url_raw( rest_url( 'ppn/v1/form-token' ) ),
			'venuesUrl'  => esc_url_raw( rest_url( 'ppn/v1/venues' ) ),
			'hoursUrl'   => esc_url_raw( rest_url( 'ppn/v1/hours' ) ),
		] );
	}

	/* ------------------------------------------------------------ helpers */

	public static function explore_url( array $args = [] ): string {
		$page = (int) get_option( 'options_general_explore_listings_page' );
		$base = $page ? get_permalink( $page ) : home_url( '/' );
		return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $base ) : (string) $base;
	}

	private static function current_venue( array $atts ): ?Venue {
		$id = isset( $atts['id'] ) ? absint( $atts['id'] ) : (int) get_the_ID();
		return $id ? Venue::get( $id ) : null;
	}

	private static function section( string $class, string $inner ): string {
		return '' === trim( $inner ) ? self::EMPTY : '<div class="ppn-section ' . esc_attr( $class ) . '">' . $inner . '</div>';
	}

	private static function chips( array $labels, string $mod = '' ): string {
		if ( ! $labels ) {
			return '';
		}
		$out = '<ul class="ppn-chips' . ( $mod ? ' ppn-chips--' . esc_attr( $mod ) : '' ) . '">';
		foreach ( $labels as $label ) {
			$out .= '<li>' . esc_html( $label ) . '</li>';
		}
		return $out . '</ul>';
	}

	/* ------------------------------------------------------------ venue */

	/** Key facts strip for the top of a venue page. */
	public static function venue_summary( $atts = [] ): string {
		$v = self::current_venue( (array) $atts );
		if ( ! $v || ! Venue::is_venue( $v->id() ) ) {
			return self::EMPTY;
		}
		$items = [];
		$open = $v->open_status();
		if ( $open['label'] ) {
			$items[] = '<li class="ppn-fact ppn-fact--' . esc_attr( $open['state'] ) . '"' . Open_Status::attr( $v ) . '>' . esc_html( $open['label'] ) . '</li>';
		}
		$total = $v->total_tables();
		if ( $total ) {
			$items[] = '<li class="ppn-fact"><strong>' . esc_html( (string) $total ) . '</strong> ' . esc_html( _n( 'table', 'tables', $total, 'playpoolnation-core' ) ) . '</li>';
		}
		$sizes = Format::sizes_label( $v->size_slugs() );
		if ( $sizes ) {
			$items[] = '<li class="ppn-fact">' . esc_html( $sizes ) . '</li>';
		}
		$brands = $v->term_names( 'table-brand' );
		if ( $brands ) {
			$items[] = '<li class="ppn-fact">' . esc_html( Format::join_list( $brands ) ) . '</li>';
		}
		$verification = Verification::get( $v->id() );
		if ( Verification::has_badge( $verification ) ) {
			$items[] = '<li class="ppn-fact ppn-fact--verified">' . esc_html( $verification['label'] ) . '</li>';
		}
		if ( Promotions::is_promoted( $v->id() ) ) {
			$items[] = '<li class="ppn-fact ppn-fact--promo">Featured</li>';
		}
		return $items ? '<ul class="ppn-facts">' . implode( '', $items ) . '</ul>' : self::EMPTY;
	}

	public static function pool_tables( $atts = [] ): string {
		$v = self::current_venue( (array) $atts );
		if ( ! $v ) {
			return self::EMPTY;
		}
		$html = '';

		$total = $v->total_tables();
		$size_rows = '';
		foreach ( Pool_Schema::COUNT_FIELDS as $field => $def ) {
			if ( 'number-of-tables' === $field ) {
				continue;
			}
			$count = $v->count( $field );
			$has = $def['size'] ? Tri_State::YES === $v->state_of( 'table-size', $def['size'] ) : false;
			if ( $count ) {
				$size_rows .= '<li><span class="ppn-size">' . esc_html( $def['label'] ) . '</span><span class="ppn-size-count">' . esc_html( (string) $count ) . '</span></li>';
			} elseif ( $has ) {
				$size_rows .= '<li><span class="ppn-size">' . esc_html( $def['label'] ) . '</span></li>';
			}
		}
		if ( $total || $size_rows ) {
			$html .= '<div class="ppn-tables-head">';
			if ( $total ) {
				$html .= '<p class="ppn-total"><span class="ppn-total-num">' . esc_html( (string) $total ) . '</span> ' . esc_html( _n( 'pool table', 'pool tables', $total, 'playpoolnation-core' ) ) . '</p>';
			}
			if ( $size_rows ) {
				$html .= '<ul class="ppn-sizes">' . $size_rows . '</ul>';
			}
			$html .= '</div>';
		}

		$details = '';
		$brands = $v->term_names( 'table-brand' );
		if ( $brands ) {
			$details .= '<div><dt>' . esc_html__( 'Tables', 'playpoolnation-core' ) . '</dt><dd>' . esc_html( Format::join_list( $brands ) ) . '</dd></div>';
		}
		foreach ( [ 'table-models' => 'Models', 'table-cloth' => 'Cloth', 'table-balls' => 'Balls' ] as $field => $label ) {
			$value = $v->field( $field );
			if ( '' !== $value ) {
				$details .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
		}
		if ( $details ) {
			$html .= '<dl class="ppn-dl">' . $details . '</dl>';
		}
		$notes = $v->field( 'equipment-notes' );
		if ( '' !== $notes ) {
			$html .= '<p class="ppn-note">' . esc_html( $notes ) . '</p>';
		}

		$pricing = '';
		$models = $v->term_names( 'pool-pricing' );
		if ( $models ) {
			$pricing .= self::chips( $models );
		}
		$prices = [];
		foreach ( Pool_Schema::PRICE_FIELDS as $field => $label ) {
			$money = Format::money( $v->field( $field ) );
			if ( $money ) {
				$prices[] = '<span class="ppn-price"><strong>' . esc_html( $money ) . '</strong> ' . esc_html( strtolower( $label ) ) . '</span>';
			}
		}
		if ( $prices ) {
			$pricing .= '<p class="ppn-prices">' . implode( ' ', $prices ) . '</p>';
		}
		$pricing_notes = $v->field( 'pricing-notes' );
		if ( '' !== $pricing_notes ) {
			$pricing .= '<p class="ppn-note">' . esc_html( $pricing_notes ) . '</p>';
		}
		if ( $pricing ) {
			$html .= '<h6 class="ppn-subhead">' . esc_html__( 'Pricing', 'playpoolnation-core' ) . '</h6>' . $pricing;
		}

		return self::section( 'ppn-tables', $html );
	}

	public static function amenities( $atts = [] ): string {
		$v = self::current_venue( (array) $atts );
		if ( ! $v ) {
			return self::EMPTY;
		}
		$have = $v->term_slugs( Pool_Schema::TAX_AMENITY );
		$html = '';
		foreach ( Pool_Schema::AMENITY_GROUPS as $group => $items ) {
			$labels = [];
			foreach ( $items as $slug => $label ) {
				if ( in_array( $slug, $have, true ) ) {
					$labels[] = $label;
				}
			}
			if ( $labels ) {
				$html .= '<div class="ppn-amenity-group"><h6 class="ppn-subhead">' . esc_html( $group ) . '</h6>' . self::chips( $labels ) . '</div>';
			}
		}
		// Admin-added amenities outside the standard groups.
		$standard = array_keys( Pool_Schema::all_amenities() );
		$extra = [];
		foreach ( $v->terms( Pool_Schema::TAX_AMENITY ) as $term ) {
			if ( ! in_array( $term->slug, $standard, true ) ) {
				$extra[] = html_entity_decode( $term->name, ENT_QUOTES );
			}
		}
		if ( $extra ) {
			$html .= '<div class="ppn-amenity-group"><h6 class="ppn-subhead">' . esc_html__( 'Also', 'playpoolnation-core' ) . '</h6>' . self::chips( $extra ) . '</div>';
		}
		return self::section( 'ppn-amenities', $html ? '<div class="ppn-amenity-grid">' . $html . '</div>' : '' );
	}

	public static function leagues( $atts = [] ): string {
		$v = self::current_venue( (array) $atts );
		if ( ! $v ) {
			return self::EMPTY;
		}
		$rows = '';
		foreach ( Play::leagues( $v->id() ) as $l ) {
			$when = trim( $l['day'] . ( $l['time'] ? ' ' . $l['time'] : '' ) );
			$meta = array_filter( [ Format::join_list( $l['games'] ), $l['season'], 'Active' !== $l['status'] ? $l['status'] : '' ] );
			$rows .= '<li class="ppn-row">'
				. ( $l['orgs'] ? '<span class="ppn-org">' . esc_html( implode( ' / ', $l['orgs'] ) ) . '</span>' : '' )
				. '<a class="ppn-row-title" href="' . esc_url( $l['url'] ) . '">' . esc_html( $l['name'] ) . '</a>'
				. ( $when ? '<span class="ppn-row-when">' . esc_html( $when ) . '</span>' : '' )
				. ( $meta ? '<span class="ppn-row-meta">' . esc_html( implode( ', ', $meta ) ) . '</span>' : '' )
				. '</li>';
		}
		if ( ! $rows && Venue::is_venue( $v->id() ) ) {
			return self::section( 'ppn-leagues', '<p class="ppn-tell-us">Know the league night here? <a href="#suggest-edit" data-ppn-suggest="league">Tell us</a></p>' );
		}
		return self::section( 'ppn-leagues', $rows ? '<ul class="ppn-rows">' . $rows . '</ul>' : '' );
	}

	public static function tournaments( $atts = [] ): string {
		$v = self::current_venue( (array) $atts );
		if ( ! $v ) {
			return self::EMPTY;
		}
		$rows = '';
		foreach ( Play::upcoming_tournaments( $v->id() ) as $t ) {
			$meta = array_filter( [
				$t['promoted'] ? 'Promoted' : '',
				'Tournament' !== $t['type'] ? $t['type'] : '',
				Format::join_list( $t['games'] ),
				Format::money( $t['entry_fee'] ) ? Format::money( $t['entry_fee'] ) . ' entry' : '',
				Format::money( $t['added_money'] ) ? Format::money( $t['added_money'] ) . ' added' : '',
				$t['recurring'] ? 'Recurring' : '',
				'Scheduled' !== $t['status'] ? $t['status'] : '',
			] );
			$rows .= '<li class="ppn-row">'
				. '<time class="ppn-date" datetime="' . esc_attr( $t['when']->format( 'c' ) ) . '"><span>' . esc_html( $t['when']->format( 'M' ) ) . '</span><strong>' . esc_html( $t['when']->format( 'j' ) ) . '</strong></time>'
				. '<a class="ppn-row-title" href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['name'] ) . '</a>'
				. '<span class="ppn-row-when">' . esc_html( $t['when']->format( 'l' ) . ', ' . Format::clock( (int) $t['when']->format( 'G' ) * 60 + (int) $t['when']->format( 'i' ) ) ) . '</span>'
				. ( $meta ? '<span class="ppn-row-meta">' . esc_html( implode( ', ', $meta ) ) . '</span>' : '' )
				. '</li>';
		}
		return self::section( 'ppn-tournaments', $rows ? '<ul class="ppn-rows ppn-rows--dated">' . $rows . '</ul>' : '' );
	}

	/** Details block on a tournament page. */
	public static function tournament_details( $atts = [] ): string {
		$v = self::current_venue( (array) $atts );
		if ( ! $v ) {
			return self::EMPTY;
		}
		$id = $v->id();
		$rows = [ 'Type' => esc_html( Events::event_type_label( $id ) ) ];
		$venue_id = (int) get_post_meta( $id, '_ppn_venue_id', true );
		if ( $venue_id && 'publish' === get_post_status( $venue_id ) ) {
			$rows['Venue'] = '<a href="' . esc_url( get_permalink( $venue_id ) ) . '">' . esc_html( get_the_title( $venue_id ) ) . '</a>';
		}
		$next = Play::upcoming_from_ids( [ $id ], 1 );
		if ( $next ) {
			$when = $next[0]['when'];
			$rows['Next'] = esc_html( wp_date( 'l, F j', $when->getTimestamp() ) . ' at ' . Format::clock( (int) $when->format( 'G' ) * 60 + (int) $when->format( 'i' ) ) ) . ( $next[0]['recurring'] ? ' <span class="ppn-fineprint">(repeats)</span>' : '' );
		}
		$games = wp_get_object_terms( $id, 'game-type', [ 'fields' => 'names' ] );
		if ( $games ) {
			$rows['Game'] = esc_html( Format::join_list( $games ) );
		}
		$size = Events::TABLE_SIZES[ $v->field( 'tournament-table-size' ) ] ?? Format::sizes_label( wp_get_object_terms( $id, 'table-size', [ 'fields' => 'slugs' ] ) );
		if ( $size ) {
			$rows['Tables'] = esc_html( $size );
		}
		foreach ( [ 'entry-fee' => 'Entry fee', 'added-money' => 'Added money' ] as $field => $label ) {
			$money = Format::money( $v->field( $field ) );
			if ( $money ) {
				$rows[ $label ] = esc_html( $money );
			}
		}
		foreach ( [ 'skill-restrictions' => 'Eligibility', 'registration-info' => 'Registration' ] as $field => $label ) {
			if ( '' !== $v->field( $field ) ) {
				$rows[ $label ] = nl2br( esc_html( $v->field( $field ) ) );
			}
		}
		$status = Pool_Schema::TOURNAMENT_STATUSES[ $v->field( 'tournament-status' ) ] ?? '';
		if ( $status && 'Scheduled' !== $status ) {
			$rows['Status'] = esc_html( $status );
		}
		$ended = Events::is_ended( $id ) ? '<p class="ppn-notice ppn-notice--ended" role="status">This event has ended.' . ( $venue_id ? ' <a href="' . esc_url( get_permalink( $venue_id ) ) . '">See what is coming up at this venue</a>.' : '' ) . '</p>' : '';
		return self::section( 'ppn-details', $ended . self::dl( $rows ) );
	}

	/** Details block on a league page. */
	public static function league_details( $atts = [] ): string {
		$v = self::current_venue( (array) $atts );
		if ( ! $v ) {
			return self::EMPTY;
		}
		$id = $v->id();
		$rows = [];
		$venue_id = (int) get_post_meta( $id, '_ppn_venue_id', true );
		if ( $venue_id && 'publish' === get_post_status( $venue_id ) ) {
			$rows['Venue'] = '<a href="' . esc_url( get_permalink( $venue_id ) ) . '">' . esc_html( get_the_title( $venue_id ) ) . '</a>';
		}
		$orgs = wp_get_object_terms( $id, 'league-org', [ 'fields' => 'names' ] );
		if ( $orgs ) {
			$rows['Organization'] = esc_html( Format::join_list( $orgs ) );
		}
		$games = wp_get_object_terms( $id, 'game-type', [ 'fields' => 'names' ] );
		if ( $games ) {
			$rows['Game'] = esc_html( Format::join_list( $games ) );
		}
		$day = Pool_Schema::LEAGUE_DAYS[ $v->field( 'league-day' ) ] ?? '';
		$minutes = get_post_meta( $id, '_ppn_league_minutes', true );
		$when = trim( $day . ( '' !== $minutes ? ' at ' . Format::clock( (int) $minutes ) : '' ) );
		if ( $when ) {
			$rows['Plays'] = esc_html( $when );
		}
		foreach ( [ 'league-season' => 'Season' ] as $field => $label ) {
			if ( '' !== $v->field( $field ) ) {
				$rows[ $label ] = esc_html( $v->field( $field ) );
			}
		}
		$status = Pool_Schema::LEAGUE_STATUSES[ $v->field( 'league-status' ) ] ?? '';
		if ( $status ) {
			$rows['Status'] = esc_html( $status );
		}
		return self::section( 'ppn-details', self::dl( $rows ) );
	}

	/** @param array<string,string> $rows label => already-escaped HTML */
	private static function dl( array $rows ): string {
		if ( ! $rows ) {
			return '';
		}
		$out = '<dl class="ppn-dl">';
		foreach ( $rows as $label => $html ) {
			$out .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . $html . '</dd></div>';
		}
		return $out . '</dl>';
	}

	/** Verification, claim and suggest-an-edit. Always renders on venue pages (the actions are useful). */
	public static function trust( $atts = [] ): string {
		$v = self::current_venue( (array) $atts );
		if ( ! $v || ! Venue::is_venue( $v->id() ) ) {
			return self::EMPTY;
		}
		$html = '';
		$verification = Verification::get( $v->id() );
		if ( Verification::has_badge( $verification ) ) {
			$html .= '<p class="ppn-verified"><span class="ppn-badge">' . esc_html( $verification['label'] ) . '</span> '
				. esc_html( sprintf( 'Information verified %s', wp_date( 'F j, Y', strtotime( $verification['date'] ) ) ) ) . '</p>';
		} else {
			$checked = (string) get_post_meta( $v->id(), '_ppn_last_checked', true );
			if ( $checked ) {
				$html .= '<p class="ppn-checked">' . esc_html( sprintf( 'Address, phone and hours last checked %s.', wp_date( 'F j, Y', strtotime( $checked ) ) ) ) . '</p>';
			}
		}

		$claim_url = Claims::claim_url( $v->id() );
		if ( $claim_url ) {
			$html .= '<p class="ppn-claim">' . esc_html__( 'Own or manage this venue?', 'playpoolnation-core' )
				. ' <a href="' . esc_url( $claim_url ) . '">' . esc_html__( 'Claim this listing', 'playpoolnation-core' ) . '</a></p>';
		}
		$html .= '<p class="ppn-claim">' . esc_html__( 'Hosting a tournament or event here?', 'playpoolnation-core' )
			. ' <a href="' . esc_url( Events::page_url( $v->id() ) ) . '">' . esc_html__( 'Post an event', 'playpoolnation-core' ) . '</a></p>';
		$html .= Forms::suggest_edit_form( $v->id() );
		return '<div class="ppn-section ppn-trust">' . $html . '</div>';
	}

	/* --------------------------------------------------------- discovery */

	public static function near_me( $atts = [] ): string {
		$atts = shortcode_atts( [ 'label' => 'Near me', 'class' => '' ], (array) $atts );
		return '<button type="button" class="ppn-near-me ' . esc_attr( $atts['class'] ) . '" data-ppn-near-me>'
			. '<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm9 3h-2.07A7 7 0 0 0 13 5.07V3h-2v2.07A7 7 0 0 0 5.07 11H3v2h2.07A7 7 0 0 0 11 18.93V21h2v-2.07A7 7 0 0 0 18.93 13H21v-2Zm-9 6a5 5 0 1 1 0-10 5 5 0 0 1 0 10Z"/></svg>'
			. '<span>' . esc_html( $atts['label'] ) . '</span></button>'
			. '<span class="ppn-near-me-msg" role="status" aria-live="polite"></span>';
	}

	/** Shortcut chips for the homepage. Only shows a shortcut when real listings back it. */
	public static function quick_links(): string {
		$links = [];
		$links[] = self::near_me( [ 'label' => 'Near me', 'class' => 'ppn-quick' ] );
		$pool = get_term_by( 'slug', 'pool-halls', Pool_Schema::TAX_VENUE_TYPE );
		if ( $pool && $pool->count ) {
			$links[] = '<a class="ppn-quick" href="' . esc_url( self::explore_url( [ 'type' => Pool_Schema::VENUE_TYPE, 'category' => 'pool-halls' ] ) ) . '">Pool halls</a>';
		}
		$bar = get_term_by( 'slug', 'bars-with-pool-tables', Pool_Schema::TAX_VENUE_TYPE );
		if ( $bar && $bar->count ) {
			$links[] = '<a class="ppn-quick" href="' . esc_url( self::explore_url( [ 'type' => Pool_Schema::VENUE_TYPE, 'category' => 'bars-with-pool-tables' ] ) ) . '">Bars with pool</a>';
		}
		foreach ( [ 'events' => 'Events', 'leagues' => 'Leagues', 'instructors' => 'Instructors' ] as $slug => $label ) {
			$page = get_page_by_path( $slug );
			if ( ! $page || 'publish' !== $page->post_status ) {
				continue;
			}
			// Only link to the instructor directory once it has profiles.
			if ( 'instructors' === $slug && empty( Stats::stats()['instructors'] ) ) {
				continue;
			}
			$links[] = '<a class="ppn-quick" href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( $label ) . '</a>';
		}
		return '<nav class="ppn-quick-links" aria-label="Quick discovery">' . implode( '', $links ) . '</nav>';
	}
}
