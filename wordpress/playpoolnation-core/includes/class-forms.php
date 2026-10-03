<?php
/**
 * Public forms: Suggest an Edit and Add a Venue.
 *
 * Pages are served from LiteSpeed's page cache, so a nonce printed into the HTML
 * would go stale. Instead each form fetches a short-lived signed token from an
 * uncached REST endpoint, and submissions also pass a honeypot, a minimum fill
 * time and a per-IP rate limit. Nothing a visitor submits changes a published
 * venue: suggestions go to moderation and new venues are created as pending.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Address;
use PlayPoolNation\Core\Helpers\Dedupe;

defined( 'ABSPATH' ) || exit;

final class Forms {

	public const SUGGEST_REASONS = [
		'hours'           => 'Incorrect hours',
		'address'         => 'Incorrect address',
		'phone'           => 'Incorrect phone',
		'table-count'     => 'Incorrect table count',
		'table-brand'     => 'Incorrect table brand',
		'new-tables'      => 'New tables',
		'tables-removed'  => 'Tables removed',
		'closed'          => 'Venue closed',
		'reopened'        => 'Venue reopened',
		'league'          => 'League change',
		'tournament'      => 'Tournament change',
		'amenity'         => 'Amenity change',
		'other'           => 'Other',
	];

	public const RELATIONS = [ 'player' => 'I play there', 'owner' => 'I own or manage it', 'staff' => 'I work there', 'other' => 'Other' ];

	private const MIN_SECONDS = 3;
	private const MAX_AGE = 2 * HOUR_IN_SECONDS;
	private const RATE_LIMIT = 5; // per hour per IP per form

	public static function boot(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		foreach ( [ 'ppn_suggest_edit', 'ppn_add_venue' ] as $action ) {
			add_action( 'admin_post_nopriv_' . $action, [ __CLASS__, 'handle_' . substr( $action, 4 ) ] );
			add_action( 'admin_post_' . $action, [ __CLASS__, 'handle_' . substr( $action, 4 ) ] );
		}
		add_shortcode( 'ppn_add_venue', [ __CLASS__, 'add_venue_form' ] );
	}

	/* ---------------------------------------------------------- tokens */

	public static function routes(): void {
		register_rest_route( 'ppn/v1', '/form-token', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => static function () {
				$ts = time();
				$res = new \WP_REST_Response( [ 'token' => self::make_token( $ts ) ] );
				$res->header( 'Cache-Control', 'no-store, private' );
				return $res;
			},
		] );
	}

	public static function make_token( int $ts ): string {
		return $ts . '.' . hash_hmac( 'sha256', 'ppn-form|' . $ts, wp_salt( 'nonce' ) );
	}

	public static function verify_token( string $token, ?int $now = null ): bool {
		$now = $now ?? time();
		if ( ! preg_match( '/^(\d{9,11})\.([a-f0-9]{64})$/', $token, $m ) ) {
			return false;
		}
		$ts = (int) $m[1];
		if ( $now - $ts < self::MIN_SECONDS || $now - $ts > self::MAX_AGE ) {
			return false;
		}
		return hash_equals( self::make_token( $ts ), $token );
	}

	private static function client_key( string $form ): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return 'ppn_rl_' . substr( hash_hmac( 'sha256', $form . '|' . $ip, wp_salt( 'auth' ) ), 0, 32 );
	}

	private static function rate_limited( string $form ): bool {
		$key = self::client_key( $form );
		$n = (int) get_transient( $key );
		if ( $n >= self::RATE_LIMIT ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}

	/** Shared checks; returns an error code or '' when the request may proceed. */
	private static function guard( string $form ): string {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return 'method';
		}
		if ( ! empty( $_POST['ppn_hp'] ) ) {
			return 'spam'; // Honeypot filled by a bot.
		}
		$token = isset( $_POST['ppn_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ppn_token'] ) ) : '';
		if ( ! self::verify_token( $token ) ) {
			return 'expired';
		}
		if ( self::rate_limited( $form ) ) {
			return 'limit';
		}
		return '';
	}

	private static function back( string $url, string $msg, string $anchor ): void {
		wp_safe_redirect( add_query_arg( 'ppn_msg', $msg, $url ) . '#' . $anchor );
		exit;
	}

	private static function message( string $form ): string {
		$msg = isset( $_GET['ppn_msg'] ) ? sanitize_key( wp_unslash( $_GET['ppn_msg'] ) ) : '';
		$texts = [
			'suggest' => [
				'thanks'  => [ 'ok', 'Thanks. Your suggestion was sent to our team for review.' ],
				'invalid' => [ 'error', 'Please choose what needs fixing and describe the change.' ],
			],
			'venue'   => [
				'thanks'    => [ 'ok', 'Thanks. We will review the venue and add it to the map once it is confirmed.' ],
				'duplicate' => [ 'ok', 'Thanks. This looks like it may already be listed, so our team will check before adding it.' ],
				'invalid'   => [ 'error', 'Please fill in the venue name, street address, city and state.' ],
			],
		];
		$common = [
			'expired' => [ 'error', 'The form expired. Please try again.' ],
			'limit'   => [ 'error', 'Too many submissions from your connection. Please try again later.' ],
			'spam'    => [ 'error', 'Your submission could not be accepted.' ],
		];
		$all = array_merge( $common, $texts[ $form ] ?? [] );
		if ( ! isset( $all[ $msg ] ) ) {
			return '';
		}
		[ $type, $text ] = $all[ $msg ];
		return '<p class="ppn-notice ppn-notice--' . esc_attr( $type ) . '" role="' . ( 'ok' === $type ? 'status' : 'alert' ) . '">' . esc_html( $text ) . '</p>';
	}

	private static function common_fields(): string {
		// Honeypot is visually hidden and skipped by keyboard and assistive tech.
		return '<div class="ppn-hp" aria-hidden="true"><label>Leave this empty<input type="text" name="ppn_hp" tabindex="-1" autocomplete="off"></label></div>'
			. '<input type="hidden" name="ppn_token" value="" data-ppn-token>';
	}

	private static function select( string $name, string $id, array $options, string $placeholder, bool $required ): string {
		$out = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '>';
		$out .= '<option value="">' . esc_html( $placeholder ) . '</option>';
		foreach ( $options as $value => $label ) {
			$out .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		return $out . '</select>';
	}

	/* ------------------------------------------------- suggest an edit */

	public static function suggest_edit_form( int $venue_id ): string {
		$message = self::message( 'suggest' );
		$open = $message ? ' open' : '';
		$html = '<details class="ppn-suggest" id="suggest-edit"' . $open . '>';
		$html .= '<summary class="ppn-button ppn-button--ghost">Suggest an edit</summary>' . $message;
		$html .= '<form class="ppn-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="ppn_suggest_edit"><input type="hidden" name="venue_id" value="' . esc_attr( (string) $venue_id ) . '">';
		$html .= self::common_fields();
		$html .= '<p><label for="ppn-reason">What needs fixing?</label>' . self::select( 'reason', 'ppn-reason', self::SUGGEST_REASONS, 'Choose one', true ) . '</p>';
		$html .= '<p><label for="ppn-details">Details</label><textarea id="ppn-details" name="details" rows="4" maxlength="2000" required placeholder="For example: there are now 8 tables, two of them 9-foot Diamonds."></textarea></p>';
		$html .= '<div class="ppn-form-row"><p><label for="ppn-s-name">Your name <span class="ppn-optional">(optional)</span></label><input id="ppn-s-name" type="text" name="name" maxlength="100" autocomplete="name"></p>';
		$html .= '<p><label for="ppn-s-email">Email <span class="ppn-optional">(optional, only used if we have a question)</span></label><input id="ppn-s-email" type="email" name="email" maxlength="200" autocomplete="email"></p></div>';
		$html .= '<p><button type="submit" class="ppn-button">Send suggestion</button></p>';
		$html .= '<p class="ppn-fineprint">Suggestions are reviewed before anything on the listing changes.</p>';
		return $html . '</form></details>';
	}

	public static function handle_suggest_edit(): void {
		$venue_id = isset( $_POST['venue_id'] ) ? absint( $_POST['venue_id'] ) : 0;
		$return = $venue_id && 'publish' === get_post_status( $venue_id ) ? get_permalink( $venue_id ) : home_url( '/' );
		$error = self::guard( 'suggest' );
		if ( $error ) {
			self::back( $return, $error, 'suggest-edit' );
		}
		$reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
		if ( ! $venue_id || ! Venue::is_venue( $venue_id ) || ! isset( self::SUGGEST_REASONS[ $reason ] ) || '' === trim( $details ) ) {
			self::back( $return, 'invalid', 'suggest-edit' );
		}
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		Moderation::create_suggestion( [
			'venue_id' => $venue_id,
			'reason'   => $reason,
			'details'  => mb_substr( $details, 0, 2000 ),
			'name'     => isset( $_POST['name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 100 ) : '',
			'email'    => is_email( $email ) ? $email : '',
		] );
		self::back( $return, 'thanks', 'suggest-edit' );
	}

	/* ------------------------------------------------------ add a venue */

	public static function add_venue_form(): string {
		$types = [];
		foreach ( Pool_Schema::VENUE_TYPES as $slug => $label ) {
			if ( get_term_by( 'slug', $slug, Pool_Schema::TAX_VENUE_TYPE ) ) {
				$types[ $slug ] = $label;
			}
		}
		$states = Address::STATES;
		asort( $states );
		$html = '<div class="ppn-add-venue" id="add-venue">' . self::message( 'venue' );
		$html .= '<form class="ppn-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="ppn_add_venue">' . self::common_fields();
		$html .= '<fieldset><legend>The venue</legend>';
		$html .= '<p><label for="ppn-v-name">Venue name</label><input id="ppn-v-name" type="text" name="name" required maxlength="150"></p>';
		$html .= '<p><label for="ppn-v-street">Street address</label><input id="ppn-v-street" type="text" name="street" required maxlength="200" autocomplete="street-address"></p>';
		$html .= '<div class="ppn-form-row"><p><label for="ppn-v-city">City</label><input id="ppn-v-city" type="text" name="city" required maxlength="100" autocomplete="address-level2"></p>';
		$html .= '<p><label for="ppn-v-state">State</label>' . self::select( 'state', 'ppn-v-state', $states, 'Choose a state', true ) . '</p>';
		$html .= '<p><label for="ppn-v-zip">ZIP <span class="ppn-optional">(optional)</span></label><input id="ppn-v-zip" type="text" name="zip" inputmode="numeric" pattern="\d{5}" maxlength="5" autocomplete="postal-code"></p></div>';
		$html .= '<p><label for="ppn-v-type">Type of place</label>' . self::select( 'venue_type', 'ppn-v-type', $types, 'Choose one', false ) . '</p>';
		$html .= '<div class="ppn-form-row"><p><label for="ppn-v-phone">Phone <span class="ppn-optional">(optional)</span></label><input id="ppn-v-phone" type="tel" name="phone" maxlength="30"></p>';
		$html .= '<p><label for="ppn-v-web">Website <span class="ppn-optional">(optional)</span></label><input id="ppn-v-web" type="url" name="website" maxlength="300" placeholder="https://"></p></div>';
		$html .= '<p><label for="ppn-v-tables">Number of pool tables <span class="ppn-optional">(if you know)</span></label><input id="ppn-v-tables" type="number" name="tables" min="0" max="500" inputmode="numeric"></p>';
		$html .= '<p><label for="ppn-v-notes">Anything else players should know? <span class="ppn-optional">(optional)</span></label><textarea id="ppn-v-notes" name="notes" rows="3" maxlength="1500" placeholder="Table sizes and brands, pricing, leagues, hours..."></textarea></p>';
		$html .= '</fieldset><fieldset><legend>About you</legend>';
		$html .= '<p><label for="ppn-v-rel">Your connection to the venue</label>' . self::select( 'relation', 'ppn-v-rel', self::RELATIONS, 'Choose one', true ) . '</p>';
		$html .= '<p><label for="ppn-v-email">Email <span class="ppn-optional">(optional, only used if we have a question)</span></label><input id="ppn-v-email" type="email" name="email" maxlength="200" autocomplete="email"></p>';
		$html .= '</fieldset><p><button type="submit" class="ppn-button">Submit venue</button></p>';
		$html .= '<p class="ppn-fineprint">Every submission is checked before it appears on PlayPoolNation.</p>';
		return $html . '</form></div>';
	}

	public static function handle_add_venue(): void {
		$referer = wp_get_referer();
		$return = $referer && wp_validate_redirect( $referer ) ? strtok( $referer, '?#' ) : home_url( '/' );
		$error = self::guard( 'venue' );
		if ( $error ) {
			self::back( $return, $error, 'add-venue' );
		}
		$in = static fn( string $key, int $max = 200 ) => isset( $_POST[ $key ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ), 0, $max ) : '';
		$data = [
			'name'       => $in( 'name', 150 ),
			'street'     => $in( 'street' ),
			'city'       => $in( 'city', 100 ),
			'state'      => strtoupper( $in( 'state', 2 ) ),
			'zip'        => preg_replace( '/\D/', '', $in( 'zip', 5 ) ),
			'phone'      => $in( 'phone', 30 ),
			'website'    => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '',
			'venue_type' => sanitize_title( $in( 'venue_type', 60 ) ),
			'tables'     => isset( $_POST['tables'] ) && '' !== $_POST['tables'] ? (string) min( 500, absint( $_POST['tables'] ) ) : '',
			'notes'      => isset( $_POST['notes'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ), 0, 1500 ) : '',
			'relation'   => sanitize_key( $in( 'relation', 20 ) ),
			'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		];
		if ( '' === $data['name'] || '' === $data['street'] || '' === $data['city'] || ! isset( Address::STATES[ $data['state'] ] ) || ! isset( self::RELATIONS[ $data['relation'] ] ) ) {
			self::back( $return, 'invalid', 'add-venue' );
		}
		$result = self::create_pending_venue( $data );
		self::back( $return, $result['duplicate_of'] ? 'duplicate' : 'thanks', 'add-venue' );
	}

	/**
	 * Create a pending venue from a community submission.
	 *
	 * @return array{id:int,duplicate_of:int}
	 */
	public static function create_pending_venue( array $data ): array {
		$address = trim( $data['street'] . ', ' . $data['city'] . ', ' . $data['state'] . ( $data['zip'] ? ' ' . $data['zip'] : '' ) );
		$geo = Geocoder::geocode( $address );

		$duplicate = 0;
		foreach ( Importer::candidates( $geo['lat'] ?? null, $geo['lng'] ?? null, $data['name'] ) as $candidate ) {
			$cmp = Dedupe::compare(
				[ 'name' => $data['name'], 'phone' => $data['phone'], 'website' => $data['website'], 'lat' => $geo['lat'] ?? '', 'lng' => $geo['lng'] ?? '' ],
				$candidate
			);
			if ( Dedupe::DISTINCT !== $cmp['class'] ) {
				$duplicate = (int) $candidate['id'];
				break;
			}
		}

		$post_id = wp_insert_post( [
			'post_type'    => Pool_Schema::POST_TYPE,
			'post_status'  => 'pending',
			'post_title'   => $data['name'],
			'post_content' => '',
			'post_author'  => get_current_user_id(),
		], true );
		if ( is_wp_error( $post_id ) ) {
			return [ 'id' => 0, 'duplicate_of' => $duplicate ];
		}
		update_post_meta( $post_id, '_case27_listing_type', Pool_Schema::VENUE_TYPE );
		update_post_meta( $post_id, '_job_location', $geo['address'] ?? $address );
		if ( $data['phone'] ) {
			update_post_meta( $post_id, '_job_phone', $data['phone'] );
		}
		if ( $data['website'] ) {
			update_post_meta( $post_id, '_job_website', $data['website'] );
		}
		if ( '' !== $data['tables'] ) {
			update_post_meta( $post_id, '_number-of-tables', $data['tables'] );
			Provenance::record_field( $post_id, 'number-of-tables', 'community' );
		}
		if ( $data['venue_type'] && isset( Pool_Schema::VENUE_TYPES[ $data['venue_type'] ] ) ) {
			wp_set_object_terms( $post_id, $data['venue_type'], Pool_Schema::TAX_VENUE_TYPE );
		}
		if ( isset( $geo['lat'], $geo['lng'] ) ) {
			global $wpdb;
			$wpdb->insert( $wpdb->prefix . 'mylisting_locations', [ 'listing_id' => $post_id, 'address' => $geo['address'], 'lat' => round( $geo['lat'], 5 ), 'lng' => round( $geo['lng'], 5 ) ] );
		}
		update_post_meta( $post_id, '_ppn_submission', [
			'notes'    => $data['notes'],
			'relation' => $data['relation'],
			'email'    => is_email( $data['email'] ) ? $data['email'] : '',
			'at'       => gmdate( 'Y-m-d H:i:s' ),
		] );
		if ( $duplicate ) {
			update_post_meta( $post_id, '_ppn_possible_duplicate_of', $duplicate );
		}
		Provenance::set_origin( $post_id, 'community' );
		Moderation::notify( 'New venue submitted: ' . $data['name'], admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
		return [ 'id' => (int) $post_id, 'duplicate_of' => $duplicate ];
	}
}
