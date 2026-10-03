<?php
/**
 * Indexing hygiene: one sitemap, noindex for utility/placeholder/thin pages,
 * 301 redirects for changed URLs, and preview-domain protection.
 *
 * The SEO plugin (ThinkRank) stays in charge of titles, meta and its sitemap; this
 * class only feeds it exclusions through its public filters.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Seo {

	/** Region (state/city) pages need at least this many venues to be indexed. */
	public const MIN_REGION_VENUES = 2;

	/** Taxonomies used for filtering; their archives are thin and stay out of search. */
	public const FILTER_TAXONOMIES = [ 'table-size', 'table-brand', 'pool-pricing', 'league-org', 'pool-play', 'game-type', Pool_Schema::TAX_AMENITY ];

	public static function boot(): void {
		// ThinkRank's sitemap (/sitemap.xml) is the one listed in robots.txt; avoid a second, divergent one.
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_filter( 'thinkrank_sitemap_query_args', [ __CLASS__, 'sitemap_query_args' ], 20 );
		add_filter( 'thinkrank_sitemap_term_query_args', [ __CLASS__, 'sitemap_term_args' ], 20 );
		add_filter( 'wp_robots', [ __CLASS__, 'wp_robots' ], 50 );
		add_filter( 'thinkrank_robots_meta', [ __CLASS__, 'thinkrank_robots' ], 50 );
		add_action( 'send_headers', [ __CLASS__, 'preview_domain_header' ] );
		add_action( 'template_redirect', [ __CLASS__, 'redirects' ], 1 );
	}

	/** @return int[] */
	public static function noindex_page_ids(): array {
		return array_map( 'intval', (array) get_option( 'ppn_noindex_pages', [] ) );
	}

	public static function should_noindex(): bool {
		if ( is_search() || is_404() ) {
			return true;
		}
		if ( is_page() && in_array( (int) get_queried_object_id(), self::noindex_page_ids(), true ) ) {
			return true;
		}
		if ( is_singular( 'product' ) || is_post_type_archive( 'product' ) || is_tax( [ 'product_cat', 'product_tag' ] ) ) {
			return true;
		}
		if ( is_tax( self::FILTER_TAXONOMIES ) ) {
			return true;
		}
		// My Listing renders filter and location pages through the explore page.
		if ( Seo_Meta::is_filter_page() ) {
			return true;
		}
		if ( 'regions' === get_query_var( 'explore_tab' ) ) {
			$region = Seo_Meta::region_term();
			return ! $region || (int) $region->count < self::MIN_REGION_VENUES;
		}
		if ( is_tax( Pool_Schema::TAX_REGION ) ) {
			$term = get_queried_object();
			return $term instanceof \WP_Term && (int) $term->count < self::MIN_REGION_VENUES;
		}
		if ( is_singular( Pool_Schema::POST_TYPE ) && 'publish' !== get_post_status() ) {
			return true;
		}
		return false;
	}

	public static function wp_robots( array $robots ): array {
		if ( self::should_noindex() ) {
			$robots['noindex'] = true;
			$robots['follow'] = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	/**
	 * ThinkRank passes either a robots string or an array of directives.
	 *
	 * @param mixed $robots
	 * @return mixed
	 */
	public static function thinkrank_robots( $robots ) {
		if ( ! self::should_noindex() ) {
			return $robots;
		}
		if ( is_array( $robots ) ) {
			$robots = array_values( array_diff( $robots, [ 'index' ] ) );
			$robots[] = 'noindex';
			return array_values( array_unique( $robots ) );
		}
		return 'noindex, follow';
	}

	/** @param mixed $args */
	public static function sitemap_query_args( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		if ( isset( $args['post_type'] ) ) {
			$types = array_values( array_diff( (array) $args['post_type'], [ 'product', 'attachment', 'elementor_library', 'claim', 'ppn_suggestion' ] ) );
			$args['post_type'] = $types ?: [ 'page' ];
		}
		$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? [] ), self::noindex_page_ids() ) ) );
		return $args;
	}

	/** @param mixed $args */
	public static function sitemap_term_args( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		if ( isset( $args['taxonomy'] ) ) {
			$args['taxonomy'] = array_values( array_diff( (array) $args['taxonomy'], array_merge( self::FILTER_TAXONOMIES, [ 'product_cat', 'product_tag', 'product_brand' ] ) ) );
		}
		$thin = get_terms( [ 'taxonomy' => Pool_Schema::TAX_REGION, 'hide_empty' => false, 'fields' => 'id=>count' ] );
		if ( ! is_wp_error( $thin ) ) {
			$exclude = array_keys( array_filter( $thin, static fn( $c ) => (int) $c < self::MIN_REGION_VENUES ) );
			$args['exclude'] = array_values( array_unique( array_merge( (array) ( $args['exclude'] ?? [] ), $exclude ) ) );
		}
		return $args;
	}

	/** Hostinger's preview domain serves the same site; never let it be indexed. */
	public static function preview_domain_header(): void {
		$host = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) );
		$canonical = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( $host && $canonical && $host !== $canonical && 'www.' . $canonical !== $host ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}

	/** 301s for URLs that changed: /listing/<slug>, /region/<path>, and renamed pages. */
	public static function redirects(): void {
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		if ( '' === $path ) {
			return;
		}

		if ( preg_match( '#^listing/([^/]+)/?$#', $path, $m ) ) {
			$target = self::listing_by_slug( sanitize_title( $m[1] ) );
			if ( $target ) {
				wp_safe_redirect( get_permalink( $target ), 301, 'PlayPoolNation' );
				exit;
			}
		}

		if ( preg_match( '#^region/(.+?)/?$#', $path, $m ) ) {
			$slugs = array_map( 'sanitize_title', explode( '/', $m[1] ) );
			$term = get_term_by( 'slug', end( $slugs ), Pool_Schema::TAX_REGION );
			if ( $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					wp_safe_redirect( $link, 301, 'PlayPoolNation' );
					exit;
				}
			}
		}

		$map = (array) get_option( 'ppn_redirects', [] );
		if ( isset( $map[ $path ] ) ) {
			wp_safe_redirect( home_url( '/' . trim( $map[ $path ], '/' ) . '/' ), 301, 'PlayPoolNation' );
			exit;
		}
	}

	/** A listing by current slug or any previous slug (WordPress keeps `_wp_old_slug`). */
	public static function listing_by_slug( string $slug ): int {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_name = %s LIMIT 1",
			Pool_Schema::POST_TYPE,
			$slug
		) );
		if ( $id ) {
			return $id;
		}
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_wp_old_slug' AND pm.meta_value = %s AND p.post_type = %s AND p.post_status = 'publish' LIMIT 1",
			$slug,
			Pool_Schema::POST_TYPE
		) );
	}
}
