<?php
/**
 * Metro pages: /places/<metro>/ for metros in the `ppn_metros` option whose venues
 * are spread over several cities, none of which has a city page of its own
 * (Minneapolis-St. Paul, Boston and Detroit when this was written).
 *
 * They render through the explore page like state and city pages, with the
 * metro's radius search applied on the server, so they get their own H1, title,
 * description and canonical (Seo_Meta) and a place in ThinkRank's sitemap.xml.
 * Metros without a page redirect to their city page or to the map around them,
 * and a metro's main city without a city page of its own redirects to the metro.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Metros {

	public const QUERY_VAR = 'ppn_metro';

	/** ThinkRank deletes this option once a sitemap rebuild has been written. */
	private const SITEMAP_PENDING = 'thinkrank_sitemap_regeneration_pending';

	public static function boot(): void {
		add_filter( 'query_vars', [ __CLASS__, 'query_vars' ] );
		add_filter( 'rewrite_rules_array', [ __CLASS__, 'rules' ] );
		add_action( 'parse_request', [ __CLASS__, 'parse_request' ] );
		add_action( 'template_redirect', [ __CLASS__, 'redirect' ], 2 );
		add_action( 'add_option_ppn_metros', [ __CLASS__, 'metros_changed' ], 20 );
		add_action( 'update_option_ppn_metros', [ __CLASS__, 'metros_changed' ], 20 );
		add_action( 'deleted_option', [ __CLASS__, 'sitemap_written' ] );
	}

	public static function slug( string $label ): string {
		return Helpers\Format::slugify( $label );
	}

	/**
	 * Metros from the option, keyed by slug. `index` lines up with Stats' metro counts.
	 *
	 * @return array<string,array{slug:string,label:string,location:string,lat:float,lng:float,radius:int,index:int}>
	 */
	public static function all(): array {
		$out = [];
		foreach ( array_values( (array) get_option( 'ppn_metros', [] ) ) as $i => $m ) {
			$slug = is_array( $m ) && count( $m ) >= 5 ? self::slug( (string) $m[0] ) : '';
			if ( '' === $slug || isset( $out[ $slug ] ) ) {
				continue;
			}
			$out[ $slug ] = [
				'slug'     => $slug,
				'label'    => (string) $m[0],
				'location' => (string) $m[1],
				'lat'      => (float) $m[2],
				'lng'      => (float) $m[3],
				'radius'   => (int) $m[4],
				'index'    => $i,
			];
		}
		return $out;
	}

	public static function find( string $slug ): ?array {
		return self::all()[ $slug ] ?? null;
	}

	public static function venue_count( array $metro ): int {
		return (int) ( Stats::stats()['metro_counts'][ $metro['index'] ] ?? 0 );
	}

	/** @return string[] Cities with venues in the metro, most venues first. */
	public static function cities( array $metro ): array {
		return (array) ( Stats::stats()['metro_cities'][ $metro['index'] ] ?? [] );
	}

	/** Enough venues, in more than one city, and no single city page that already covers them. */
	public static function has_page( array $metro ): bool {
		return self::venue_count( $metro ) >= Seo::MIN_REGION_VENUES
			&& count( self::cities( $metro ) ) > 1
			&& ! Stats::city_term( $metro['location'] );
	}

	/** @return array<string,array> Metros with a page, keyed by slug. */
	public static function with_pages(): array {
		return array_filter( self::all(), [ __CLASS__, 'has_page' ] );
	}

	public static function url( array $metro ): string {
		return home_url( user_trailingslashit( Locations::REGION_BASE . '/' . $metro['slug'] ) );
	}

	/** e.g. "the Minneapolis–St. Paul area". */
	public static function area( array $metro ): string {
		return 'the ' . $metro['label'] . ' area';
	}

	/** The metro being viewed, when it has a page. */
	public static function current(): ?array {
		static $memo = [];
		$slug = (string) get_query_var( self::QUERY_VAR );
		if ( ! array_key_exists( $slug, $memo ) ) {
			$metro = '' !== $slug ? self::find( $slug ) : null;
			$memo[ $slug ] = $metro && self::has_page( $metro ) ? $metro : null;
		}
		return $memo[ $slug ];
	}

	/** The explore page's radius search, as the "Pick a city" map links pass it. */
	public static function search_args( array $metro ): array {
		return [
			'type'            => Pool_Schema::VENUE_TYPE,
			'search_location' => $metro['location'],
			'lat'             => (string) $metro['lat'],
			'lng'             => (string) $metro['lng'],
			'proximity'       => (string) $metro['radius'],
			'sort'            => 'nearby',
		];
	}

	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * One rule for the metro slugs, ahead of My Listing's region rule. Built when the
	 * rules are (re)generated; a metro whose slug is a state's stays with the state.
	 */
	public static function rules( array $rules ): array {
		$explore = (int) get_option( 'options_general_explore_listings_page' );
		$slugs = [];
		foreach ( array_keys( self::all() ) as $slug ) {
			if ( ! self::state_term( $slug ) ) {
				$slugs[] = preg_quote( $slug, '#' );
			}
		}
		if ( ! $explore || ! $slugs ) {
			return $rules;
		}
		$key = '^' . Locations::REGION_BASE . '/(' . implode( '|', $slugs ) . ')/?$';
		return [ $key => 'index.php?page_id=' . $explore . '&' . self::QUERY_VAR . '=$matches[1]' ] + $rules;
	}

	private static function state_term( string $slug ): ?\WP_Term {
		$term = get_term_by( 'slug', $slug, Pool_Schema::TAX_REGION );
		return $term instanceof \WP_Term && 0 === (int) $term->parent ? $term : null;
	}

	/** The explore page reads its search from the request, so give it the metro's. */
	public static function parse_request( \WP $wp ): void {
		$slug = sanitize_title( (string) ( $wp->query_vars[ self::QUERY_VAR ] ?? '' ) );
		if ( '' === $slug ) {
			return;
		}
		if ( self::state_term( $slug ) ) {
			// A state added after the rules were built: show the state, as before.
			unset( $wp->query_vars[ self::QUERY_VAR ] );
			$wp->query_vars['explore_tab'] = 'regions';
			$wp->query_vars['explore_region'] = $slug;
			return;
		}
		$metro = self::find( $slug );
		if ( ! $metro || ! self::has_page( $metro ) ) {
			return;
		}
		foreach ( self::search_args( $metro ) as $key => $value ) {
			$_GET[ $key ] = $value;
			$_REQUEST[ $key ] = $value;
		}
	}

	/** Metros without a page go to their city page, or the map around them while they are thin. */
	public static function redirect(): void {
		$slug = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $slug ) {
			self::city_redirect();
			return;
		}
		if ( self::current() ) {
			return;
		}
		$metro = self::find( $slug );
		if ( ! $metro ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}
		$city = Stats::city_term( $metro['location'] );
		$link = $city ? get_term_link( $city ) : '';
		$target = $city && ! is_wp_error( $link ) ? $link : Display::explore_url( self::venue_count( $metro ) ? self::search_args( $metro ) : [] );
		wp_safe_redirect( $target, $city ? 301 : 302, 'PlayPoolNation' );
		exit;
	}

	/**
	 * /places/<state>/<city>/ for a metro's main city that has no city page of its own
	 * (Minneapolis and Detroit have no venues inside city limits; Boston has one)
	 * showed the whole directory or a single venue under a noindex. It goes to the
	 * metro page instead, which lists the metro's venues.
	 */
	private static function city_redirect(): void {
		if ( 'regions' !== get_query_var( 'explore_tab' ) ) {
			return;
		}
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		if ( ! preg_match( '#^' . Locations::REGION_BASE . '/([^/]+)/([^/]+)$#', $path, $m ) ) {
			return;
		}
		$state = self::state_term( sanitize_title( $m[1] ) );
		if ( ! $state ) {
			return;
		}
		$city_slug = sanitize_title( $m[2] );
		$city = get_term_by( 'slug', $city_slug, Pool_Schema::TAX_REGION );
		$city = $city instanceof \WP_Term && (int) $city->parent === (int) $state->term_id ? $city : null;
		if ( $city && Locations::venue_count( (int) $city->term_id ) >= Seo::MIN_REGION_VENUES ) {
			return;
		}
		foreach ( self::with_pages() as $metro ) {
			$parts = array_map( 'trim', explode( ',', $metro['location'] ) );
			if ( 2 !== count( $parts ) || Helpers\Address::state_name( $parts[1] ) !== html_entity_decode( $state->name, ENT_QUOTES ) ) {
				continue;
			}
			if ( self::slug( $parts[0] ) === $city_slug || ( $city && html_entity_decode( $city->name, ENT_QUOTES ) === $parts[0] ) ) {
				wp_safe_redirect( self::url( $metro ), 301, 'PlayPoolNation' );
				exit;
			}
		}
	}

	public static function metros_changed(): void {
		delete_option( 'rewrite_rules' ); // Rebuilt with the new slugs on the next request.
		Stats::flush();
		self::patch_sitemap();
	}

	public static function sitemap_written( string $option ): void {
		if ( self::SITEMAP_PENDING === $option ) {
			self::patch_sitemap();
		}
	}

	/**
	 * ThinkRank builds sitemap.xml from posts and terms only and has no filter for
	 * other URLs, so metro pages are added to the file it wrote, after each rebuild.
	 * Entries for metros that no longer have a page are removed, and so are venue
	 * pages that are not indexed (Venue::is_indexable), in case the file was
	 * written before they became noindex.
	 *
	 * @return int Metro URLs in the sitemap, or -1 when there is no flat sitemap.xml to update.
	 */
	public static function patch_sitemap(): int {
		$file = ABSPATH . 'sitemap.xml';
		$xml = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
		$end = strrpos( $xml, '</urlset>' );
		if ( false === $end || ! wp_is_writable( $file ) ) {
			return -1;
		}
		$before = $xml;
		foreach ( Venue::unindexable_ids() as $id ) {
			$xml = (string) preg_replace( '#\s*<url>\s*<loc>' . preg_quote( esc_url( (string) get_permalink( $id ) ), '#' ) . '</loc>.*?</url>#s', '', $xml );
		}
		foreach ( self::all() as $metro ) {
			$xml = (string) preg_replace( '#\s*<url>\s*<loc>' . preg_quote( esc_url( self::url( $metro ) ), '#' ) . '</loc>.*?</url>#s', '', $xml );
		}
		$entries = '';
		$pages = self::with_pages();
		foreach ( $pages as $metro ) {
			$entries .= sprintf(
				"  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n    <priority>0.8</priority>\n    <changefreq>weekly</changefreq>\n  </url>\n",
				esc_url( self::url( $metro ) ),
				gmdate( 'c' )
			);
		}
		$end = (int) strrpos( $xml, '</urlset>' );
		$xml = rtrim( substr( $xml, 0, $end ) ) . "\n" . $entries . substr( $xml, $end );
		if ( $xml !== $before ) {
			file_put_contents( $file, $xml, LOCK_EX );
		}
		return count( $pages );
	}
}
