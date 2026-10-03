<?php
/**
 * Titles, meta descriptions and canonical URLs for directory pages.
 *
 * My Listing renders state/city and filter pages through the explore page
 * (?explore_tab=...), so the SEO plugin treats them all as that one page and
 * points their canonical at it. This class gives each location page its own
 * canonical, title and description, built only from known facts.
 *
 * ThinkRank remains the SEO plugin; this hooks its documented filters and only
 * replaces its meta description output on these directory contexts.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Seo_Meta {

	/** Explore tabs whose pages are filters, not destinations. */
	private const FILTER_TABS = [ 'tags', 'table-size', 'table-brand', 'pool-pricing', 'league-org', 'pool-play', 'game-type' ];

	public static function boot(): void {
		add_action( 'init', [ __CLASS__, 'rewrite' ] );
		add_filter( 'rewrite_rules_array', [ __CLASS__, 'city_rule_first' ] );
		add_filter( 'pre_get_document_title', [ __CLASS__, 'title' ], 20 );
		add_filter( 'thinkrank_canonical_url', [ __CLASS__, 'canonical' ], 20 );
		add_action( 'wp', [ __CLASS__, 'take_over_description' ] );
	}

	/** /places/<state>/<city>/ shows the city (My Listing's own rule only reads the first segment). */
	public static function rewrite(): void {
		$explore = (int) get_option( 'options_general_explore_listings_page' );
		if ( $explore ) {
			add_rewrite_rule( '^' . Locations::REGION_BASE . '/([^/]+)/([^/]+)/?$', 'index.php?page_id=' . $explore . '&explore_tab=regions&explore_region=$matches[2]', 'top' );
		}
	}

	/** My Listing's own region rule has no end anchor, so the city rule must be checked before it. */
	public static function city_rule_first( array $rules ): array {
		$key = '^' . Locations::REGION_BASE . '/([^/]+)/([^/]+)/?$';
		if ( isset( $rules[ $key ] ) ) {
			$rules = [ $key => $rules[ $key ] ] + $rules;
		}
		return $rules;
	}

	public static function region_term(): ?\WP_Term {
		if ( 'regions' !== get_query_var( 'explore_tab' ) ) {
			return null;
		}
		$slug = sanitize_title( (string) get_query_var( 'explore_region' ) );
		$term = $slug ? get_term_by( 'slug', $slug, Pool_Schema::TAX_REGION ) : false;
		return $term instanceof \WP_Term ? $term : null;
	}

	public static function is_filter_page(): bool {
		return in_array( (string) get_query_var( 'explore_tab' ), self::FILTER_TABS, true );
	}

	private static function region_label( \WP_Term $term ): string {
		$name = html_entity_decode( $term->name, ENT_QUOTES );
		if ( $term->parent ) {
			$parent = get_term( $term->parent, Pool_Schema::TAX_REGION );
			if ( $parent instanceof \WP_Term ) {
				$abbr = Helpers\Address::state_abbr( $parent->name );
				$name .= ', ' . ( $abbr ?: html_entity_decode( $parent->name, ENT_QUOTES ) );
			}
		}
		return $name;
	}

	public static function title( $title ) {
		$site = get_bloginfo( 'name' );
		$term = self::region_term();
		if ( $term ) {
			return sprintf( 'Pool Halls & Places to Play Pool in %s | %s', self::region_label( $term ), $site );
		}
		if ( is_singular( Pool_Schema::POST_TYPE ) ) {
			$id = (int) get_queried_object_id();
			$v = Venue::get( $id );
			if ( $v && Venue::is_venue( $id ) && $v->city_state() ) {
				$kind = in_array( $v->primary_type(), [ '', 'Other' ], true ) ? 'Place to Play Pool' : $v->primary_type();
				return sprintf( '%s: %s in %s | %s', $v->name(), $kind, $v->city_state(), $site );
			}
		}
		return $title;
	}

	public static function canonical( $url ) {
		$term = self::region_term();
		if ( $term ) {
			$link = get_term_link( $term );
			return is_wp_error( $link ) ? $url : $link;
		}
		return $url;
	}

	public static function description(): string {
		$term = self::region_term();
		if ( $term ) {
			$count = (int) $term->count;
			$where = self::region_label( $term );
			return sprintf(
				'%s in %s. See table sizes and brands where known, hours, and who is open right now.',
				1 === $count ? 'A place to play pool' : sprintf( '%d places to play pool, from pool halls to bars with tables,', $count ),
				$where
			);
		}
		if ( is_singular( Pool_Schema::POST_TYPE ) ) {
			$id = (int) get_queried_object_id();
			$v = Venue::get( $id );
			if ( ! $v ) {
				return '';
			}
			$type = Venue::listing_type( $id );
			if ( Pool_Schema::VENUE_TYPE === $type ) {
				return self::venue_description( $v );
			}
			if ( in_array( $type, [ Pool_Schema::TOURNAMENT_TYPE, Pool_Schema::LEAGUE_TYPE ], true ) ) {
				$venue = Venue::get( (int) get_post_meta( $id, '_ppn_venue_id', true ) );
				$what = Pool_Schema::TOURNAMENT_TYPE === $type ? 'Pool tournament' : 'Pool league';
				return trim( sprintf( '%s at %s%s.', $what, $venue ? $venue->name() : 'a local venue', $venue && $venue->city_state() ? ' in ' . $venue->city_state() : '' ) );
			}
		}
		$pages = [
			'places'          => 'Find pool halls, billiards clubs and bars with pool tables near you. Filter by table size, table brand, leagues, open now and more.',
			'tournaments'     => 'Upcoming pool tournaments near you, with game, entry fee and added money where known.',
			'leagues'         => 'Pool leagues near you, including APA, BCA, USAPL and local leagues, with the night they play.',
			'add-a-venue'     => 'Know a place to play pool that is missing from PlayPoolNation? Add it and we will check it before it goes on the map.',
			'list-your-venue' => 'Own or manage a pool hall or bar with pool tables? List it on PlayPoolNation with tables, pricing, leagues and hours.',
		];
		if ( is_page() && ! self::is_filter_page() && ! get_query_var( 'explore_tab' ) ) {
			$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
			return $pages[ $slug ] ?? '';
		}
		return '';
	}

	/** Factual one-liner from known data, e.g. "Rack City Billiards is a pool hall in Sioux Falls, SD with 19 pool tables..." */
	public static function venue_description( Venue $v ): string {
		$type = strtolower( $v->primary_type() );
		$type = ( '' === $type || 'other' === $type ) ? 'place to play pool' : $type;
		$parts = [ sprintf( '%s is a %s%s', $v->name(), 'bar' === $type ? 'bar with pool tables' : $type, $v->city_state() ? ' in ' . $v->city_state() : '' ) ];
		$facts = [];
		$total = $v->total_tables();
		if ( $total ) {
			$facts[] = sprintf( _n( '%d pool table', '%d pool tables', $total, 'playpoolnation-core' ), $total );
		}
		$brands = $v->term_names( 'table-brand' );
		$sizes = Helpers\Format::sizes_label( $v->size_slugs() );
		if ( $brands || $sizes ) {
			$facts[] = trim( Helpers\Format::join_list( $brands ) . ' ' . $sizes ) . ' tables';
		}
		$text = $parts[0] . ( $facts ? ' with ' . implode( ', ', $facts ) : '' ) . '.';
		$extras = [ 'hours', 'phone', 'directions' ];
		if ( in_array( 'leagues', $v->term_slugs( 'pool-play' ), true ) ) {
			$extras[] = 'leagues';
		}
		if ( in_array( 'tournaments', $v->term_slugs( 'pool-play' ), true ) ) {
			$extras[] = 'upcoming tournaments';
		}
		return $text . ' ' . ucfirst( Helpers\Format::join_list( $extras ) ) . ' on PlayPoolNation.';
	}

	/** Replace ThinkRank's content-derived meta description where we know better. */
	public static function take_over_description(): void {
		if ( '' === self::description() ) {
			return;
		}
		global $wp_filter;
		foreach ( (array) ( $wp_filter['wp_head']->callbacks[1] ?? [] ) as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && 'output_meta_description' === $fn[1] ) {
				remove_action( 'wp_head', $fn, 1 );
			}
		}
		add_action( 'wp_head', static function () {
			echo '<meta name="description" content="' . esc_attr( self::description() ) . '" />' . "\n";
		}, 1 );
	}
}
