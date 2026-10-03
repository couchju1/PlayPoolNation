<?php
/**
 * Pool instructors: a My Listing listing type (`instructor`) for people who teach.
 *
 * Instructors create their own free profile through the theme's add-listing form
 * (held for approval like every submission). Profiles show a city, never a home
 * address: on save the location is reduced to "City, ST" and mapped to the city.
 * Instructors are not given region terms, so place counts stay venue-only.
 *
 * Credentials are what the instructor reports. A credential is marked Verified only
 * after an administrator checks it and ticks it on the profile's edit screen.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Address;
use PlayPoolNation\Core\Helpers\Format;

defined( 'ABSPATH' ) || exit;

final class Instructors {

	public const SIGNUP_PAGE = 'teach';
	private const VERIFIED_META = '_ppn_credentials_verified';
	private const NONCE = 'ppn_instructor_verify';

	public static function boot(): void {
		add_shortcode( 'ppn_instructor_details', [ __CLASS__, 'details' ] );
		add_shortcode( 'ppn_instructor_trust', [ __CLASS__, 'trust' ] );
		add_shortcode( 'ppn_instructors_here', [ __CLASS__, 'at_venue' ] );
		add_action( 'mylisting/admin/save-listing-data', [ __CLASS__, 'on_save' ], 75, 1 );
		add_action( 'mylisting/submission/save-listing-data', [ __CLASS__, 'on_save' ], 75, 1 );
		add_action( 'add_meta_boxes_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'meta_box' ] );
		add_action( 'save_post_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'save_verification' ], 20, 1 );
	}

	public static function is_instructor( int $id ): bool {
		return Pool_Schema::INSTRUCTOR_TYPE === Venue::listing_type( $id );
	}

	public static function signup_url(): string {
		$page = get_page_by_path( self::SIGNUP_PAGE );
		return $page ? (string) get_permalink( $page ) : home_url( '/' . self::SIGNUP_PAGE . '/' );
	}

	/* -------------------------------------------------------------- save */

	public static function on_save( $post_id ): void {
		$post_id = (int) $post_id;
		if ( ! self::is_instructor( $post_id ) ) {
			return;
		}
		Venue::flush_cache( $post_id );
		self::coarsen_location( $post_id );
	}

	/**
	 * Keep only "City, ST" for an instructor's location and map pin.
	 *
	 * @return string The stored location label ('' when it could not be read as a US city).
	 */
	public static function coarsen_location( int $post_id ): string {
		global $wpdb;
		$raw = (string) get_post_meta( $post_id, '_job_location', true );
		$parts = self::city_state( $raw );
		if ( ! $parts ) {
			return '';
		}
		$label = $parts['city'] . ', ' . $parts['state'];
		update_post_meta( $post_id, '_ppn_city', $parts['city'] );
		update_post_meta( $post_id, '_ppn_state', $parts['state'] );
		delete_post_meta( $post_id, '_ppn_street' );
		delete_post_meta( $post_id, '_ppn_postal_code' );
		if ( $label === $raw ) {
			return $label;
		}
		update_post_meta( $post_id, '_job_location', $label );
		$table = $wpdb->prefix . 'mylisting_locations';
		$wpdb->delete( $table, [ 'listing_id' => $post_id ] );
		$geo = Geocoder::geocode( $label );
		if ( isset( $geo['lat'], $geo['lng'] ) ) {
			$wpdb->insert( $table, [ 'listing_id' => $post_id, 'address' => $label, 'lat' => round( (float) $geo['lat'], 4 ), 'lng' => round( (float) $geo['lng'], 4 ) ] );
		}
		Venue::flush_cache( $post_id );
		return $label;
	}

	/**
	 * City and state from a typed location: "Sioux Falls, SD", "Sioux Falls, South Dakota",
	 * or a full street address.
	 *
	 * @return array{city:string,state:string}|null
	 */
	public static function city_state( string $raw ): ?array {
		$parts = Address::parse( $raw );
		if ( '' !== $parts['city'] && '' !== $parts['state'] ) {
			return [ 'city' => $parts['city'], 'state' => $parts['state'] ];
		}
		$pieces = array_values( array_filter( array_map( 'trim', explode( ',', preg_replace( '/,?\s*(USA|United States)$/i', '', $raw ) ) ), 'strlen' ) );
		if ( count( $pieces ) >= 2 ) {
			$state = Address::state_abbr( (string) end( $pieces ) ) ?: ( isset( Address::STATES[ strtoupper( (string) end( $pieces ) ) ] ) ? strtoupper( (string) end( $pieces ) ) : '' );
			$city = $pieces[ count( $pieces ) - 2 ];
			if ( $state && ! preg_match( '/\d/', $city ) ) {
				return [ 'city' => $city, 'state' => $state ];
			}
		}
		return null;
	}

	/* ------------------------------------------------------ credentials */

	/** @return array<int,array{slug:string,name:string,verified:string}> verified = date checked or '' */
	public static function credentials( int $id ): array {
		$verified = get_post_meta( $id, self::VERIFIED_META, true );
		$verified = is_array( $verified ) ? $verified : [];
		$out = [];
		$terms = wp_get_object_terms( $id, 'instructor-credential' );
		foreach ( is_array( $terms ) ? $terms : [] as $term ) {
			$out[] = [ 'slug' => $term->slug, 'name' => html_entity_decode( $term->name, ENT_QUOTES ), 'verified' => (string) ( $verified[ $term->slug ] ?? '' ) ];
		}
		return $out;
	}

	public static function has_verified_credential( int $id ): bool {
		foreach ( self::credentials( $id ) as $c ) {
			if ( $c['verified'] ) {
				return true;
			}
		}
		return false;
	}

	public static function meta_box( \WP_Post $post ): void {
		if ( ! self::is_instructor( (int) $post->ID ) || ! current_user_can( Moderation::CAP ) ) {
			return;
		}
		add_meta_box( 'ppn-instructor-verify', 'Credential verification', static function () use ( $post ) {
			wp_nonce_field( self::NONCE, self::NONCE );
			$creds = self::credentials( (int) $post->ID );
			if ( ! $creds ) {
				echo '<p>No certifications listed on this profile.</p>';
				return;
			}
			echo '<p class="description">Tick a certification only after checking it with the issuing organization (for PBIA levels, the PBIA instructor directory or the PBIA office). Ticked certifications show a Verified badge.</p>';
			foreach ( $creds as $c ) {
				printf(
					'<p><label><input type="checkbox" name="ppn_verified[]" value="%s"%s> %s</label>%s</p>',
					esc_attr( $c['slug'] ),
					checked( '' !== $c['verified'], true, false ),
					esc_html( $c['name'] ),
					$c['verified'] ? ' <span class="description">(checked ' . esc_html( $c['verified'] ) . ')</span>' : ''
				);
			}
			$note = (string) get_post_meta( $post->ID, '_ppn_credentials_note', true );
			echo '<p><label for="ppn-cred-note">How it was checked</label><textarea id="ppn-cred-note" name="ppn_credentials_note" rows="2" style="width:100%">' . esc_textarea( $note ) . '</textarea></p>';
		}, null, 'side', 'high' );
	}

	public static function save_verification( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( Moderation::CAP ) || ! self::is_instructor( $post_id ) ) {
			return;
		}
		$ticked = isset( $_POST['ppn_verified'] ) && is_array( $_POST['ppn_verified'] ) ? array_map( 'sanitize_title', wp_unslash( $_POST['ppn_verified'] ) ) : [];
		self::set_verified( $post_id, $ticked );
		update_post_meta( $post_id, '_ppn_credentials_note', isset( $_POST['ppn_credentials_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ppn_credentials_note'] ) ) : '' );
	}

	/** Mark exactly these credential slugs verified (keeping the original check date). */
	public static function set_verified( int $post_id, array $slugs ): void {
		$have = wp_list_pluck( self::credentials( $post_id ), 'slug' );
		$old = get_post_meta( $post_id, self::VERIFIED_META, true );
		$old = is_array( $old ) ? $old : [];
		$new = [];
		foreach ( array_intersect( $slugs, $have ) as $slug ) {
			$new[ $slug ] = $old[ $slug ] ?? wp_date( 'Y-m-d' );
		}
		update_post_meta( $post_id, self::VERIFIED_META, $new );
	}

	/* ------------------------------------------------------------ output */

	/** @return int[] venue IDs the instructor teaches at (published only) */
	public static function venues( int $id ): array {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT r.parent_listing_id FROM {$wpdb->prefix}mylisting_relations r
			 JOIN {$wpdb->posts} p ON p.ID = r.parent_listing_id AND p.post_status = 'publish'
			 WHERE r.child_listing_id = %d AND r.field_key = %s ORDER BY r.item_order",
			$id,
			Pool_Schema::INSTRUCTOR_VENUE_FIELD
		) ) );
	}

	private static function credential_list( int $id ): string {
		$items = '';
		foreach ( self::credentials( $id ) as $c ) {
			$items .= '<li>' . esc_html( $c['name'] ) . ( $c['verified'] ? ' <span class="ppn-badge">Verified</span>' : '' ) . '</li>';
		}
		return $items ? '<ul class="ppn-creds">' . $items . '</ul>' : '';
	}

	public static function details( $atts = [] ): string {
		$id = isset( $atts['id'] ) ? absint( $atts['id'] ) : (int) get_the_ID();
		$v = $id ? Venue::get( $id ) : null;
		if ( ! $v || ! self::is_instructor( $id ) ) {
			return Display::EMPTY;
		}
		$rows = [];
		$creds = self::credential_list( $id );
		if ( $creds ) {
			$unchecked = in_array( '', wp_list_pluck( self::credentials( $id ), 'verified' ), true );
			$rows['Certifications'] = $creds . ( $unchecked ? '<span class="ppn-fineprint">Listed by the instructor. A Verified badge means PlayPoolNation checked it.</span>' : '' );
		}
		$years = $v->field( 'years-teaching' );
		if ( '' !== $years && ctype_digit( $years ) && (int) $years > 0 ) {
			$rows['Teaching'] = esc_html( sprintf( _n( '%d year', '%d years', (int) $years, 'playpoolnation-core' ), (int) $years ) );
		}
		foreach ( [ 'lesson-focus' => 'Teaches', 'game-type' => 'Games', 'lesson-format' => 'Lessons' ] as $tax => $label ) {
			$names = $v->term_names( $tax );
			if ( $names ) {
				$rows[ $label ] = esc_html( Format::join_list( $names ) );
			}
		}
		$rate = Format::money( $v->field( 'lesson-rate' ) );
		$notes = $v->field( 'rate-notes' );
		if ( $rate || '' !== $notes ) {
			$rows['Rates'] = ( $rate ? '<strong>' . esc_html( $rate ) . '</strong> per hour' : '' ) . ( '' !== $notes ? ( $rate ? '<br>' : '' ) . nl2br( esc_html( $notes ) ) : '' );
		}
		if ( $v->city_state() ) {
			$rows['Based in'] = esc_html( $v->city_state() );
		}
		$area = $v->field( 'service-area' );
		if ( '' !== $area ) {
			$rows['Travels to'] = esc_html( $area );
		}
		if ( ! $rows ) {
			return Display::EMPTY;
		}
		$out = '<dl class="ppn-dl">';
		foreach ( $rows as $label => $html ) {
			$out .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . $html . '</dd></div>';
		}
		return '<div class="ppn-section ppn-details">' . $out . '</dl></div>';
	}

	/** Sidebar note on an instructor profile: how it is maintained. */
	public static function trust( $atts = [] ): string {
		$id = (int) get_the_ID();
		if ( ! $id || ! self::is_instructor( $id ) ) {
			return Display::EMPTY;
		}
		$html = '<p class="ppn-checked">This profile is written and kept up to date by the instructor.</p>';
		if ( self::has_verified_credential( $id ) ) {
			$html .= '<p><span class="ppn-badge">Verified</span> Certifications marked Verified were checked by PlayPoolNation.</p>';
		}
		$html .= '<p class="ppn-claim">Teach pool? <a href="' . esc_url( self::signup_url() ) . '">Create your free profile</a></p>';
		return '<div class="ppn-section ppn-trust">' . $html . '</div>';
	}

	/** Venue page section: instructors who teach at this venue. */
	public static function at_venue( $atts = [] ): string {
		$venue_id = isset( $atts['id'] ) ? absint( $atts['id'] ) : (int) get_the_ID();
		if ( ! $venue_id || ! Venue::is_venue( $venue_id ) ) {
			return Display::EMPTY;
		}
		$ids = Play::children( $venue_id, Pool_Schema::INSTRUCTOR_VENUE_FIELD );
		if ( ! $ids ) {
			return Display::EMPTY;
		}
		Venue::prime( $ids );
		$rows = '';
		foreach ( $ids as $id ) {
			$v = Venue::get( $id );
			if ( ! $v ) {
				continue;
			}
			$verified = array_values( array_filter( self::credentials( $id ), static fn( $c ) => '' !== $c['verified'] ) );
			$meta = array_filter( [ Format::join_list( array_slice( $v->term_names( 'lesson-focus' ), 0, 3 ) ), Format::join_list( $v->term_names( 'lesson-format' ) ) ] );
			$rows .= '<li class="ppn-row">'
				. '<a class="ppn-row-title" href="' . esc_url( $v->url() ) . '">' . esc_html( $v->name() ) . '</a>'
				. ( $verified ? '<span class="ppn-row-when">' . esc_html( $verified[0]['name'] ) . ' <span class="ppn-badge">Verified</span></span>' : '' )
				. ( $meta ? '<span class="ppn-row-meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>' : '' )
				. '</li>';
		}
		return $rows ? '<div class="ppn-section ppn-instructors"><ul class="ppn-rows">' . $rows . '</ul></div>' : Display::EMPTY;
	}
}
