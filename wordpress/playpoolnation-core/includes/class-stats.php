<?php
/**
 * Live counts and location links.
 *
 * [ppn_stat type="venues|states|metros"]  A live number.
 * [ppn_metros]                             City links with live venue counts.
 * [ppn_states]                             State links with counts.
 *
 * Metro definitions live in the `ppn_metros` option: [ label, search_location, lat, lng, radius_miles ].
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Stats {

	private const CACHE = 'ppn_stats_v3';

	public static function boot(): void {
		add_shortcode( 'ppn_stat', [ __CLASS__, 'stat_shortcode' ] );
		add_shortcode( 'ppn_metros', [ __CLASS__, 'metros_shortcode' ] );
		add_shortcode( 'ppn_states', [ __CLASS__, 'states_shortcode' ] );
		foreach ( [ 'save_post_' . Pool_Schema::POST_TYPE, 'trashed_post', 'untrashed_post', 'deleted_post' ] as $hook ) {
			add_action( $hook, [ __CLASS__, 'flush' ] );
		}
		add_action( 'update_option_ppn_metros', [ __CLASS__, 'flush' ] );
	}

	public static function flush(): void {
		delete_transient( self::CACHE );
		delete_transient( Locations::COUNT_CACHE );
	}

	public static function stats(): array {
		$stats = get_transient( self::CACHE );
		if ( is_array( $stats ) ) {
			return $stats;
		}
		global $wpdb;
		$venues = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_case27_listing_type' AND m.meta_value = %s
			 WHERE p.post_type = %s AND p.post_status = 'publish'",
			Pool_Schema::VENUE_TYPE,
			Pool_Schema::POST_TYPE
		) );
		$counts = [];
		foreach ( (array) get_option( 'ppn_metros', [] ) as $m ) {
			$counts[] = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT l.listing_id) FROM {$wpdb->prefix}mylisting_locations l
				 JOIN {$wpdb->posts} p ON p.ID = l.listing_id AND p.post_status = 'publish' AND p.post_type = %s
				 JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_case27_listing_type' AND t.meta_value = %s
				 WHERE ( 3959 * ACOS( LEAST( 1, COS( RADIANS( %f ) ) * COS( RADIANS( l.lat ) ) * COS( RADIANS( l.lng ) - RADIANS( %f ) ) + SIN( RADIANS( %f ) ) * SIN( RADIANS( l.lat ) ) ) ) ) < %d",
				Pool_Schema::POST_TYPE,
				Pool_Schema::VENUE_TYPE,
				$m[2],
				$m[3],
				$m[2],
				$m[4]
			) );
		}
		$instructors = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_case27_listing_type' AND m.meta_value = %s
			 WHERE p.post_type = %s AND p.post_status = 'publish'",
			Pool_Schema::INSTRUCTOR_TYPE,
			Pool_Schema::POST_TYPE
		) );
		// States with at least one venue (event and instructor listings do not count).
		$states = array_filter( Locations::venue_counts(), static fn( $n, $term_id ) => $n > 0 && 0 === (int) wp_get_term_taxonomy_parent_id( $term_id, Pool_Schema::TAX_REGION ), ARRAY_FILTER_USE_BOTH );
		$stats = [
			'venues'       => $venues,
			'instructors'  => $instructors,
			'states'       => count( $states ),
			'metros'       => count( array_filter( $counts ) ),
			'metro_counts' => $counts,
		];
		set_transient( self::CACHE, $stats, 6 * HOUR_IN_SECONDS );
		return $stats;
	}

	public static function stat_shortcode( $atts ): string {
		$atts = shortcode_atts( [ 'type' => 'venues' ], (array) $atts );
		$stats = self::stats();
		return isset( $stats[ $atts['type'] ] ) && is_int( $stats[ $atts['type'] ] ) ? esc_html( number_format_i18n( $stats[ $atts['type'] ] ) ) : '';
	}

	/**
	 * Metros link to their city page (/places/<state>/<city>/) when it is indexable,
	 * so search engines can follow them; otherwise to the map around the metro.
	 */
	public static function metros_shortcode(): string {
		$stats = self::stats();
		$out = '<ul class="ppn-chips ppn-chips--links">';
		foreach ( (array) get_option( 'ppn_metros', [] ) as $i => $m ) {
			$n = $stats['metro_counts'][ $i ] ?? 0;
			if ( ! $n ) {
				continue;
			}
			$city = self::city_term( (string) $m[1] );
			$link = $city ? get_term_link( $city ) : '';
			if ( $city && ! is_wp_error( $link ) ) {
				// The city page lists that city only, so show its name and count rather than the metro's.
				$out .= sprintf( '<li><a href="%s">%s <span>%d</span></a></li>', esc_url( $link ), esc_html( html_entity_decode( $city->name, ENT_QUOTES ) ), Locations::venue_count( (int) $city->term_id ) );
				continue;
			}
			$url = Display::explore_url( [
				'type'            => Pool_Schema::VENUE_TYPE,
				'search_location' => $m[1],
				'lat'             => (string) $m[2],
				'lng'             => (string) $m[3],
				'proximity'       => (string) $m[4],
				'sort'            => 'nearby',
			] );
			$out .= sprintf( '<li><a href="%s">%s <span>%d</span></a></li>', esc_url( $url ), esc_html( $m[0] ), $n );
		}
		return $out . '</ul>';
	}

	/** The indexable city region term for "City, ST", or null. */
	public static function city_term( string $location ): ?\WP_Term {
		$parts = array_map( 'trim', explode( ',', $location ) );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		$state = get_terms( [ 'taxonomy' => Pool_Schema::TAX_REGION, 'name' => Helpers\Address::state_name( $parts[1] ), 'parent' => 0, 'hide_empty' => false ] );
		if ( is_wp_error( $state ) || ! $state ) {
			return null;
		}
		$city = get_terms( [ 'taxonomy' => Pool_Schema::TAX_REGION, 'name' => $parts[0], 'parent' => (int) $state[0]->term_id, 'hide_empty' => false ] );
		if ( is_wp_error( $city ) || ! $city || Locations::venue_count( (int) $city[0]->term_id ) < Seo::MIN_REGION_VENUES ) {
			return null;
		}
		return $city[0];
	}

	/** State links, with the cities that have enough venues for their own page. */
	public static function states_shortcode(): string {
		$out = '<ul class="ppn-states">';
		foreach ( get_terms( [ 'taxonomy' => Pool_Schema::TAX_REGION, 'hide_empty' => true, 'parent' => 0, 'orderby' => 'name' ] ) as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$out .= sprintf( '<li><a href="%s">%s<span>%d</span></a></li>', esc_url( $link ), esc_html( html_entity_decode( $term->name ) ), Locations::venue_count( (int) $term->term_id ) );
		}
		return $out . '</ul>';
	}
}
