<?php
/**
 * Address components, state > city region terms, and readable venue slugs.
 *
 * Region terms are only created for places where a venue exists, so every
 * /places/<state>/<city>/ page has real inventory behind it.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Address;
use PlayPoolNation\Core\Helpers\Format;

defined( 'ABSPATH' ) || exit;

final class Locations {

	public const REGION_BASE = 'places';

	public static function boot(): void {
		add_filter( 'register_taxonomy_args', [ __CLASS__, 'region_args' ], 20, 2 );
		add_action( 'mylisting/admin/save-listing-data', [ __CLASS__, 'on_save' ], 70, 1 );
		add_action( 'mylisting/submission/save-listing-data', [ __CLASS__, 'on_save' ], 70, 1 );
		add_action( 'transition_post_status', [ __CLASS__, 'on_publish' ], 20, 3 );
	}

	public static function region_args( array $args, string $taxonomy ): array {
		if ( Pool_Schema::TAX_REGION === $taxonomy ) {
			$args['hierarchical'] = true;
			$args['rewrite'] = [ 'slug' => self::REGION_BASE, 'with_front' => false, 'hierarchical' => true ];
		}
		return $args;
	}

	public static function on_save( $post_id ): void {
		$post_id = (int) $post_id;
		if ( Venue::is_venue( $post_id ) ) {
			Venue::flush_cache( $post_id );
			self::sync( $post_id );
		}
	}

	public static function on_publish( string $new, string $old, \WP_Post $post ): void {
		if ( 'publish' === $new && 'publish' !== $old && Pool_Schema::POST_TYPE === $post->post_type && Venue::is_venue( (int) $post->ID ) ) {
			self::sync( (int) $post->ID );
			self::maybe_set_slug( (int) $post->ID );
		}
	}

	/** Store address parts and assign state + city region terms. */
	public static function sync( int $post_id ): void {
		$venue = Venue::get( $post_id );
		if ( ! $venue ) {
			return;
		}
		$parts = Address::parse( $venue->address() );
		if ( '' === $parts['state'] ) {
			return; // Not a parseable US address; leave regions alone rather than guess.
		}
		foreach ( [ 'street', 'city', 'state', 'postal_code', 'country' ] as $key ) {
			update_post_meta( $post_id, '_ppn_' . $key, $parts[ $key ] );
		}
		update_post_meta( $post_id, '_ppn_state_name', Address::state_name( $parts['state'] ) );

		$state_term = self::ensure_term( Address::state_name( $parts['state'] ), 0 );
		$ids = $state_term ? [ $state_term ] : [];
		if ( $state_term && '' !== $parts['city'] ) {
			$city_term = self::ensure_term( $parts['city'], $state_term );
			if ( $city_term ) {
				$ids[] = $city_term;
			}
		}
		if ( $ids ) {
			wp_set_object_terms( $post_id, $ids, Pool_Schema::TAX_REGION );
			delete_transient( self::COUNT_CACHE );
		}
	}

	public const COUNT_CACHE = 'ppn_region_venue_counts';

	/**
	 * Published venues per region term. Term counts also include events that copy
	 * their venue's region, so place counts and thin-page checks use this instead.
	 *
	 * @return array<int,int> term_id => venues
	 */
	public static function venue_counts(): array {
		$cached = get_transient( self::COUNT_CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT tt.term_id, COUNT(DISTINCT p.ID) AS n
			 FROM {$wpdb->term_taxonomy} tt
			 JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
			 JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = %s AND p.post_status = 'publish'
			 JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_case27_listing_type' AND m.meta_value = %s
			 WHERE tt.taxonomy = %s
			 GROUP BY tt.term_id",
			Pool_Schema::POST_TYPE,
			Pool_Schema::VENUE_TYPE,
			Pool_Schema::TAX_REGION
		) );
		$counts = [];
		foreach ( $rows as $row ) {
			$counts[ (int) $row->term_id ] = (int) $row->n;
		}
		set_transient( self::COUNT_CACHE, $counts, 6 * HOUR_IN_SECONDS );
		return $counts;
	}

	public static function venue_count( int $term_id ): int {
		return self::venue_counts()[ $term_id ] ?? 0;
	}

	/** Find or create a region term under a parent. Returns term ID or 0. */
	public static function ensure_term( string $name, int $parent ): int {
		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}
		$existing = get_terms( [
			'taxonomy'   => Pool_Schema::TAX_REGION,
			'name'       => $name,
			'parent'     => $parent,
			'hide_empty' => false,
			'fields'     => 'ids',
		] );
		if ( ! is_wp_error( $existing ) && $existing ) {
			return (int) $existing[0];
		}
		// Top-level state terms may exist from before the hierarchy (created without a parent check).
		if ( 0 === $parent ) {
			$term = get_term_by( 'name', $name, Pool_Schema::TAX_REGION );
			if ( $term && 0 === (int) $term->parent ) {
				return (int) $term->term_id;
			}
		}
		$args = [ 'parent' => $parent ];
		if ( $parent ) {
			$slug = Format::slugify( $name );
			// Keep city slugs readable; only disambiguate when the slug is taken elsewhere.
			if ( get_term_by( 'slug', $slug, Pool_Schema::TAX_REGION ) ) {
				$parent_term = get_term( $parent, Pool_Schema::TAX_REGION );
				$slug .= '-' . ( $parent_term && ! is_wp_error( $parent_term ) ? Address::state_abbr( $parent_term->name ) ?: $parent_term->slug : $parent );
				$slug = strtolower( $slug );
			}
			$args['slug'] = $slug;
		}
		$result = wp_insert_term( $name, Pool_Schema::TAX_REGION, $args );
		return is_wp_error( $result ) ? 0 : (int) $result['term_id'];
	}

	/** Give a venue a "name-city-st" slug once; WordPress keeps the old slug for redirects. */
	public static function maybe_set_slug( int $post_id ): bool {
		$venue = Venue::get( $post_id );
		if ( ! $venue || get_post_meta( $post_id, '_ppn_slug_set', true ) ) {
			return false;
		}
		$parts = $venue->address_parts();
		if ( '' === $parts['city'] || '' === $parts['state'] ) {
			return false;
		}
		$wanted = Format::venue_slug( $venue->name(), $parts['city'], $parts['state'] );
		if ( $wanted && $wanted !== $venue->post->post_name ) {
			$unique = wp_unique_post_slug( $wanted, $post_id, 'publish', Pool_Schema::POST_TYPE, 0 );
			wp_update_post( [ 'ID' => $post_id, 'post_name' => $unique ] );
		}
		update_post_meta( $post_id, '_ppn_slug_set', 1 );
		return true;
	}
}
