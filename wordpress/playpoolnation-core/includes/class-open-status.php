<?php
/**
 * Open/closed status that stays right on cached pages.
 *
 * Pages are cached for days, so a status worked out in PHP goes stale. Every place
 * that shows one also carries the venue's weekly hours and timezone, and
 * assets/ppn.js recomputes the status in the browser with the venue's local time.
 * The server-rendered status stays in place for visitors without JavaScript.
 *
 * - Venue summary strip, My Pool rows: `data-ppn-hours` on the status chip.
 * - Venue page Hours block: `data-ppn-hours` on the block wrapper.
 * - Theme listing cards: one JSON map in the footer keyed by listing ID; cards are
 *   matched through the theme's `data-id="listing-id-<ID>"` attribute. Cards added
 *   later (Load more, map search) are fetched in one batch from /ppn/v1/hours.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Open_Status {

	private const MAX_IDS = 100;

	/** @var array<int,true> Listings whose cards were rendered on this page. */
	private static array $card_ids = [];

	public static function boot(): void {
		// Runs once for every preview card the theme renders, with the card's listing.
		add_filter( 'mylisting/preview-card:bg-size', [ __CLASS__, 'collect_card' ], 10, 2 );
		add_action( 'wp_footer', [ __CLASS__, 'print_map' ], 5 );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'ppn_markup', [ __CLASS__, 'markup' ] );
	}

	/** ` data-ppn-hours='…'` for an element whose status the browser should keep current. */
	public static function attr( Venue $v ): string {
		return ' data-ppn-hours="' . esc_attr( (string) wp_json_encode( $v->hours_data() ) ) . '"';
	}

	/**
	 * @param mixed $size
	 * @param mixed $listing \MyListing\Src\Listing
	 * @return mixed
	 */
	public static function collect_card( $size, $listing = null ) {
		if ( is_object( $listing ) && method_exists( $listing, 'get_id' ) ) {
			self::$card_ids[ (int) $listing->get_id() ] = true;
		}
		return $size;
	}

	/**
	 * @param int[] $ids
	 * @return array<int,array{tz:string,r:array,s:string}>
	 */
	public static function map( array $ids ): array {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, self::MAX_IDS );
		Venue::prime( $ids );
		$map = [];
		foreach ( $ids as $id ) {
			$v = Venue::get( $id );
			if ( ! $v || 'publish' !== $v->post->post_status ) {
				continue;
			}
			$data = $v->hours_data();
			if ( $data['r'] || 'open' !== $data['s'] ) {
				$map[ $id ] = $data;
			}
		}
		return $map;
	}

	public static function print_map(): void {
		$map = self::$card_ids ? self::map( array_keys( self::$card_ids ) ) : [];
		if ( ! $map ) {
			return;
		}
		echo '<script type="application/json" id="ppn-hours-map">' . wp_json_encode( $map, JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n";
	}

	public static function routes(): void {
		register_rest_route( 'ppn/v1', '/hours', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => [ 'ids' => [ 'type' => 'string', 'required' => true ] ],
			'callback'            => static function ( \WP_REST_Request $request ) {
				$ids = array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) );
				$res = new \WP_REST_Response( (object) self::map( $ids ) );
				// Weekly hours, not a status: safe to cache.
				$res->header( 'Cache-Control', 'public, max-age=3600' );
				return $res;
			},
		] );
	}

	/** Venue page Hours block: hours data on its wrapper, and no "<date> local time" line frozen at cache time. */
	public static function markup(): void {
		if ( ! is_singular( Pool_Schema::POST_TYPE ) ) {
			return;
		}
		$v = Venue::get( (int) get_queried_object_id() );
		if ( ! $v || ! Venue::is_venue( $v->id() ) ) {
			return;
		}
		$attr = self::attr( $v );
		Markup::add( static fn( string $html ) => self::hours_block( $html, $attr ) );
	}

	public static function hours_block( string $html, string $attr ): string {
		$html = (string) preg_replace_callback( '/<div class="[^"]*\bopen-now sl-zindex\b[^"]*"/', static fn( $m ) => $m[0] . $attr, $html, 1 );
		return (string) preg_replace(
			'#<p class="work-hours-timezone">.*?</p>#s',
			'<p class="work-hours-timezone" data-ppn-local-time hidden><em></em></p>',
			$html,
			1
		);
	}
}
