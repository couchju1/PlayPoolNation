<?php
/**
 * "Play tonight" on the homepage: the hero answers "where can I play right now?"
 * instead of showing a blank search form.
 *
 * The panel sits in the homepage hero, in place of the theme's search widget. It
 * shows how many rooms are open at this moment, a "Find an open table near me"
 * button and a city picker. Either one lists the three best matches, open rooms
 * first, with Call and Directions right on the row.
 *
 * Every venue's hours, location and sourced table facts come from one cached REST
 * response (GET /ppn/v1/tonight). The open/closed status and the ranking happen in
 * the browser with each venue's local time (assets/ppn.js), so cached pages stay
 * right. Without JavaScript the panel is a link to the full list and the theme's
 * search stays visible; the script hides it once the panel works.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Format;

defined( 'ABSPATH' ) || exit;

final class Tonight {

	private const CACHE = 'ppn_tonight_v1';

	public static function boot(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'save_post_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'flush' ] );
		add_action( 'deleted_post', [ __CLASS__, 'flush' ] );
		add_action( 'set_object_terms', [ __CLASS__, 'flush' ] );
		add_action( 'ppn_markup', [ __CLASS__, 'markup' ] );
	}

	public static function flush(): void {
		delete_transient( self::CACHE );
	}

	public static function routes(): void {
		register_rest_route( 'ppn/v1', '/tonight', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => static function () {
				$res = new \WP_REST_Response( self::data() );
				// Weekly hours and places, not a status: safe to cache.
				$res->header( 'Cache-Control', 'public, max-age=3600' );
				return $res;
			},
		] );
	}

	/**
	 * Published venues with a map location, and the cities they are in.
	 *
	 * Venue keys are short because the list goes to every homepage visitor:
	 * i id, n name, u page, k city, a lat, o lng, h hours (tz, r, s), t tables,
	 * z sizes, p phone (E.164), d directions, l league weekdays (0 = Monday).
	 *
	 * @return array{v:array<int,array>,c:array<int,array{k:int,n:string,u:string}>}
	 */
	public static function data(): array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$ids = array_map( 'intval', get_posts( [
			'post_type'      => Pool_Schema::POST_TYPE,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 2000,
			'no_found_rows'  => true,
			'meta_query'     => [ [ 'key' => '_case27_listing_type', 'value' => Pool_Schema::VENUE_TYPE ] ],
		] ) );
		Venue::prime( $ids );
		$venues = [];
		$cities = [];
		foreach ( $ids as $id ) {
			$v = Venue::get( $id );
			if ( ! $v || 'permanently-closed' === $v->status() ) {
				continue;
			}
			$f = City_Page::facts( $v );
			if ( null === $f['lat'] || null === $f['lng'] || ( 0.0 === $f['lat'] && 0.0 === $f['lng'] ) ) {
				continue;
			}
			$city = self::city( $id );
			if ( $city && ! isset( $cities[ $city['k'] ] ) ) {
				$cities[ $city['k'] ] = $city;
			}
			$sizes = Helpers\City_Text::sizes( $f );
			$venues[] = array_filter( [
				'i' => $id,
				'n' => $f['name'],
				'u' => $f['url'],
				'k' => $city ? $city['k'] : 0,
				'a' => round( (float) $f['lat'], 5 ),
				'o' => round( (float) $f['lng'], 5 ),
				'h' => $v->hours_data(),
				't' => Helpers\City_Text::tables( $f ),
				'z' => $sizes ? Helpers\About_Text::and_list( $sizes ) : '',
				'p' => Format::e164( (string) $f['phone'] ),
				'd' => '' === trim( (string) $f['address'] ) ? '' : Format::directions_url( (string) $f['address'] ),
				'l' => array_values( array_unique( array_filter( array_column( $f['leagues'], 'day' ), 'is_int' ) ) ),
			], static fn( $value ) => null !== $value && '' !== $value && [] !== $value );
		}
		usort( $cities, static fn( $a, $b ) => strcmp( $a['n'], $b['n'] ) );
		$data = [ 'v' => $venues, 'c' => array_values( $cities ) ];
		set_transient( self::CACHE, $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}

	/** The venue's city region (a region term with a parent state). */
	private static function city( int $id ): ?array {
		$terms = get_the_terms( $id, Pool_Schema::TAX_REGION );
		foreach ( ( $terms && ! is_wp_error( $terms ) ) ? $terms : [] as $term ) {
			if ( ! $term->parent ) {
				continue;
			}
			$url = get_term_link( $term );
			return [ 'k' => (int) $term->term_id, 'n' => City_Page::where( $term ), 'u' => is_wp_error( $url ) ? '' : (string) $url ];
		}
		return null;
	}

	public static function markup(): void {
		if ( is_front_page() ) {
			Markup::add( [ __CLASS__, 'inject' ] );
		}
	}

	/** Put the panel in the homepage hero, just before the theme's search widget. */
	public static function inject( string $html ): string {
		if ( ! preg_match( '#<div class="elementor-element [^"]*\bppn-hero-search\b#', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}
		return substr_replace( $html, self::panel(), $m[0][1], 0 );
	}

	public static function panel(): string {
		$places = esc_url( Display::explore_url() );
		$data = esc_url( rest_url( 'ppn/v1/tonight' ) );
		return '<section class="ppn-tn" data-ppn-tonight="' . $data . '" aria-labelledby="ppn-tn-title">'
			. '<p class="ppn-tn-live" data-ppn-tn-live hidden><span class="ppn-tn-dot" aria-hidden="true"></span><span data-ppn-tn-count></span></p>'
			. '<h2 class="ppn-tn-title" id="ppn-tn-title">Want to shoot some pool tonight?</h2>'
			. '<div class="ppn-tn-controls" data-ppn-tn-controls hidden>'
			. '<button type="button" class="ppn-tn-go" data-ppn-tn-locate>Find an open table near me</button>'
			. '<span class="ppn-tn-or" aria-hidden="true">or</span>'
			. '<label class="ppn-tn-city"><span class="screen-reader-text">Pick a city</span><select data-ppn-tn-city><option value="">Pick a city</option></select></label>'
			. '</div>'
			. '<p class="ppn-tn-msg" data-ppn-tn-msg role="status" aria-live="polite"></p>'
			. '<ol class="ppn-tn-list" data-ppn-tn-list hidden></ol>'
			. '<p class="ppn-tn-more" data-ppn-tn-more hidden></p>'
			. '<noscript><p class="ppn-tn-noscript"><a class="ppn-tn-go" href="' . $places . '">See every place to play</a></p></noscript>'
			. '</section>';
	}
}
