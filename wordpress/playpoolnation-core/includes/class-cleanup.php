<?php
/**
 * Front-end source cleanup: generator tags, the SEO plugin's HTML comments, and the
 * WooCommerce cart drawer on pages that have no shop.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Cleanup {

	public static function boot(): void {
		// WordPress, and WooCommerce's tag which it appends to WordPress's (pages and feeds).
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		// Elementor's own setting (Elementor > Settings > Advanced > Generator Tag).
		add_filter( 'pre_option_elementor_meta_generator_tag', static fn() => '1' );
		add_filter( 'woocommerce_widget_cart_is_hidden', [ __CLASS__, 'hide_cart' ], 20 );
		add_action( 'ppn_markup', static fn() => Markup::add( [ __CLASS__, 'strip_thinkrank' ] ) );
	}

	/** The theme prints the cart drawer on every page; only shop pages use it. */
	public static function hide_cart( $hidden ): bool {
		return (bool) $hidden || ( ! is_admin() && ! Performance::needs_woocommerce() );
	}

	/**
	 * ThinkRank prints about 15 "<!-- ThinkRank … -->" comments per page and its own
	 * generator tag, with no hook to turn them off. Only those are removed; every other
	 * comment (LiteSpeed's cache note included) stays.
	 */
	public static function strip_thinkrank( string $html ): string {
		$html = (string) preg_replace( '#[ \t]*<!-- (?:/?ThinkRank\b|Search Engine Optimization by ThinkRank\b)(?:(?!-->).)*-->[ \t]*\R?#s', '', $html );
		return (string) preg_replace( '#[ \t]*<meta name="generator" content="ThinkRank[^"]*"\s*/?>[ \t]*\R?#', '', $html );
	}
}
