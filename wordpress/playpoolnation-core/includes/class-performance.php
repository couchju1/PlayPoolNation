<?php
/**
 * Front-end weight: WooCommerce is only used for accounts and the owner
 * "list your venue" flow, so its shop and payment assets are dropped everywhere
 * else. Payment plugins (WooPayments, PayPal Payments, Stripe) and WooCommerce
 * Blocks are matched by source folder, since their handle names change between
 * versions. WooPay's direct checkout alone pulled React and ~18 wp-* packages
 * onto every page.
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

	/** Pages that still need WooCommerce on the front end (slugs). */
	private const WC_PAGES = [ 'list-your-venue', 'claim-listing', 'claim-your-listing', 'my-pool', 'sign-in', 'join' ];

	public static function boot(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'trim_woocommerce' ], 99 );
		// Some of these are enqueued late (while the page renders), so trim again before the footer prints.
		add_action( 'wp_print_footer_scripts', [ __CLASS__, 'trim_woocommerce' ], 1 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'defer_scripts' ], 999 );
		add_action( 'wp_print_footer_scripts', [ __CLASS__, 'defer_scripts' ], 1 );
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
