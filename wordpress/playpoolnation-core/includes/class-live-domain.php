<?php
/**
 * The site was built on Hostinger's temporary domain, and some output stored
 * then still linked there. Stored values were repaired once (Install migration
 * 2026_10_24_live_domain); `wp search-replace` found nothing left afterwards.
 * Links are not rewritten at render time: tests/integration/run.php fetches the
 * homepage, /places/ and a venue page and fails on any temporary-domain link.
 *
 * The homepage "Rooms players travel for" rail is My Listing's listing-feed
 * widget. The theme marks it static, so Elementor's element cache served the
 * cards (and their temporary-domain links) it rendered weeks ago. It now renders
 * on every request, with get_permalink() and get_term_link() for the live site.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Html_Fix;

defined( 'ABSPATH' ) || exit;

final class Live_Domain {

	/** Elementor widgets that show live listing data and must not come from its element cache. */
	private const LIVE_WIDGETS = [ 'case27-listing-feed-widget' ];

	public static function boot(): void {
		add_filter( 'elementor/element/is_dynamic_content', [ __CLASS__, 'render_live' ], 10, 2 );
	}

	public static function origin(): string {
		$parts = wp_parse_url( home_url() );
		return ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	}

	/**
	 * @param bool  $dynamic
	 * @param array $raw_data Elementor element data.
	 */
	public static function render_live( $dynamic, $raw_data ): bool {
		return $dynamic || in_array( $raw_data['widgetType'] ?? '', self::LIVE_WIDGETS, true );
	}

	/**
	 * Replace the temporary domain in stored content, settings and metadata, and
	 * delete caches that hold it (they rebuild from the fixed data).
	 *
	 * @return array<string,int> table => rows changed or deleted
	 */
	public static function repair(): array {
		global $wpdb;
		$origin = self::origin();
		if ( preg_match( '#' . Html_Fix::TEMP_HOST . '#i', $origin ) ) {
			return []; // Still running on the temporary domain; nothing to repair.
		}
		$like = '%' . $wpdb->esc_like( 'hostingersite.com' ) . '%';
		$log = [ 'posts' => 0, 'postmeta' => 0, 'termmeta' => 0, 'options' => 0, 'caches deleted' => 0 ];

		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_content LIKE %s OR post_excerpt LIKE %s", $like, $like ) ) as $row ) {
			$wpdb->update( $wpdb->posts, [
				'post_content' => Html_Fix::live_domain( $row->post_content, $origin )[0],
				'post_excerpt' => Html_Fix::live_domain( $row->post_excerpt, $origin )[0],
			], [ 'ID' => $row->ID ] );
			clean_post_cache( (int) $row->ID );
			$log['posts']++;
		}

		foreach ( [ 'postmeta' => [ $wpdb->postmeta, 'post_id' ], 'termmeta' => [ $wpdb->termmeta, 'term_id' ] ] as $name => [ $table, $object ] ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, {$object} AS object_id, meta_key, meta_value FROM {$table} WHERE meta_value LIKE %s", $like ) );
			foreach ( $rows as $row ) {
				if ( self::is_cache_key( $row->meta_key ) ) {
					$wpdb->delete( $table, [ 'meta_id' => $row->meta_id ] );
					$log['caches deleted']++;
				} else {
					$wpdb->update( $table, [ 'meta_value' => self::fix_stored( $row->meta_value, $origin ) ], [ 'meta_id' => $row->meta_id ] );
					$log[ $name ]++;
				}
				wp_cache_delete( (int) $row->object_id, 'postmeta' === $name ? 'post_meta' : 'term_meta' );
			}
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE %s", $like ) );
		foreach ( $rows as $row ) {
			if ( in_array( $row->option_name, [ 'home', 'siteurl' ], true ) ) {
				continue;
			}
			if ( self::is_cache_key( $row->option_name ) ) {
				$wpdb->delete( $wpdb->options, [ 'option_name' => $row->option_name ] );
				$log['caches deleted']++;
			} else {
				$wpdb->update( $wpdb->options, [ 'option_value' => self::fix_stored( $row->option_value, $origin ) ], [ 'option_name' => $row->option_name ] );
				$log['options']++;
			}
		}
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		self::clear_page_caches();
		return $log;
	}

	/** Transients, Elementor's rendered output, CSS and asset lists, and keys named *_cache. */
	private static function is_cache_key( string $key ): bool {
		return (bool) preg_match( '#^_(site_)?transient_|^_elementor_(element_cache|css|page_assets)$|_cache$#', $key );
	}

	private static function fix_stored( string $raw, string $origin ): string {
		$value = maybe_unserialize( $raw );
		if ( is_string( $value ) ) {
			return Html_Fix::live_domain( $raw, $origin )[0];
		}
		return maybe_serialize( Html_Fix::live_domain_deep( $value, $origin ) );
	}

	/** Elementor's element and CSS caches, then the page cache. */
	public static function clear_page_caches(): void {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		do_action( 'litespeed_purge_all' );
	}
}
