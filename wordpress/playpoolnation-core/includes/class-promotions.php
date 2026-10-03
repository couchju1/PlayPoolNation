<?php
/**
 * Paid promotions: Featured Venue and Promoted Event.
 *
 * Each package is a hidden, virtual WooCommerce product. An owner picks one of
 * their listings on /promote/, pays at the normal checkout, and once the order is
 * paid the listing gets My Listing's priority (`_featured`), which puts it first in
 * search results, until the promotion ends. Promotions stack: buying again extends
 * the end date. A daily job ends expired promotions and emails a renewal note.
 * Search result cards show the theme's own "Featured" / "Promoted" badge.
 *
 * Paid placement is always labelled ("Featured" / "Promoted") for players.
 *
 * Everything stays off until an administrator switches it on under
 * PlayPoolNation → Promotions, which needs a WooCommerce payment method.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Promotions {

	public const ENABLED = 'ppn_promotions_enabled';
	public const PRODUCTS = 'ppn_promotion_products';
	public const PAGE = 'promote';
	public const SALES_PAGE = 'advertise';
	public const SALES_OPTION = 'ppn_promotions_sales_page';
	public const UNTIL = '_ppn_promoted_until';
	private const BEFORE = '_ppn_featured_before';
	private const NONCE = 'ppn_promote';

	/** Package defaults. Prices are starting suggestions; edit them in WooCommerce → Products. */
	public const PACKAGES = [
		'venue' => [
			'title' => 'Featured Venue (30 days)',
			'price' => '29',
			'days'  => 30,
			'for'   => Pool_Schema::VENUE_TYPE,
			'label' => 'Featured',
			'priority' => 1, // My Listing shows priority 1 as "Featured" on cards.
			'desc'  => 'Your venue shows first in search results and on the map for your area for 30 days, marked Featured.',
		],
		'event' => [
			'title' => 'Promoted Event (14 days)',
			'price' => '15',
			'days'  => 14,
			'for'   => Pool_Schema::TOURNAMENT_TYPE,
			'label' => 'Promoted',
			'priority' => 2, // ...and priority 2 as "Promoted".
			'desc'  => 'Your event shows first on the Events page and in players\' My Pool for 14 days, marked Promoted.',
		],
	];

	private static bool $adding = false;

	public static function boot(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
		add_action( 'admin_post_ppn_promotions_toggle', [ __CLASS__, 'handle_toggle' ] );
		add_action( 'admin_post_ppn_promote', [ __CLASS__, 'handle_promote' ] );
		add_action( 'add_meta_boxes_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'meta_box' ] );
		add_action( 'save_post_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'save_grant' ], 25, 1 );
		add_shortcode( 'ppn_promote', [ __CLASS__, 'promote_page' ] );
		add_shortcode( 'ppn_advertise', [ __CLASS__, 'sales_page' ] );
		add_action( Play::CRON, [ __CLASS__, 'expire' ], 30 );
		add_action( 'template_redirect', [ __CLASS__, 'no_cache' ], 1 );
		// WooCommerce: carry the chosen listing through cart, order and payment.
		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'only_through_promote' ], 10, 2 );
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'cart_item_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'order_item_meta' ], 10, 3 );
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'apply_order' ] );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'apply_order' ] );
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'account_menu' ], 80 );
		add_filter( 'woocommerce_get_endpoint_url', [ __CLASS__, 'account_menu_url' ], 10, 2 );
	}

	public static function enabled(): bool {
		return (bool) get_option( self::ENABLED, false );
	}

	public static function page_url(): string {
		$page = get_page_by_path( self::PAGE );
		return $page ? (string) get_permalink( $page ) : home_url( '/' . self::PAGE . '/' );
	}

	public static function no_cache(): void {
		if ( is_page( self::PAGE ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			do_action( 'litespeed_control_set_nocache', 'promote page' );
			nocache_headers();
		}
	}

	/* ------------------------------------------------------------ products */

	/** @return array<string,int> package key => product ID */
	public static function product_ids(): array {
		$ids = get_option( self::PRODUCTS, [] );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	public static function product( string $key ): ?\WC_Product {
		$id = self::product_ids()[ $key ] ?? 0;
		$p = $id && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		return $p instanceof \WC_Product ? $p : null;
	}

	public static function package_for_product( int $product_id ): string {
		return (string) ( array_search( $product_id, self::product_ids(), true ) ?: '' );
	}

	public static function package_for_listing( int $listing_id ): string {
		$type = Venue::listing_type( $listing_id );
		foreach ( self::PACKAGES as $key => $def ) {
			if ( $def['for'] === $type ) {
				return $key;
			}
		}
		return '';
	}

	/** Create the hidden package products once. Existing ones are left as they are (prices may have been edited). */
	public static function ensure_products(): array {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			return [];
		}
		$ids = self::product_ids();
		foreach ( self::PACKAGES as $key => $def ) {
			if ( ! empty( $ids[ $key ] ) && wc_get_product( $ids[ $key ] ) ) {
				continue;
			}
			$p = new \WC_Product_Simple();
			$p->set_name( $def['title'] );
			$p->set_status( self::enabled() ? 'publish' : 'private' );
			$p->set_catalog_visibility( 'hidden' );
			$p->set_virtual( true );
			$p->set_sold_individually( true );
			$p->set_regular_price( $def['price'] );
			$p->set_description( $def['desc'] );
			$p->set_short_description( $def['desc'] );
			$p->update_meta_data( '_ppn_promotion', $key );
			$ids[ $key ] = (int) $p->save();
		}
		update_option( self::PRODUCTS, $ids, false );
		return $ids;
	}

	public static function days( string $key ): int {
		$p = self::product( $key );
		$days = $p ? (int) $p->get_meta( '_ppn_days' ) : 0;
		return $days > 0 ? $days : (int) self::PACKAGES[ $key ]['days'];
	}

	public static function price_html( string $key ): string {
		$p = self::product( $key );
		return $p ? wp_strip_all_tags( wc_price( (float) $p->get_price() ) ) : '';
	}

	/* --------------------------------------------------------- applying */

	public static function promoted_until( int $listing_id ): string {
		$until = (string) get_post_meta( $listing_id, self::UNTIL, true );
		return ( '' !== $until && strtotime( $until ) > time() ) ? $until : '';
	}

	public static function is_promoted( int $listing_id ): bool {
		return '' !== self::promoted_until( $listing_id );
	}

	/**
	 * Promote a listing for N days, extending any current promotion.
	 *
	 * @return string The new end date (UTC, Y-m-d H:i:s).
	 */
	public static function apply( int $listing_id, int $days, string $source, int $order_id = 0 ): string {
		$now = time();
		$current = strtotime( (string) get_post_meta( $listing_id, self::UNTIL, true ) );
		$base = ( $current && $current > $now ) ? $current : $now;
		$until = gmdate( 'Y-m-d H:i:s', $base + $days * DAY_IN_SECONDS );
		if ( ! self::is_promoted( $listing_id ) ) {
			update_post_meta( $listing_id, self::BEFORE, (string) get_post_meta( $listing_id, '_featured', true ) );
		}
		$key = self::package_for_listing( $listing_id );
		update_post_meta( $listing_id, '_featured', $key ? self::PACKAGES[ $key ]['priority'] : 1 );
		update_post_meta( $listing_id, self::UNTIL, $until );
		$log = get_post_meta( $listing_id, '_ppn_promotions', true );
		$log = is_array( $log ) ? $log : [];
		$log[] = [ 'at' => gmdate( 'Y-m-d H:i:s' ), 'days' => $days, 'until' => $until, 'source' => $source, 'order' => $order_id, 'by' => get_current_user_id() ];
		update_post_meta( $listing_id, '_ppn_promotions', array_slice( $log, -50 ) );
		delete_post_meta( $listing_id, '_ppn_promo_reminded' );
		Venue::flush_cache( $listing_id );
		if ( function_exists( 'litespeed_purge_single_post' ) ) {
			litespeed_purge_single_post( $listing_id );
		}
		do_action( 'litespeed_purge_post', $listing_id );
		return $until;
	}

	public static function end( int $listing_id ): void {
		$before = get_post_meta( $listing_id, self::BEFORE, true );
		'' !== (string) $before && '0' !== (string) $before ? update_post_meta( $listing_id, '_featured', (int) $before ) : delete_post_meta( $listing_id, '_featured' );
		delete_post_meta( $listing_id, self::UNTIL );
		delete_post_meta( $listing_id, self::BEFORE );
		Venue::flush_cache( $listing_id );
		do_action( 'litespeed_purge_post', $listing_id );
	}

	/** Daily: end expired promotions and send one renewal email. */
	public static function expire(): void {
		$ids = get_posts( [
			'post_type'      => Pool_Schema::POST_TYPE,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'meta_query'     => [ [ 'key' => self::UNTIL, 'value' => gmdate( 'Y-m-d H:i:s' ), 'compare' => '<', 'type' => 'DATETIME' ] ],
		] );
		foreach ( $ids as $id ) {
			$id = (int) $id;
			self::end( $id );
			self::renewal_email( $id );
		}
	}

	private static function renewal_email( int $listing_id ): void {
		$author = get_userdata( (int) get_post_field( 'post_author', $listing_id ) );
		if ( ! $author || ! is_email( $author->user_email ) || user_can( $author, 'manage_options' ) || ! self::enabled() ) {
			return;
		}
		$key = self::package_for_listing( $listing_id );
		wp_mail(
			$author->user_email,
			'Your PlayPoolNation promotion has ended',
			sprintf(
				"Hi %s,\n\nThe %s placement for \"%s\" has ended, so it no longer shows first in search.\n\nRenew it here:\n%s\n\nThanks,\nPlayPoolNation",
				$author->display_name,
				$key ? strtolower( self::PACKAGES[ $key ]['label'] ) : 'promoted',
				html_entity_decode( get_the_title( $listing_id ), ENT_QUOTES ),
				self::page_url()
			)
		);
	}

	/* -------------------------------------------------------- who can buy */

	/** The listing owner (claimed venue author, or the event's poster / its venue's owner) or staff. */
	public static function can_promote( int $user_id, int $listing_id ): bool {
		if ( ! $user_id || 'publish' !== get_post_status( $listing_id ) || '' === self::package_for_listing( $listing_id ) ) {
			return false;
		}
		if ( user_can( $user_id, 'edit_others_posts' ) ) {
			return true;
		}
		$type = Venue::listing_type( $listing_id );
		if ( Pool_Schema::VENUE_TYPE === $type ) {
			return isset( Events::owned_venues( $user_id )[ $listing_id ] );
		}
		if ( (int) get_post_field( 'post_author', $listing_id ) === $user_id ) {
			return true;
		}
		$venue = (int) get_post_meta( $listing_id, '_ppn_venue_id', true );
		return $venue && isset( Events::owned_venues( $user_id )[ $venue ] );
	}

	/** @return int[] listings this user can promote: their claimed venues, then upcoming events */
	public static function promotable( int $user_id ): array {
		$venues = array_keys( Events::owned_venues( $user_id ) );
		$events = get_posts( [
			'post_type'      => Pool_Schema::POST_TYPE,
			'post_status'    => 'publish',
			'author'         => $user_id,
			'fields'         => 'ids',
			'posts_per_page' => 50,
			'meta_key'       => '_case27_listing_type',
			'meta_value'     => Pool_Schema::TOURNAMENT_TYPE,
		] );
		foreach ( $venues as $venue ) {
			$events = array_merge( $events, Play::children( $venue, Pool_Schema::TOURNAMENT_VENUE_FIELD ) );
		}
		$events = array_values( array_filter( array_unique( array_map( 'intval', $events ) ), static fn( $id ) => ! Events::is_ended( $id ) ) );
		return array_merge( $venues, $events );
	}

	/* ------------------------------------------------------------ buying */

	public static function handle_promote(): void {
		$user = get_current_user_id();
		$listing = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		$back = add_query_arg( 'ppn_msg', 'error', self::page_url() );
		if ( ! $user || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
			wp_safe_redirect( $back );
			exit;
		}
		$key = self::package_for_listing( $listing );
		$product = $key ? self::product( $key ) : null;
		if ( ! self::enabled() || ! $product || ! self::can_promote( $user, $listing ) || ! function_exists( 'WC' ) ) {
			wp_safe_redirect( $back );
			exit;
		}
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		// One promotion per checkout keeps the order and its listing unambiguous.
		WC()->cart->empty_cart();
		self::$adding = true;
		WC()->cart->add_to_cart( $product->get_id(), 1, 0, [], [ 'ppn_listing_id' => $listing ] );
		self::$adding = false;
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/** Promotion products can only be added from /promote/, with a listing attached. */
	public static function only_through_promote( $passed, $product_id ) {
		if ( '' !== self::package_for_product( (int) $product_id ) && ! self::$adding ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( 'Choose the listing to promote on the Promote page.', 'error' );
			}
			return false;
		}
		return $passed;
	}

	public static function cart_item_data( array $data, array $item ): array {
		if ( ! empty( $item['ppn_listing_id'] ) ) {
			$data[] = [ 'name' => 'Listing', 'value' => esc_html( html_entity_decode( get_the_title( (int) $item['ppn_listing_id'] ), ENT_QUOTES ) ) ];
		}
		return $data;
	}

	public static function order_item_meta( $item, $cart_key, $values ): void {
		if ( ! empty( $values['ppn_listing_id'] ) ) {
			$item->add_meta_data( '_ppn_listing_id', (int) $values['ppn_listing_id'], true );
			$item->add_meta_data( 'Listing', html_entity_decode( get_the_title( (int) $values['ppn_listing_id'] ), ENT_QUOTES ), true );
		}
	}

	/** Paid order: promote each listing once. */
	public static function apply_order( $order_id ): void {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$listing = (int) $item->get_meta( '_ppn_listing_id' );
			$key = self::package_for_product( (int) $item->get_product_id() );
			if ( ! $listing || '' === $key || $item->get_meta( '_ppn_applied' ) ) {
				continue;
			}
			$until = self::apply( $listing, self::days( $key ), 'order', (int) $order->get_id() );
			$item->update_meta_data( '_ppn_applied', $until );
			$item->save();
			$order->add_order_note( sprintf( '%s applied to "%s" until %s (UTC).', self::PACKAGES[ $key ]['title'], get_the_title( $listing ), $until ) );
		}
	}

	/* ------------------------------------------------------------- pages */

	private static function message(): string {
		$msg = isset( $_GET['ppn_msg'] ) ? sanitize_key( wp_unslash( $_GET['ppn_msg'] ) ) : '';
		return 'error' === $msg ? '<p class="ppn-notice ppn-notice--error" role="alert">That listing could not be promoted. Please try again.</p>' : '';
	}

	/** [ppn_promote]: the owner's list of listings with Feature / Promote buttons. */
	public static function promote_page(): string {
		if ( ! self::enabled() ) {
			return '<div class="ppn-pool"><section class="ppn-pool-section"><h2 class="ppn-pool-h">Promotions are coming soon</h2><p class="ppn-note">Listing your venue and posting events stay free. Featured placement for venues and events will be available here soon.</p></section></div>';
		}
		if ( ! is_user_logged_in() ) {
			return '<div class="ppn-pool"><section class="ppn-pool-section"><h2 class="ppn-pool-h">Sign in to promote your venue or event</h2>'
				. '<p class="ppn-note">Promotions are for venue owners who have claimed their listing, and for event organizers.</p>'
				. '<p><a class="ppn-button" href="' . esc_url( add_query_arg( 'redirect_to', rawurlencode( self::page_url() ), My_Pool::sign_in_url() ) ) . '">Sign in</a></p></section></div>';
		}
		$user = get_current_user_id();
		$ids = self::promotable( $user );
		Venue::prime( $ids );
		$h = '<div class="ppn-pool">' . self::message();
		if ( ! $ids ) {
			return $h . '<section class="ppn-pool-section"><h2 class="ppn-pool-h">Nothing to promote yet</h2>'
				. '<p class="ppn-note">Claim your venue first: open your venue\'s page on PlayPoolNation and choose <strong>Claim this venue</strong>. Once it is approved, your venue and its events show up here.</p>'
				. '<p><a class="ppn-button" href="' . esc_url( Display::explore_url() ) . '">Find your venue</a></p></section></div>';
		}
		foreach ( [ 'venue' => 'Your venues', 'event' => 'Your upcoming events' ] as $key => $heading ) {
			$rows = '';
			foreach ( $ids as $id ) {
				if ( self::package_for_listing( $id ) !== $key ) {
					continue;
				}
				$v = Venue::get( $id );
				if ( ! $v ) {
					continue;
				}
				$until = self::promoted_until( $id );
				$status = $until
					? '<span class="ppn-fact ppn-fact--verified">' . esc_html( self::PACKAGES[ $key ]['label'] . ' until ' . wp_date( 'M j', strtotime( $until . ' UTC' ) ) ) . '</span>'
					: '<span class="ppn-row-meta">Not promoted</span>';
				$rows .= '<li class="ppn-place"><div class="ppn-place-main"><a class="ppn-row-title" href="' . esc_url( $v->url() ) . '">' . esc_html( $v->name() ) . '</a>' . $status . '</div>'
					. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
					. '<input type="hidden" name="action" value="ppn_promote"><input type="hidden" name="listing_id" value="' . esc_attr( (string) $id ) . '">' . wp_nonce_field( self::NONCE, '_wpnonce', true, false )
					. '<button type="submit" class="ppn-button">' . esc_html( ( $until ? 'Extend ' : '' ) . self::days( $key ) . ' days · ' . self::price_html( $key ) ) . '</button></form></li>';
			}
			if ( $rows ) {
				$h .= '<section class="ppn-pool-section"><h2 class="ppn-pool-h">' . esc_html( $heading ) . '</h2>'
					. '<p class="ppn-note">' . esc_html( self::PACKAGES[ $key ]['desc'] ) . ' Buying again adds days to the end.</p><ul class="ppn-places">' . $rows . '</ul></section>';
			}
		}
		return $h . '</div>';
	}

	/** [ppn_advertise]: the public "Grow your business" page with live prices. */
	public static function sales_page(): string {
		$card = static function ( string $key ): string {
			$def = self::PACKAGES[ $key ];
			return '<div class="ppn-aside ppn-package"><h2>' . esc_html( $def['label'] === 'Featured' ? 'Featured Venue' : 'Promoted Event' ) . '</h2>'
				. '<p class="ppn-package-price"><strong>' . esc_html( self::price_html( $key ) ) . '</strong> for ' . (int) self::days( $key ) . ' days</p>'
				. '<p>' . esc_html( $def['desc'] ) . '</p></div>';
		};
		return '<div class="ppn-ads">'
			. '<div class="ppn-ads-free"><h2>Free for every venue</h2><ul class="ppn-pool-benefits">'
			. '<li><strong>Your listing.</strong> Tables, pricing, hours, leagues and photos.</li>'
			. '<li><strong>Your events.</strong> Post tournaments and events. Claimed venues publish instantly.</li>'
			. '<li><strong>Players who saved you</strong> see your events in their My Pool.</li></ul></div>'
			. '<div class="ppn-packages">' . $card( 'venue' ) . $card( 'event' ) . '</div>'
			. '<p class="ppn-ads-cta"><a class="ppn-button" href="' . esc_url( self::page_url() ) . '">Promote your venue or event</a> '
			. '<a class="ppn-button ppn-button--ghost" href="' . esc_url( Display::explore_url() ) . '">Find and claim your venue</a></p>'
			. '<p class="ppn-fineprint">Promoted placements are labelled for players. No contract: each promotion simply ends after its days are up.</p></div>';
	}

	/* ------------------------------------------------------- account menu */

	public static function account_menu( array $items ): array {
		if ( ! self::enabled() ) {
			return $items;
		}
		$out = [];
		foreach ( $items as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'ppn-post-event' === $key ) {
				$out['ppn-promote'] = 'Promote';
			}
		}
		return isset( $out['ppn-promote'] ) ? $out : $out + [ 'ppn-promote' => 'Promote' ];
	}

	public static function account_menu_url( string $url, string $endpoint ): string {
		return 'ppn-promote' === $endpoint ? self::page_url() : $url;
	}

	/* --------------------------------------------------------------- admin */

	public static function gateways_ready(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return false;
		}
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			if ( 'yes' === $gateway->enabled ) {
				return true;
			}
		}
		return false;
	}

	public static function set_enabled( bool $on ): void {
		update_option( self::ENABLED, $on ? 1 : 0 );
		foreach ( self::product_ids() as $id ) {
			wp_update_post( [ 'ID' => $id, 'post_status' => $on ? 'publish' : 'private' ] );
		}
		$sales = (int) get_option( self::SALES_OPTION );
		if ( $sales && get_post( $sales ) ) {
			wp_update_post( [ 'ID' => $sales, 'post_status' => $on ? 'publish' : 'draft', 'post_name' => self::SALES_PAGE ] );
		}
		do_action( 'litespeed_purge_all' );
	}

	public static function handle_toggle(): void {
		check_admin_referer( 'ppn_promotions_toggle' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$on = ! empty( $_POST['enable'] );
		$msg = 'saved';
		if ( $on && ! self::gateways_ready() ) {
			$msg = 'no-gateway';
		} else {
			self::set_enabled( $on );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=ppn-promotions&ppn_msg=' . $msg ) );
		exit;
	}

	public static function menu(): void {
		add_submenu_page( Moderation::MENU, 'Promotions', 'Promotions', 'manage_options', 'ppn-promotions', [ __CLASS__, 'admin_page' ] );
	}

	public static function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$on = self::enabled();
		$msg = isset( $_GET['ppn_msg'] ) ? sanitize_key( wp_unslash( $_GET['ppn_msg'] ) ) : '';
		echo '<div class="wrap"><h1>Promotions</h1>';
		if ( 'no-gateway' === $msg ) {
			echo '<div class="notice notice-error"><p>Set up a payment method first (WooCommerce → Settings → Payments), then switch promotions on.</p></div>';
		} elseif ( 'saved' === $msg ) {
			echo '<div class="notice notice-success"><p>Saved.</p></div>';
		}
		echo '<p>Status: <strong>' . ( $on ? 'On: owners can buy promotions' : 'Off: nothing is for sale' ) . '</strong>. Payment method: <strong>' . ( self::gateways_ready() ? 'ready' : 'not set up' ) . '</strong>.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ppn_promotions_toggle">';
		wp_nonce_field( 'ppn_promotions_toggle' );
		echo '<input type="hidden" name="enable" value="' . ( $on ? '' : '1' ) . '"><button class="button button-primary">' . ( $on ? 'Switch promotions off' : 'Switch promotions on' ) . '</button></form>';

		echo '<h2>Packages</h2><table class="widefat striped" style="max-width:820px"><thead><tr><th>Package</th><th>Price</th><th>Days</th><th>For</th><th></th></tr></thead><tbody>';
		foreach ( self::PACKAGES as $key => $def ) {
			$p = self::product( $key );
			printf(
				'<tr><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>',
				esc_html( $p ? $p->get_name() : $def['title'] ),
				esc_html( $p ? self::price_html( $key ) : 'missing' ),
				(int) self::days( $key ),
				esc_html( Pool_Schema::VENUE_TYPE === $def['for'] ? 'Venues' : 'Events' ),
				$p ? '<a href="' . esc_url( get_edit_post_link( $p->get_id() ) ?: '' ) . '">Edit price</a>' : ''
			);
		}
		echo '</tbody></table><p class="description">To change the number of days, add a custom field <code>_ppn_days</code> on the product.</p>';

		$active = get_posts( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 100, 'meta_key' => self::UNTIL, 'orderby' => 'meta_value', 'order' => 'ASC' ] );
		echo '<h2>Active promotions</h2>';
		if ( ! $active ) {
			echo '<p>None.</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th>Listing</th><th>Ends (site time)</th><th>Last source</th></tr></thead><tbody>';
			foreach ( $active as $post ) {
				$log = (array) get_post_meta( $post->ID, '_ppn_promotions', true );
				$last = end( $log ) ?: [];
				printf( '<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>', esc_url( get_edit_post_link( $post->ID ) ?: '' ), esc_html( get_the_title( $post ) ), esc_html( wp_date( 'M j, Y g:i A', strtotime( get_post_meta( $post->ID, self::UNTIL, true ) . ' UTC' ) ) ), esc_html( ( $last['source'] ?? '' ) . ( ! empty( $last['order'] ) ? ' #' . $last['order'] : '' ) ) );
			}
			echo '</tbody></table>';
		}
		echo '<p class="description">To give a venue or event a free promotion (for example a launch offer), open it under Listings and use the Promotion box.</p></div>';
	}

	public static function meta_box( \WP_Post $post ): void {
		if ( '' === self::package_for_listing( (int) $post->ID ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_meta_box( 'ppn-promotion', 'Promotion', static function () use ( $post ) {
			wp_nonce_field( 'ppn_grant', 'ppn_grant_nonce' );
			$until = self::promoted_until( (int) $post->ID );
			echo '<p>' . ( $until ? 'Promoted until ' . esc_html( wp_date( 'M j, Y', strtotime( $until . ' UTC' ) ) ) . '.' : 'Not promoted.' ) . '</p>';
			echo '<p><label>Add free days <input type="number" name="ppn_grant_days" min="1" max="365" style="width:70px"></label></p>';
			if ( $until ) {
				echo '<p><label><input type="checkbox" name="ppn_end_promotion" value="1"> End the promotion now</label></p>';
			}
		}, null, 'side' );
	}

	public static function save_grant( int $post_id ): void {
		if ( ! isset( $_POST['ppn_grant_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppn_grant_nonce'] ) ), 'ppn_grant' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! empty( $_POST['ppn_end_promotion'] ) ) {
			self::end( $post_id );
			return;
		}
		$days = isset( $_POST['ppn_grant_days'] ) ? min( 365, absint( $_POST['ppn_grant_days'] ) ) : 0;
		if ( $days ) {
			self::apply( $post_id, $days, 'admin' );
		}
	}
}
