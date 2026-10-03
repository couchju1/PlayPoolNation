<?php
/**
 * "My Pool": a signed-in player's page.
 *
 * Signed out, it explains the page and shows the account sign-in / registration
 * form (WooCommerce's, as styled by the theme), which returns the player here.
 * Signed in, it shows places near the player's chosen area, their saved places
 * (My Listing bookmarks), and upcoming events at those places and nearby.
 *
 * The page is personal: it is never cached and never indexed.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Format;

defined( 'ABSPATH' ) || exit;

final class My_Pool {

	public const PAGE = 'my-pool';
	public const HOME_META = 'ppn_home';
	public const RADII = [ 10, 25, 50, 100 ];
	private const NONCE = 'ppn_my_pool';

	public static function boot(): void {
		add_shortcode( 'ppn_my_pool', [ __CLASS__, 'render' ] );
		add_action( 'admin_post_ppn_set_home', [ __CLASS__, 'handle_set_home' ] );
		add_action( 'admin_post_ppn_unsave', [ __CLASS__, 'handle_unsave' ] );
		add_action( 'template_redirect', [ __CLASS__, 'no_cache' ], 1 );
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'account_menu' ], 70 );
		add_filter( 'woocommerce_get_endpoint_url', [ __CLASS__, 'account_menu_url' ], 10, 2 );
		add_filter( 'woocommerce_login_redirect', [ __CLASS__, 'after_login' ], 20, 2 );
		add_filter( 'woocommerce_registration_redirect', [ __CLASS__, 'after_login' ], 20, 1 );
	}

	public static function page_url(): string {
		$page = get_page_by_path( self::PAGE );
		return $page ? (string) get_permalink( $page ) : home_url( '/' . self::PAGE . '/' );
	}

	public static function no_cache(): void {
		if ( ! is_page( self::PAGE ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'personal page' );
		nocache_headers();
	}

	/* -------------------------------------------------------- account */

	public static function account_menu( array $items ): array {
		return [ 'ppn-my-pool' => 'My Pool' ] + $items;
	}

	public static function account_menu_url( string $url, string $endpoint ): string {
		return 'ppn-my-pool' === $endpoint ? self::page_url() : $url;
	}

	/** Players who sign in on the account page land on My Pool; explicit redirects are kept. */
	public static function after_login( $redirect, $user = null ) {
		$account = function_exists( 'wc_get_page_permalink' ) ? untrailingslashit( (string) wc_get_page_permalink( 'myaccount' ) ) : '';
		$user = $user instanceof \WP_User ? $user : wp_get_current_user();
		if ( $user && $user->exists() && user_can( $user, 'edit_others_posts' ) ) {
			return $redirect; // Staff keep the normal account dashboard.
		}
		$target = untrailingslashit( strtok( (string) $redirect, '?#' ) ?: '' );
		return ( '' === $target || $target === $account ) ? self::page_url() : $redirect;
	}

	/* ------------------------------------------------------------ data */

	/** @return array{label:string,lat:float,lng:float,radius:int}|null */
	public static function home( int $user_id ): ?array {
		$home = get_user_meta( $user_id, self::HOME_META, true );
		if ( ! is_array( $home ) || ! isset( $home['lat'], $home['lng'] ) ) {
			return null;
		}
		return [
			'label'  => (string) ( $home['label'] ?? '' ),
			'lat'    => (float) $home['lat'],
			'lng'    => (float) $home['lng'],
			'radius' => in_array( (int) ( $home['radius'] ?? 25 ), self::RADII, true ) ? (int) $home['radius'] : 25,
		];
	}

	public static function set_home( int $user_id, string $label, float $lat, float $lng, int $radius ): bool {
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ( 0.0 === $lat && 0.0 === $lng ) ) {
			return false;
		}
		update_user_meta( $user_id, self::HOME_META, [
			'label'  => mb_substr( $label, 0, 120 ),
			'lat'    => round( $lat, 4 ),
			'lng'    => round( $lng, 4 ),
			'radius' => in_array( $radius, self::RADII, true ) ? $radius : 25,
		] );
		return true;
	}

	/**
	 * Published listings of one type within a radius, nearest first.
	 *
	 * @return array<int,float> listing ID => miles
	 */
	public static function nearby( float $lat, float $lng, int $radius, string $type, int $limit = 12 ): array {
		global $wpdb;
		$dlat = $radius / 69.0;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.listing_id AS id,
				MIN( 3959 * ACOS( LEAST( 1, COS( RADIANS( %f ) ) * COS( RADIANS( l.lat ) ) * COS( RADIANS( l.lng ) - RADIANS( %f ) ) + SIN( RADIANS( %f ) ) * SIN( RADIANS( l.lat ) ) ) ) ) AS miles
			 FROM {$wpdb->prefix}mylisting_locations l
			 JOIN {$wpdb->posts} p ON p.ID = l.listing_id AND p.post_status = 'publish' AND p.post_type = %s
			 JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_case27_listing_type' AND t.meta_value = %s
			 WHERE l.lat BETWEEN %f AND %f
			 GROUP BY l.listing_id
			 HAVING miles <= %d
			 ORDER BY miles ASC
			 LIMIT %d",
			$lat, $lng, $lat, Pool_Schema::POST_TYPE, $type, $lat - $dlat, $lat + $dlat, $radius, $limit
		) );
		$out = [];
		foreach ( $rows as $row ) {
			$out[ (int) $row->id ] = (float) $row->miles;
		}
		return $out;
	}

	/** @return int[] the user's saved (bookmarked) published listings of one type, most recently saved first */
	public static function saved( int $user_id, string $type ): array {
		$ids = get_user_meta( $user_id, '_case27_user_bookmarks', true );
		$ids = is_array( $ids ) ? array_reverse( array_values( array_unique( array_map( 'absint', array_filter( $ids ) ) ) ) ) : [];
		return array_values( array_filter( $ids, static fn( $id ) => 'publish' === get_post_status( $id ) && $type === Venue::listing_type( $id ) ) );
	}

	/** Upcoming events at the saved venues, plus events the user saved directly. */
	public static function saved_events( int $user_id, int $limit = 10 ): array {
		$ids = self::saved( $user_id, Pool_Schema::TOURNAMENT_TYPE );
		foreach ( self::saved( $user_id, Pool_Schema::VENUE_TYPE ) as $venue ) {
			$ids = array_merge( $ids, Play::children( $venue, Pool_Schema::TOURNAMENT_VENUE_FIELD ) );
		}
		return Play::upcoming_from_ids( array_values( array_unique( $ids ) ), $limit );
	}

	/* ---------------------------------------------------------- handlers */

	private static function back( string $msg, string $anchor = 'my-area' ): void {
		wp_safe_redirect( add_query_arg( 'ppn_msg', $msg, self::page_url() ) . '#' . $anchor );
		exit;
	}

	public static function handle_set_home(): void {
		$user = get_current_user_id();
		if ( ! $user || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
			self::back( 'expired' );
		}
		$radius = isset( $_POST['radius'] ) ? absint( $_POST['radius'] ) : 25;
		$label = isset( $_POST['area'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['area'] ) ) ) : '';
		$lat = isset( $_POST['lat'] ) && '' !== $_POST['lat'] ? (float) $_POST['lat'] : null;
		$lng = isset( $_POST['lng'] ) && '' !== $_POST['lng'] ? (float) $_POST['lng'] : null;
		if ( null !== $lat && null !== $lng ) {
			$ok = self::set_home( $user, $label ?: 'Your location', $lat, $lng, $radius );
		} else {
			$geo = '' !== $label ? Geocoder::geocode( $label ) : [];
			$ok = isset( $geo['lat'], $geo['lng'] ) && self::set_home( $user, (string) ( $geo['address'] ?? $label ), (float) $geo['lat'], (float) $geo['lng'], $radius );
		}
		self::back( $ok ? 'area' : 'area-unknown' );
	}

	public static function handle_unsave(): void {
		$user = get_current_user_id();
		$id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		if ( ! $user || ! $id || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
			self::back( 'expired', 'saved' );
		}
		if ( class_exists( '\MyListing\Src\Bookmarks' ) ) {
			\MyListing\Src\Bookmarks::remove( $id, $user );
		} else {
			$ids = array_diff( array_map( 'absint', (array) get_user_meta( $user, '_case27_user_bookmarks', true ) ), [ $id ] );
			update_user_meta( $user, '_case27_user_bookmarks', array_values( $ids ) );
		}
		self::back( 'removed', 'saved' );
	}

	/* ------------------------------------------------------------ output */

	private static function message(): string {
		$msg = isset( $_GET['ppn_msg'] ) ? sanitize_key( wp_unslash( $_GET['ppn_msg'] ) ) : '';
		$texts = [
			'area'         => [ 'ok', 'Your area is saved.' ],
			'area-unknown' => [ 'error', 'We could not find that place. Try a city and state, or a ZIP code.' ],
			'removed'      => [ 'ok', 'Removed from your saved places.' ],
			'expired'      => [ 'error', 'That took too long. Please try again.' ],
		];
		if ( ! isset( $texts[ $msg ] ) ) {
			return '';
		}
		[ $type, $text ] = $texts[ $msg ];
		return '<p class="ppn-notice ppn-notice--' . esc_attr( $type ) . '" role="' . ( 'ok' === $type ? 'status' : 'alert' ) . '">' . esc_html( $text ) . '</p>';
	}

	public static function render(): string {
		if ( ! is_user_logged_in() ) {
			return self::signed_out();
		}
		$user = wp_get_current_user();
		$home = self::home( (int) $user->ID );
		$saved_ids = self::saved( (int) $user->ID, Pool_Schema::VENUE_TYPE );
		Venue::prime( $saved_ids );

		$name = $user->first_name ?: $user->display_name;
		$h = '<div class="ppn-pool">' . self::message();
		$h .= '<div class="ppn-pool-head"><p class="ppn-pool-hello">Hi ' . esc_html( $name ) . '.</p>'
			. '<nav class="ppn-pool-account" aria-label="Account"><a href="' . esc_url( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'edit-account' ) : admin_url( 'profile.php' ) ) . '">Profile</a>'
			. '<a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Sign out</a></nav></div>';

		$h .= self::area_section( $home );
		$h .= self::nearby_section( $home, $saved_ids );
		$h .= self::saved_section( $saved_ids, $home );
		$h .= self::events_section( (int) $user->ID, $home );
		return $h . '</div>';
	}

	private static function signed_out(): string {
		$h = '<div class="ppn-pool ppn-pool--out">';
		$h .= '<div class="ppn-pool-intro"><h2>Your pool, in one place</h2><ul class="ppn-pool-benefits">'
			. '<li><strong>Places near you.</strong> Pool halls and bars with tables around your area, nearest first.</li>'
			. '<li><strong>Your saved places.</strong> Tap <strong>Save</strong> on any venue to keep it here, with today\'s hours.</li>'
			. '<li><strong>What is coming up.</strong> Tournaments and events at your places and nearby.</li>'
			. '</ul><p class="ppn-fineprint">Free. We only use your email for your account.</p></div>';
		if ( function_exists( 'wc_get_template' ) ) {
			ob_start();
			if ( function_exists( 'woocommerce_output_all_notices' ) ) {
				woocommerce_output_all_notices();
			}
			wc_get_template( 'myaccount/form-login.php' );
			$h .= '<div class="ppn-pool-login" id="sign-in">' . ob_get_clean() . '</div>';
		} else {
			$h .= '<p><a class="ppn-button" href="' . esc_url( wp_login_url( self::page_url() ) ) . '">Sign in</a></p>';
		}
		return $h . '</div>';
	}

	private static function area_section( ?array $home ): string {
		$radius_opts = '';
		foreach ( self::RADII as $r ) {
			$radius_opts .= '<option value="' . $r . '"' . selected( $home ? $home['radius'] : 25, $r, false ) . '>' . $r . ' miles</option>';
		}
		$form = '<form class="ppn-form ppn-area-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ppn-area-form>'
			. '<input type="hidden" name="action" value="ppn_set_home">' . wp_nonce_field( self::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="lat" value="" data-ppn-lat><input type="hidden" name="lng" value="" data-ppn-lng>'
			. '<div class="ppn-form-row">'
			. '<p><label for="ppn-area">City, state or ZIP</label><input id="ppn-area" type="text" name="area" maxlength="120" autocomplete="address-level2" value="' . esc_attr( $home['label'] ?? '' ) . '" placeholder="For example: Sioux Falls, SD"></p>'
			. '<p><label for="ppn-radius">Within</label><select id="ppn-radius" name="radius">' . $radius_opts . '</select></p>'
			. '</div><p class="ppn-area-actions"><button type="submit" class="ppn-button">Save my area</button> '
			. '<button type="button" class="ppn-button ppn-button--ghost" data-ppn-locate>Use my location</button>'
			. '<span class="ppn-near-me-msg" role="status" aria-live="polite"></span></p></form>';
		if ( $home ) {
			return '<section class="ppn-pool-section" id="my-area" aria-labelledby="ppn-area-h"><h2 id="ppn-area-h" class="ppn-pool-h">Your area</h2>'
				. '<details class="ppn-area-edit"><summary><span>' . esc_html( $home['label'] ?: 'Your location' ) . ' · within ' . (int) $home['radius'] . ' miles</span> <span class="ppn-link">Change</span></summary>' . $form . '</details></section>';
		}
		return '<section class="ppn-pool-section" id="my-area" aria-labelledby="ppn-area-h"><h2 id="ppn-area-h" class="ppn-pool-h">Where do you play?</h2>'
			. '<p class="ppn-note">Set your area to see pool halls and events near you.</p>' . $form . '</section>';
	}

	private static function venue_row( Venue $v, ?float $miles, bool $saved, bool $removable ): string {
		$open = $v->open_status();
		$total = $v->total_tables();
		$meta = array_filter( [
			$v->city_state(),
			null !== $miles ? ( $miles < 0.1 ? 'here' : number_format( $miles, $miles < 10 ? 1 : 0 ) . ' mi' ) : '',
			$total ? $total . ' ' . _n( 'table', 'tables', $total, 'playpoolnation-core' ) : '',
			$v->primary_type(),
		] );
		$h = '<li class="ppn-place">'
			. '<div class="ppn-place-main"><a class="ppn-row-title" href="' . esc_url( $v->url() ) . '">' . esc_html( $v->name() ) . '</a>'
			. ( $saved && ! $removable ? ' <span class="ppn-saved" title="Saved">Saved</span>' : '' )
			. '<span class="ppn-row-meta">' . esc_html( implode( ' · ', $meta ) ) . '</span></div>';
		if ( $open['label'] ) {
			$h .= '<span class="ppn-fact ppn-fact--' . esc_attr( $open['state'] ) . '">' . esc_html( $open['label'] ) . '</span>';
		}
		if ( $removable ) {
			$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ppn-unsave">'
				. '<input type="hidden" name="action" value="ppn_unsave"><input type="hidden" name="listing_id" value="' . esc_attr( (string) $v->id() ) . '">'
				. wp_nonce_field( self::NONCE, '_wpnonce', true, false )
				. '<button type="submit" class="ppn-link-button" aria-label="' . esc_attr( 'Remove ' . $v->name() . ' from saved places' ) . '">Remove</button></form>';
		}
		return $h . '</li>';
	}

	private static function nearby_section( ?array $home, array $saved_ids ): string {
		if ( ! $home ) {
			return '';
		}
		$near = self::nearby( $home['lat'], $home['lng'], $home['radius'], Pool_Schema::VENUE_TYPE, 12 );
		Venue::prime( array_keys( $near ) );
		$map = Display::explore_url( [ 'type' => Pool_Schema::VENUE_TYPE, 'search_location' => $home['label'] ?: 'Your location', 'lat' => (string) $home['lat'], 'lng' => (string) $home['lng'], 'proximity' => (string) $home['radius'], 'sort' => 'nearby' ] );
		$h = '<section class="ppn-pool-section" id="near-you" aria-labelledby="ppn-near-h"><div class="ppn-pool-sechead"><h2 id="ppn-near-h" class="ppn-pool-h">Places near you</h2>'
			. '<a href="' . esc_url( $map ) . '">See them on the map</a></div>';
		if ( ! $near ) {
			return $h . '<p class="ppn-note">No places within ' . (int) $home['radius'] . ' miles yet. Try a wider area, or <a href="' . esc_url( home_url( '/add-a-venue/' ) ) . '">add a place we are missing</a>.</p></section>';
		}
		$rows = '';
		foreach ( $near as $id => $miles ) {
			$v = Venue::get( $id );
			if ( $v ) {
				$rows .= self::venue_row( $v, $miles, in_array( $id, $saved_ids, true ), false );
			}
		}
		return $h . '<ul class="ppn-places">' . $rows . '</ul></section>';
	}

	private static function saved_section( array $saved_ids, ?array $home ): string {
		$h = '<section class="ppn-pool-section" id="saved" aria-labelledby="ppn-saved-h"><div class="ppn-pool-sechead"><h2 id="ppn-saved-h" class="ppn-pool-h">Your saved places</h2></div>';
		if ( ! $saved_ids ) {
			return $h . '<p class="ppn-note">Nothing saved yet. Open any venue and tap <strong>Save</strong> to keep it here. <a href="' . esc_url( Display::explore_url() ) . '">Find places</a></p></section>';
		}
		$rows = '';
		foreach ( $saved_ids as $id ) {
			$v = Venue::get( $id );
			if ( ! $v ) {
				continue;
			}
			$miles = null;
			$loc = $v->location();
			if ( $home && $loc ) {
				$miles = Helpers\Dedupe::distance_m( $home['lat'], $home['lng'], (float) $loc->lat, (float) $loc->lng ) / 1609.344;
			}
			$rows .= self::venue_row( $v, $miles, true, true );
		}
		return $h . '<ul class="ppn-places">' . $rows . '</ul></section>';
	}

	/** @param array<int,array> $events from Play::upcoming_from_ids */
	private static function event_rows( array $events ): string {
		$rows = '';
		foreach ( $events as $t ) {
			$venue = (int) get_post_meta( $t['id'], '_ppn_venue_id', true );
			$meta = array_filter( [
				'Tournament' !== $t['type'] ? $t['type'] : '',
				Format::join_list( $t['games'] ),
				Format::money( $t['entry_fee'] ) ? Format::money( $t['entry_fee'] ) . ' entry' : '',
				Format::money( $t['added_money'] ) ? Format::money( $t['added_money'] ) . ' added' : '',
			] );
			$rows .= '<li class="ppn-row">'
				. '<time class="ppn-date" datetime="' . esc_attr( $t['when']->format( 'c' ) ) . '"><span>' . esc_html( $t['when']->format( 'M' ) ) . '</span><strong>' . esc_html( $t['when']->format( 'j' ) ) . '</strong></time>'
				. '<a class="ppn-row-title" href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['name'] ) . '</a>'
				. '<span class="ppn-row-when">' . esc_html( $t['when']->format( 'l' ) . ', ' . Format::clock( (int) $t['when']->format( 'G' ) * 60 + (int) $t['when']->format( 'i' ) ) )
				. ( $venue ? ' at <a href="' . esc_url( get_permalink( $venue ) ) . '">' . esc_html( get_the_title( $venue ) ) . '</a>' : '' ) . '</span>'
				. ( $meta ? '<span class="ppn-row-meta">' . esc_html( implode( ', ', $meta ) ) . '</span>' : '' )
				. '</li>';
		}
		return $rows ? '<ul class="ppn-rows ppn-rows--dated">' . $rows . '</ul>' : '';
	}

	private static function events_section( int $user_id, ?array $home ): string {
		$mine = self::saved_events( $user_id, 10 );
		$near = [];
		if ( $home ) {
			$ids = array_diff( array_keys( self::nearby( $home['lat'], $home['lng'], $home['radius'], Pool_Schema::TOURNAMENT_TYPE, 60 ) ), wp_list_pluck( $mine, 'id' ) );
			$near = Play::upcoming_from_ids( array_values( $ids ), 8 );
		}
		$h = '<section class="ppn-pool-section" id="events" aria-labelledby="ppn-events-h"><div class="ppn-pool-sechead"><h2 id="ppn-events-h" class="ppn-pool-h">Coming up</h2>'
			. '<a href="' . esc_url( home_url( '/events/' ) ) . '">All events</a></div>';
		$h .= '<h3 class="ppn-subhead">At your saved places</h3>';
		$h .= self::event_rows( $mine ) ?: '<p class="ppn-note">No upcoming events at your saved places yet.</p>';
		if ( $home ) {
			$h .= '<h3 class="ppn-subhead">Near you</h3>';
			$h .= self::event_rows( $near ) ?: '<p class="ppn-note">No other events posted within ' . (int) $home['radius'] . ' miles yet. Know of one? <a href="' . esc_url( Events::page_url() ) . '">Post it</a>.</p>';
		}
		return $h . '</section>';
	}
}
