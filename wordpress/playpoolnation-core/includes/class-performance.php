<?php
/**
 * Front-end weight: WooCommerce is only used for accounts and the owner
 * "list your venue" flow, so its shop assets are dropped everywhere else.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Performance {

	private const WC_SCRIPTS = [ 'wc-cart-fragments', 'sourcebuster-js', 'wc-order-attribution', 'wc-add-to-cart', 'wc-add-to-cart-variation', 'wc-single-product', 'wc-jquery-blockui' ];
	private const WC_STYLES  = [ 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general' ];

	/** Pages that still need WooCommerce on the front end (slugs). */
	private const WC_PAGES = [ 'list-your-venue', 'claim-listing', 'claim-your-listing', 'my-pool' ];

	public static function boot(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'trim_woocommerce' ], 99 );
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
	}
}
