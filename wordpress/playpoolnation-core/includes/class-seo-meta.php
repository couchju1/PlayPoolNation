<?php
/**
 * Titles, meta descriptions and canonical URLs for directory pages.
 *
 * My Listing renders state/city and filter pages through the explore page
 * (?explore_tab=...), so the SEO plugin treats them all as that one page and
 * points their canonical at it. This class gives each location page its own
 * canonical, title and description, built only from known facts.
 *
 * ThinkRank remains the SEO plugin. Its title and description come from post
 * meta, so this supplies factual values through that meta when an admin has not
 * set one; ThinkRank then prints them (and reuses them for social tags).
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Seo_Meta {

	/** Explore tabs whose pages are filters, not destinations. */
	private const FILTER_TABS = [ 'tags', 'table-size', 'table-brand', 'pool-pricing', 'league-org', 'pool-play', 'game-type', 'event-type', 'instructor-credential', 'lesson-focus', 'lesson-format' ];

	public static function boot(): void {
		add_action( 'init', [ __CLASS__, 'rewrite' ] );
		add_filter( 'rewrite_rules_array', [ __CLASS__, 'city_rule_first' ] );
		// My Listing sets region titles at priority 10000.
		add_filter( 'pre_get_document_title', [ __CLASS__, 'title' ], 10001 );
		add_filter( 'thinkrank_canonical_url', [ __CLASS__, 'canonical' ], 20 );
		add_filter( 'get_post_metadata', [ __CLASS__, 'thinkrank_meta' ], 10, 4 );
		add_action( 'wp_head', [ __CLASS__, 'drop_explore_head' ], 0 );
		// Schema_Org outputs accurate markup; the theme's default is a generic LocalBusiness.
		add_filter( 'mylisting/schema/enable-listing-schema', '__return_false' );
		// ThinkRank prints the listing's social title and description; keep only the theme's listing image.
		add_filter( 'mylisting\\single\\og:tags', static fn( $tags ) => array_intersect_key( (array) $tags, [ 'og:image' => 1 ] ) );
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

	/** Venue-type pages (/category/<type>/), also rendered by the explore page. */
	public static function type_term(): ?\WP_Term {
		if ( 'categories' !== get_query_var( 'explore_tab' ) ) {
			return null;
		}
		$slug = sanitize_title( (string) get_query_var( 'explore_category' ) );
		$term = $slug ? get_term_by( 'slug', $slug, Pool_Schema::TAX_VENUE_TYPE ) : false;
		return $term instanceof \WP_Term ? $term : null;
	}

	private const TYPE_PLURALS = [
		'pool-halls'            => 'Pool Halls',
		'billiards-lounge'      => 'Billiards Lounges',
		'bars-with-pool-tables' => 'Bars with Pool Tables',
		'sports-bar'            => 'Sports Bars with Pool Tables',
		'bowling-center'        => 'Bowling Centers with Pool Tables',
		'recreation'            => 'Recreation Centers with Pool Tables',
		'private-clubs'         => 'Private Clubs with Pool Tables',
	];

	private static function type_label( \WP_Term $term ): string {
		return self::TYPE_PLURALS[ $term->slug ] ?? html_entity_decode( $term->name, ENT_QUOTES );
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

	/** Site name without the tagline the site title carries. */
	public static function brand(): string {
		$name = html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		return trim( explode( '|', $name )[0] ) ?: $name;
	}

	public static function title( $title ) {
		$computed = self::computed_title();
		return '' !== $computed ? $computed : $title;
	}

	public static function computed_title(): string {
		$site = self::brand();
		$term = self::region_term();
		if ( $term ) {
			return sprintf( 'Pool Halls & Places to Play Pool in %s | %s', self::region_label( $term ), $site );
		}
		$type = self::type_term();
		if ( $type ) {
			return sprintf( '%s Across the U.S. | %s', self::type_label( $type ), $site );
		}
		if ( is_singular( Pool_Schema::POST_TYPE ) ) {
			$id = (int) get_queried_object_id();
			$v = Venue::get( $id );
			if ( $v && Venue::is_venue( $id ) && $v->city_state() ) {
				$kind = in_array( $v->primary_type(), [ '', 'Other' ], true ) ? 'Place to Play Pool' : $v->primary_type();
				return sprintf( '%s: %s in %s | %s', $v->name(), $kind, $v->city_state(), $site );
			}
			$type = Venue::listing_type( $id );
			if ( $v && Pool_Schema::TOURNAMENT_TYPE === $type ) {
				$venue = Venue::get( (int) get_post_meta( $id, '_ppn_venue_id', true ) );
				$at = $venue ? ' at ' . $venue->name() . ( $venue->city_state() ? ', ' . $venue->city_state() : '' ) : '';
				return sprintf( '%s: %s%s | %s', $v->name(), Events::event_type_label( $id ), $at, $site );
			}
			if ( $v && Pool_Schema::INSTRUCTOR_TYPE === $type ) {
				return sprintf( '%s: Pool Instructor%s | %s', $v->name(), $v->city_state() ? ' in ' . $v->city_state() : '', $site );
			}
		}
		if ( is_front_page() ) {
			return sprintf( '%s: Find Pool Halls & Bars with Pool Tables Near You', $site );
		}
		$pages = [
			'places'          => 'Find Pool Halls & Bars with Pool Tables',
			'events'          => 'Pool Tournaments & Events Near You',
			'leagues'         => 'Pool Leagues Near You',
			'instructors'     => 'Pool Instructors & Lessons Near You',
			'post-an-event'   => 'Post a Pool Tournament or Event',
			Instructors::SIGNUP_PAGE => 'Create Your Pool Instructor Profile',
			'add-a-venue'     => 'Add a Place to Play Pool',
			'list-your-venue' => 'List Your Pool Hall or Bar',
		];
		if ( is_page() && ! self::is_filter_page() && ! get_query_var( 'explore_tab' ) ) {
			$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
			if ( isset( $pages[ $slug ] ) ) {
				return $pages[ $slug ] . ' | ' . $site;
			}
		}
		return '';
	}

	public static function canonical( $url ) {
		$term = self::region_term() ?: self::type_term();
		if ( $term ) {
			$link = get_term_link( $term );
			return is_wp_error( $link ) ? $url : $link;
		}
		return $url;
	}

	public static function description(): string {
		if ( is_front_page() ) {
			return 'Find your next place to play pool. Search pool halls, billiards clubs and bars with pool tables across the U.S. by table size, brand, leagues and hours.';
		}
		$type = self::type_term();
		if ( $type && $type->count > 0 ) {
			return sprintf( '%d %s listed on PlayPoolNation, with table sizes and brands where known, hours, leagues and directions.', (int) $type->count, strtolower( self::type_label( $type ) ) );
		}
		$term = self::region_term();
		if ( $term ) {
			$count = Locations::venue_count( (int) $term->term_id );
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
			if ( Pool_Schema::TOURNAMENT_TYPE === $type ) {
				return self::event_description( $v );
			}
			if ( Pool_Schema::INSTRUCTOR_TYPE === $type ) {
				return self::instructor_description( $v );
			}
			if ( Pool_Schema::LEAGUE_TYPE === $type ) {
				$venue = Venue::get( (int) get_post_meta( $id, '_ppn_venue_id', true ) );
				return trim( sprintf( 'Pool league at %s%s.', $venue ? $venue->name() : 'a local venue', $venue && $venue->city_state() ? ' in ' . $venue->city_state() : '' ) );
			}
		}
		$pages = [
			'places'          => 'Find pool halls, billiards clubs and bars with pool tables near you. Filter by table size, table brand, leagues, open now and more.',
			'events'          => 'Upcoming pool tournaments and events near you: weekly 8-ball and 9-ball tournaments, league sign-ups, clinics and more, with entry fees and added money where known.',
			'instructors'     => 'Find a pool instructor near you. Compare certifications, what they teach, lesson formats and rates.',
			'post-an-event'   => 'Post a pool tournament or event. Venue owners with a claimed listing publish instantly; other events are checked first.',
			Instructors::SIGNUP_PAGE => 'Teach pool? Create a free instructor profile on PlayPoolNation so players near you can find your lessons.',
			'leagues'         => 'Pool leagues near you, including APA, BCA, USAPL and local leagues, with the night they play.',
			'add-a-venue'     => 'Know a place to play pool that is missing from PlayPoolNation? Add it and we will check it before it goes on the map.',
			'list-your-venue' => 'Own or manage a pool hall or bar with pool tables? List it on PlayPoolNation with tables, pricing, leagues and hours.',
		];
		if ( is_page() ) {
			// Filter tabs and unknown regions render the explore page itself.
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

	/** e.g. "Tournament at Rack City Billiards in Sioux Falls, SD. Friday, October 10 at 7 PM. 9-Ball, $20 entry, $200 added." */
	public static function event_description( Venue $e ): string {
		$id = $e->id();
		$venue = Venue::get( (int) get_post_meta( $id, '_ppn_venue_id', true ) );
		$text = sprintf( '%s at %s%s.', Events::event_type_label( $id ), $venue ? $venue->name() : 'a local venue', $venue && $venue->city_state() ? ' in ' . $venue->city_state() : '' );
		$next = Play::upcoming_from_ids( [ $id ], 1 );
		if ( $next ) {
			$when = $next[0]['when'];
			$text .= ' ' . ( $next[0]['recurring'] ? 'Next: ' : '' ) . wp_date( 'l, F j', $when->getTimestamp() ) . ' at ' . Helpers\Format::clock( (int) $when->format( 'G' ) * 60 + (int) $when->format( 'i' ) ) . '.';
		}
		$facts = array_filter( [
			Helpers\Format::join_list( $e->term_names( 'game-type' ) ),
			Helpers\Format::money( $e->field( 'entry-fee' ) ) ? Helpers\Format::money( $e->field( 'entry-fee' ) ) . ' entry' : '',
			Helpers\Format::money( $e->field( 'added-money' ) ) ? Helpers\Format::money( $e->field( 'added-money' ) ) . ' added' : '',
		] );
		return $text . ( $facts ? ' ' . implode( ', ', $facts ) . '.' : '' );
	}

	/** e.g. "Jane Doe teaches pool in Sioux Falls, SD: beginners and position play. One-on-one and online lessons. From $60 an hour." */
	public static function instructor_description( Venue $i ): string {
		$focus = array_slice( $i->term_names( 'lesson-focus' ), 0, 3 );
		$text = sprintf( '%s teaches pool%s%s.', $i->name(), $i->city_state() ? ' in ' . $i->city_state() : '', $focus ? ': ' . strtolower( Helpers\Format::join_list( $focus ) ) : '' );
		$verified = array_values( array_filter( Instructors::credentials( $i->id() ), static fn( $c ) => '' !== $c['verified'] ) );
		if ( $verified ) {
			$text .= ' ' . $verified[0]['name'] . ' (verified).';
		}
		$formats = $i->term_names( 'lesson-format' );
		if ( $formats ) {
			$text .= ' ' . ucfirst( strtolower( Helpers\Format::join_list( $formats ) ) ) . ' lessons.';
		}
		$rate = Helpers\Format::money( $i->field( 'lesson-rate' ) );
		if ( $rate ) {
			$text .= ' From ' . $rate . ' an hour.';
		}
		return $text;
	}

	/**
	 * Supply ThinkRank's title and description meta for the page being viewed
	 * when none is stored, so it does not fall back to raw page content.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function thinkrank_meta( $value, $object_id, $meta_key, $single ) {
		static $busy = false;
		if ( $busy || ( '_thinkrank_meta_description' !== $meta_key && '_thinkrank_seo_title' !== $meta_key ) ) {
			return $value;
		}
		if ( is_admin() || ! did_action( 'wp' ) || (int) $object_id !== (int) get_queried_object_id() ) {
			return $value;
		}
		$busy = true;
		try {
			if ( '' !== (string) get_post_meta( $object_id, $meta_key, true ) ) {
				return $value; // An admin-set value wins.
			}
			$text = '_thinkrank_seo_title' === $meta_key ? self::computed_title() : self::description();
		} finally {
			$busy = false;
		}
		if ( '' === $text ) {
			return $value;
		}
		return [ $text ];
	}

	/** My Listing prints its own description and social tags on region and filter pages, duplicating ThinkRank's. */
	public static function drop_explore_head(): void {
		if ( ! get_query_var( 'explore_tab' ) ) {
			return;
		}
		global $wp_filter;
		foreach ( (array) ( $wp_filter['wp_head']->callbacks[1] ?? [] ) as $cb ) {
			if ( $cb['function'] instanceof \Closure ) {
				$file = ( new \ReflectionFunction( $cb['function'] ) )->getFileName();
				if ( $file && str_ends_with( wp_normalize_path( $file ), 'includes/src/explore.php' ) ) {
					remove_action( 'wp_head', $cb['function'], 1 );
				}
			}
		}
	}
}
