<?php
/**
 * Front-end weight: WooCommerce is only used for accounts and the owner
 * "list your venue" flow, so its shop and payment assets are dropped everywhere
 * else. Payment plugins (WooPayments, PayPal Payments, Stripe) and WooCommerce
 * Blocks are matched by source folder, since their handle names change between
 * versions. WooPay's direct checkout alone pulled React and ~18 wp-* packages
 * onto every page. The homepage pool game loads only when the visitor presses Play.
 *
 * Google Maps (the API and the theme's google-maps.js) loads with the page only on
 * the venue explore page (/places/, state, city and metro pages). Elsewhere
 * assets/ppn.js loads it when a map comes into view, a location search is used,
 * or a quick view (which shows a map) is opened. Contact Form 7's script and
 * styles load only on pages that show a form.
 *
 * The theme prints its carousel scripts with `async`, so they ran in whatever
 * order they arrived: the homepage rail's listing-feed-carousel.js sometimes ran
 * before owl.js or background-carousel.js and the rail never started. They now
 * run deferred, in the order printed.
 *
 * ThinkRank loads breadcrumbs.css on every page while its breadcrumbs setting is
 * on (which also gives Google the BreadcrumbList data); the site shows no visible
 * breadcrumbs, so the stylesheet loads only where they are printed.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Performance {

	private const WC_SCRIPTS = [ 'woocommerce', 'wc-js-cookie', 'wc-cart-fragments', 'sourcebuster-js', 'wc-order-attribution', 'wc-add-to-cart', 'wc-add-to-cart-variation', 'wc-single-product', 'wc-jquery-blockui' ];
	private const WC_STYLES  = [ 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general' ];

	/** Asset sources that only checkout and the owner flow need. */
	private const PAYMENT_SOURCES = [ '/plugins/woocommerce-payments/', '/plugins/woocommerce-paypal-payments/', '/plugins/woocommerce-gateway-stripe/', '/woocommerce/assets/client/blocks/', '/woocommerce/packages/woocommerce-blocks/', 'js.stripe.com', 'paypal.com/sdk' ];
	private const PAYMENT_HANDLE = '/^(wcpay|WCPAY|woopay|ppcp|paypal|stripe|wc-blocks|wc-stripe)/';

	/** Accessibility widget scripts that should never block rendering. */
	private const DEFER_SOURCES = [ '/plugins/pojo-accessibility/' ];

	/** Google's Maps API and the theme's map script, in the order they must run. */
	private const MAP_HANDLES = [ 'google-maps', 'mylisting-google-maps' ];

	/** Theme scripts that must run in the order printed (listing-feed-carousel.js uses the other two). */
	private const ORDERED_SCRIPTS = [ 'mylisting-background-carousel', 'mylisting-owl', 'mylisting-listing-feed-carousel' ];

	/** @var array<string,string> handle => src of map scripts held back on this page */
	private static array $held_maps = [];

	/** Pages that still need WooCommerce on the front end (slugs). */
	private const WC_PAGES = [ 'list-your-venue', 'claim-listing', 'claim-your-listing', 'my-pool', 'sign-in', 'join' ];

	public static function boot(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'trim_woocommerce' ], 99 );
		// Some of these are enqueued late (while the page renders), so trim again before the footer prints.
		add_action( 'wp_print_footer_scripts', [ __CLASS__, 'trim_woocommerce' ], 1 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'defer_scripts' ], 999 );
		add_action( 'wp_print_footer_scripts', [ __CLASS__, 'defer_scripts' ], 1 );
		// The pool game's iframe loads the whole game; it now waits for the Play button.
		add_action( 'ppn_markup', static fn() => Markup::add( [ Helpers\Html_Fix::class, 'pool_game_on_click' ] ) );
		add_filter( 'script_loader_tag', [ __CLASS__, 'hold_maps' ], 20, 3 );
		add_filter( 'script_loader_tag', [ __CLASS__, 'keep_order' ], 999, 2 );
		add_action( 'wp_print_footer_scripts', [ __CLASS__, 'print_held_maps' ], 20 );
		add_filter( 'wpcf7_load_js', [ __CLASS__, 'cf7_assets' ] );
		add_filter( 'wpcf7_load_css', [ __CLASS__, 'cf7_assets' ] );
		add_filter( 'do_shortcode_tag', [ __CLASS__, 'cf7_shortcode_rendered' ], 10, 2 );
		add_filter( 'render_block_contact-form-7/contact-form-selector', [ __CLASS__, 'cf7_rendered' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'drop_breadcrumb_style' ], 99 );
		add_action( 'thinkrank_breadcrumbs', [ __CLASS__, 'breadcrumbs_printed' ], 0 );
	}

	/** The venue explore page and its state, city and metro views draw a map straight away. */
	public static function maps_on_load(): bool {
		$explore = (int) get_option( 'options_general_explore_listings_page' );
		return $explore && is_page( $explore );
	}

	/**
	 * Print map scripts' inline parts but not the scripts themselves; their addresses
	 * go to print_held_maps() for assets/ppn.js to load when needed.
	 *
	 * @param string $tag
	 * @param string $handle
	 * @param string $src
	 */
	public static function hold_maps( $tag, $handle, $src ): string {
		if ( is_admin() || ! in_array( $handle, self::MAP_HANDLES, true ) || self::maps_on_load() ) {
			return (string) $tag;
		}
		self::$held_maps[ $handle ] = (string) $src;
		return (string) preg_replace( '#<script\b[^>]*\bsrc=[^>]*>\s*</script>\s*#', '', (string) $tag );
	}

	/**
	 * @param string $tag
	 * @param string $handle
	 */
	public static function keep_order( $tag, $handle ): string {
		return in_array( $handle, self::ORDERED_SCRIPTS, true ) ? Helpers\Html_Fix::without_async( (string) $tag ) : (string) $tag;
	}

	public static function print_held_maps(): void {
		$srcs = [];
		foreach ( self::MAP_HANDLES as $handle ) {
			if ( ! empty( self::$held_maps[ $handle ] ) ) {
				$srcs[] = self::$held_maps[ $handle ];
			}
		}
		if ( $srcs ) {
			printf( '<script type="application/json" id="ppn-maps">%s</script>' . "\n", wp_json_encode( $srcs, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) );
		}
	}

	/** Contact Form 7 assets with the page only where its content has a form. */
	public static function cf7_assets( $load ): bool {
		return $load && self::has_cf7_form();
	}

	private static function has_cf7_form(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$id = (int) get_queried_object_id();
		$content = (string) get_post_field( 'post_content', $id ) . (string) get_post_meta( $id, '_elementor_data', true );
		// The shortcode, the block, and Elementor widgets that wrap them all name contact-form-7.
		return str_contains( $content, 'contact-form-7' ) || str_contains( $content, '[contact-form ' );
	}

	/**
	 * A form printed from somewhere has_cf7_form() does not read (a widget, a
	 * template) still gets its script and styles, in the footer.
	 *
	 * @param string|false $output
	 * @param string       $tag
	 * @return string|false
	 */
	public static function cf7_shortcode_rendered( $output, $tag ) {
		if ( in_array( $tag, [ 'contact-form-7', 'contact-form' ], true ) ) {
			self::cf7_rendered( '' );
		}
		if ( 'thinkrank_breadcrumbs' === $tag ) {
			self::breadcrumbs_printed();
		}
		return $output;
	}

	/** ThinkRank's breadcrumbs stylesheet only on pages whose content prints breadcrumbs. */
	public static function drop_breadcrumb_style(): void {
		if ( is_admin() || ! wp_style_is( 'thinkrank-breadcrumbs', 'enqueued' ) ) {
			return;
		}
		$id = is_singular() ? (int) get_queried_object_id() : 0;
		$content = $id ? (string) get_post_field( 'post_content', $id ) . (string) get_post_meta( $id, '_elementor_data', true ) : '';
		if ( ! str_contains( $content, 'thinkrank_breadcrumbs' ) ) {
			wp_dequeue_style( 'thinkrank-breadcrumbs' );
		}
	}

	/** Breadcrumbs printed from a template or widget still get their styles (in the footer). */
	public static function breadcrumbs_printed(): void {
		if ( wp_style_is( 'thinkrank-breadcrumbs', 'registered' ) ) {
			wp_enqueue_style( 'thinkrank-breadcrumbs' );
		}
	}

	/** @param string $html */
	public static function cf7_rendered( $html ): string {
		// wpcf7_enqueue_scripts() adds its inline settings on every call, so only once.
		if ( function_exists( 'wpcf7_enqueue_scripts' ) && function_exists( 'wpcf7_enqueue_styles' ) && ! wp_script_is( 'contact-form-7', 'enqueued' ) ) {
			wpcf7_enqueue_scripts();
			wpcf7_enqueue_styles();
		}
		return (string) $html;
	}

	public static function needs_woocommerce(): bool {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}
		if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
			return true;
		}
		return is_page( self::WC_PAGES );
	}

	public static function trim_woocommerce(): void {
		if ( is_admin() || self::needs_woocommerce() ) {
			return;
		}
		foreach ( self::WC_SCRIPTS as $handle ) {
			wp_dequeue_script( $handle );
		}
		foreach ( self::WC_STYLES as $handle ) {
			wp_dequeue_style( $handle );
		}
		self::dequeue_payment( wp_scripts(), 'wp_dequeue_script' );
		self::dequeue_payment( wp_styles(), 'wp_dequeue_style' );
	}

	/** @param \WP_Dependencies $deps */
	private static function dequeue_payment( $deps, callable $dequeue ): void {
		foreach ( (array) $deps->queue as $handle ) {
			$src = (string) ( $deps->registered[ $handle ]->src ?? '' );
			if ( preg_match( self::PAYMENT_HANDLE, $handle ) || self::matches( $src, self::PAYMENT_SOURCES ) ) {
				$dequeue( $handle );
			}
		}
	}

	public static function defer_scripts(): void {
		if ( is_admin() ) {
			return;
		}
		$scripts = wp_scripts();
		foreach ( (array) $scripts->queue as $handle ) {
			$src = (string) ( $scripts->registered[ $handle ]->src ?? '' );
			if ( self::matches( $src, self::DEFER_SOURCES ) && ! $scripts->get_data( $handle, 'strategy' ) ) {
				$scripts->add_data( $handle, 'strategy', 'defer' );
			}
		}
	}

	private static function matches( string $src, array $needles ): bool {
		foreach ( $needles as $needle ) {
			if ( '' !== $src && str_contains( $src, $needle ) ) {
				return true;
			}
		}
		return false;
	}
}
