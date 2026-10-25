<?php
/**
 * City pages: /places/<state>/<city>/ as one long page about where to play pool
 * in the city, rendered here instead of the explore page's map listing.
 *
 * The page has the H1, an opening paragraph from the data, a "Find a table"
 * picker (filter chips and a jump list of plain anchor links), a summary table,
 * one section per venue (id = the venue's name as a slug), league nights, a FAQ
 * from the data, links to hub pages and nearby cities, and ItemList,
 * BreadcrumbList and FAQPage (two or more questions) schema. It is complete
 * without JavaScript; assets/ppn.js adds the filters, table sorting and the map,
 * which loads Google Maps only when the visitor asks for it.
 *
 * Words come from Helpers\City_Text, from sourced facts only (About::facts()).
 * The venue About text is not repeated here.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Address;
use PlayPoolNation\Core\Helpers\City_Text;
use PlayPoolNation\Core\Helpers\Format;

defined( 'ABSPATH' ) || exit;

final class City_Page {

	/** Nearby city and metro pages within this many miles are linked. */
	private const NEARBY_MILES = 300;
	private const NEARBY_LIMIT = 6;

	/** Explore page assets the city page does not use (handles as My Listing and Elementor register them). */
	private const EXPLORE_SCRIPTS = [ 'ml:explore', 'ml:dialog' ];
	private const EXPLORE_STYLES  = [ 'mylisting-explore-widget', 'ml:jquery-ui', 'widget-heading' ];

	public static function boot(): void {
		add_filter( 'template_include', [ __CLASS__, 'template' ], 99 );
		add_filter( 'body_class', [ __CLASS__, 'body_class' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'drop_explore_assets' ], 1000 );
		add_action( 'wp_print_footer_scripts', [ __CLASS__, 'drop_explore_assets' ], 1 );
		add_action( 'wp_head', [ __CLASS__, 'schema' ], 30 );
	}

	/** The city being viewed: a city region term with at least one venue. */
	public static function current(): ?\WP_Term {
		static $memo = [];
		$term = Seo_Meta::region_term();
		if ( ! $term || ! $term->parent ) {
			return null;
		}
		if ( ! array_key_exists( $term->term_id, $memo ) ) {
			$memo[ $term->term_id ] = Locations::venue_count( (int) $term->term_id ) > 0 ? $term : null;
		}
		return $memo[ $term->term_id ];
	}

	public static function template( $template ) {
		return self::current() ? PPN_CORE_DIR . '/templates/city-page.php' : $template;
	}

	public static function body_class( array $classes ): array {
		if ( self::current() ) {
			$classes[] = 'ppn-city-page';
		}
		return $classes;
	}

	public static function drop_explore_assets(): void {
		if ( ! self::current() ) {
			return;
		}
		foreach ( self::EXPLORE_SCRIPTS as $handle ) {
			wp_dequeue_script( $handle );
		}
		$explore = (int) get_option( 'options_general_explore_listings_page' );
		foreach ( array_merge( self::EXPLORE_STYLES, [ 'elementor-post-' . $explore ] ) as $handle ) {
			wp_dequeue_style( $handle );
		}
	}

	/* --------------------------------------------------------------- data */

	/** "Sioux Falls, SD". */
	public static function where( \WP_Term $term ): string {
		$state = get_term( $term->parent, Pool_Schema::TAX_REGION );
		$abbr = $state instanceof \WP_Term ? Address::state_abbr( $state->name ) : '';
		return html_entity_decode( $term->name, ENT_QUOTES ) . ( $abbr ? ', ' . $abbr : '' );
	}

	/** @return int[] published venues in the city */
	public static function venue_ids( \WP_Term $term ): array {
		return array_map( 'intval', get_posts( [
			'post_type'      => Pool_Schema::POST_TYPE,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'no_found_rows'  => true,
			'tax_query'      => [ [ 'taxonomy' => Pool_Schema::TAX_REGION, 'terms' => [ (int) $term->term_id ], 'include_children' => false ] ],
			'meta_query'     => [ [ 'key' => '_case27_listing_type', 'value' => Pool_Schema::VENUE_TYPE ] ],
		] ) );
	}

	/**
	 * Every venue's facts for the page, most tables first, then by name.
	 *
	 * @return array<int,array>
	 */
	public static function venues( \WP_Term $term ): array {
		static $memo = [];
		if ( isset( $memo[ $term->term_id ] ) ) {
			return $memo[ $term->term_id ];
		}
		$ids = self::venue_ids( $term );
		Venue::prime( $ids );
		$out = [];
		$anchors = [];
		foreach ( $ids as $id ) {
			$v = Venue::get( $id );
			if ( ! $v ) {
				continue;
			}
			$facts = self::facts( $v );
			$anchor = $facts['anchor'];
			for ( $i = 2; isset( $anchors[ $anchor ] ); $i++ ) {
				$anchor = $facts['anchor'] . '-' . $i;
			}
			$anchors[ $anchor ] = true;
			$facts['anchor'] = $anchor;
			$out[] = $facts;
		}
		usort( $out, static fn( $a, $b ) => [ City_Text::tables( $b ) ?? -1, $a['name'] ] <=> [ City_Text::tables( $a ) ?? -1, $b['name'] ] );
		return $memo[ $term->term_id ] = $out;
	}

	/** Sourced facts (About::facts) plus what the page links and filters on. */
	public static function facts( Venue $v ): array {
		$id = $v->id();
		$facts = About::facts( $v );
		$sources = Provenance::field_sources( $id );
		$site = $v->website();
		$loc = $v->location();
		$leagues = [];
		foreach ( Play::leagues( $id ) as $l ) {
			$day = array_search( $l['day'], array_values( Pool_Schema::LEAGUE_DAYS ), true );
			$leagues[] = [ 'day' => false === $day ? null : (int) $day, 'time' => (string) $l['time'], 'orgs' => array_values( (array) $l['orgs'] ), 'name' => (string) $l['name'] ];
		}
		return array_merge( $facts, [
			'id'      => $id,
			'anchor'  => Format::slugify( $v->name() ),
			'url'     => $v->url(),
			'address' => $v->address(),
			'phone'   => $v->phone(),
			'website' => '' === $site ? '' : ( Contact_Links::https_url( $id, $site ) ?: $site ),
			'lat'     => $loc ? (float) $loc->lat : null,
			'lng'     => $loc ? (float) $loc->lng : null,
			'food'    => in_array( 'food', $v->term_slugs( Pool_Schema::TAX_AMENITY ), true ) && isset( $sources[ Pool_Schema::TAX_AMENITY . ':food' ] ),
			'leagues' => $leagues,
			'venue'   => $v,
		] );
	}

	/* ------------------------------------------------------------- render */

	public static function html(): string {
		$term = self::current();
		if ( ! $term ) {
			return '';
		}
		$city = html_entity_decode( $term->name, ENT_QUOTES );
		$where = self::where( $term );
		$venues = self::venues( $term );
		$h = '<main id="ppn-main" class="ppn-city" data-ppn-city>';
		$h .= self::breadcrumbs( $term );
		$h .= '<header class="ppn-city-head"><h1>' . esc_html( sprintf( 'Pool halls and bars with pool tables in %s', $where ) ) . '</h1>';
		$h .= '<p class="ppn-city-intro">' . esc_html( City_Text::intro( $city, $venues ) ) . '</p></header>';
		$h .= self::picker( $venues, $where );
		$h .= self::table( $venues );
		$h .= self::map( $venues, $where );
		$h .= '<div class="ppn-city-venues">';
		foreach ( $venues as $v ) {
			$h .= self::section( $v );
		}
		$h .= '</div>';
		$h .= self::league_block( $city, $venues );
		$h .= self::faq_block( $city, $venues );
		$h .= self::links_block( $term, $city );
		return $h . '</main>';
	}

	private static function breadcrumbs( \WP_Term $term ): string {
		$items = '';
		foreach ( self::crumbs( $term ) as $i => [ $name, $url ] ) {
			$items .= '<li>' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : '<span aria-current="page">' . esc_html( $name ) . '</span>' ) . '</li>';
		}
		return '<nav class="ppn-crumbs" aria-label="Breadcrumb"><ol>' . $items . '</ol></nav>';
	}

	/** @return array<int,array{0:string,1:string}> name, url ('' for the page itself) */
	public static function crumbs( \WP_Term $term ): array {
		$out = [ [ 'Home', home_url( '/' ) ], [ 'Places', Display::explore_url() ] ];
		$state = get_term( $term->parent, Pool_Schema::TAX_REGION );
		if ( $state instanceof \WP_Term ) {
			$link = get_term_link( $state );
			$out[] = [ html_entity_decode( $state->name, ENT_QUOTES ), is_wp_error( $link ) ? '' : $link ];
		}
		$out[] = [ html_entity_decode( $term->name, ENT_QUOTES ), '' ];
		return $out;
	}

	/** Filter chips (with JavaScript) and the jump list (plain links). */
	private static function picker( array $venues, string $where ): string {
		$chips = [
			'nine'    => [ '9-foot tables', static fn( $v ) => City_Text::has_size( $v, '9-foot' ) ],
			'open'    => [ 'Open now', static fn( $v ) => (bool) $v['hours'] ],
			'tonight' => [ 'Leagues tonight', static fn( $v ) => (bool) City_Text::league_days( $v ) ],
			'free'    => [ 'Free pool', [ City_Text::class, 'free_pool' ] ],
			'food'    => [ 'Food', static fn( $v ) => ! empty( $v['food'] ) ],
		];
		$buttons = '';
		foreach ( $chips as $key => [ $label, $test ] ) {
			if ( array_filter( $venues, $test ) ) {
				$buttons .= '<button type="button" class="ppn-city-chip" aria-pressed="false" data-ppn-filter="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
			}
		}
		$h = '<section class="ppn-city-picker" aria-labelledby="ppn-find">';
		$h .= '<h2 id="ppn-find">Find a table</h2>';
		if ( $buttons ) {
			$h .= '<div class="ppn-city-filters" data-ppn-city-filters hidden><div class="ppn-city-chips" role="group" aria-label="Filter places">' . $buttons . '</div><p class="ppn-city-count" aria-live="polite"></p></div>';
		}
		$h .= '<nav class="ppn-city-jump" aria-label="' . esc_attr( 'Places in ' . $where ) . '"><p class="ppn-city-jump-label">Jump to a place</p><ul>';
		foreach ( $venues as $v ) {
			$h .= '<li data-ppn-venue="' . esc_attr( $v['anchor'] ) . '"><a href="#' . esc_attr( $v['anchor'] ) . '">' . esc_html( $v['name'] ) . '</a></li>';
		}
		return $h . '</ul></nav></section>';
	}

	/** data-* attributes the filters read. */
	private static function filter_attrs( array $v ): string {
		$tags = array_keys( array_filter( [
			'nine' => City_Text::has_size( $v, '9-foot' ),
			'free' => City_Text::free_pool( $v ),
			'food' => ! empty( $v['food'] ),
		] ) );
		return ' data-ppn-venue="' . esc_attr( $v['anchor'] ) . '" data-ppn-tags="' . esc_attr( implode( ' ', $tags ) ) . '" data-ppn-league-days="' . esc_attr( implode( ' ', City_Text::league_days( $v ) ) ) . '"';
	}

	private static function status_chip( array $v ): string {
		$venue = $v['venue'];
		$open = $venue->open_status();
		if ( '' === $open['label'] ) {
			return '';
		}
		return '<span class="ppn-fact ppn-fact--' . esc_attr( $open['state'] ) . '"' . Open_Status::attr( $venue ) . '>' . esc_html( $open['label'] ) . '</span>';
	}

	private static function table( array $venues ): string {
		$h = '<section class="ppn-city-summary" aria-labelledby="ppn-summary"><h2 id="ppn-summary">At a glance</h2>';
		$h .= '<table class="ppn-city-table"><thead><tr>';
		$h .= '<th scope="col">Place</th><th scope="col">Type</th><th scope="col" aria-sort="descending" data-ppn-sort-col><button type="button" data-ppn-sort hidden>Tables</button><span data-ppn-sort-label>Tables</span></th><th scope="col">Sizes</th><th scope="col">Brands</th><th scope="col">League nights</th><th scope="col">Now</th>';
		$h .= '</tr></thead><tbody>';
		foreach ( $venues as $i => $v ) {
			$tables = City_Text::tables( $v );
			$days = array_map( static fn( $d ) => City_Text::DAY_SHORT[ $d ], City_Text::league_days( $v ) );
			$h .= '<tr' . self::filter_attrs( $v ) . ' data-ppn-tables="' . esc_attr( null === $tables ? '-1' : (string) $tables ) . '" data-ppn-order="' . (int) $i . '">';
			$h .= '<th scope="row" data-label="Place"><a href="#' . esc_attr( $v['anchor'] ) . '">' . esc_html( $v['name'] ) . '</a></th>';
			$h .= self::cell( 'Type', City_Text::type_label( (string) $v['type'] ) );
			$h .= self::cell( 'Tables', null === $tables ? '' : (string) $tables );
			$h .= self::cell( 'Sizes', Format::sizes_label( City_Text::sizes( $v ) ) );
			$h .= self::cell( 'Brands', Format::join_list( City_Text::brands( $v ) ) );
			$h .= self::cell( 'League nights', implode( ', ', $days ) );
			$h .= '<td data-label="Now">' . self::status_chip( $v ) . '</td>';
			$h .= '</tr>';
		}
		return $h . '</tbody></table></section>';
	}

	private static function cell( string $label, string $value ): string {
		return '<td data-label="' . esc_attr( $label ) . '"' . ( '' === $value ? ' class="ppn-empty-cell"' : '' ) . '>' . esc_html( $value ) . '</td>';
	}

	/** "Show map": the explore map for the city without JavaScript, a map on this page with it. */
	private static function map( array $venues, string $where ): string {
		$points = [];
		foreach ( $venues as $v ) {
			if ( null !== $v['lat'] && null !== $v['lng'] ) {
				$points[] = [ 'n' => $v['name'], 'a' => $v['anchor'], 'lat' => round( $v['lat'], 5 ), 'lng' => round( $v['lng'], 5 ) ];
			}
		}
		if ( ! $points ) {
			return '';
		}
		$lat = array_sum( array_column( $points, 'lat' ) ) / count( $points );
		$lng = array_sum( array_column( $points, 'lng' ) ) / count( $points );
		$url = Display::explore_url( [ 'type' => Pool_Schema::VENUE_TYPE, 'search_location' => $where, 'lat' => (string) round( $lat, 5 ), 'lng' => (string) round( $lng, 5 ), 'proximity' => '15', 'sort' => 'nearby' ] );
		return '<div class="ppn-city-mapbox"><a class="ppn-button ppn-button--ghost" href="' . esc_url( $url ) . '" data-ppn-show-map>Show map</a>'
			. '<div class="ppn-city-map" data-ppn-city-map hidden data-points="' . esc_attr( (string) wp_json_encode( $points ) ) . '"></div></div>';
	}

	private static function section( array $v ): string {
		$h = '<section class="ppn-city-venue" id="' . esc_attr( $v['anchor'] ) . '" data-listing-id="' . (int) $v['id'] . '"' . self::filter_attrs( $v ) . '>';
		$h .= '<h2>' . esc_html( $v['name'] ) . '</h2>';
		$h .= self::status_chip( $v );
		$h .= '<dl class="ppn-city-facts">';
		foreach ( City_Text::fact_lines( $v ) as [ $label, $value ] ) {
			$h .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
		}
		$h .= '</dl>';
		$week = City_Text::week_hours( (array) $v['hours'] );
		if ( $week && in_array( $v['status'], [ '', 'open' ], true ) ) {
			$h .= '<details class="ppn-city-hours"><summary>Hours</summary><table><tbody>';
			foreach ( $week as $row ) {
				$h .= '<tr><th scope="row">' . esc_html( $row['day'] ) . '</th><td>' . esc_html( $row['hours'] ) . '</td></tr>';
			}
			$h .= '</tbody></table></details>';
		}
		$h .= '<p class="ppn-city-actions">';
		$tel = Format::e164( (string) $v['phone'] );
		if ( '' !== $tel ) {
			$h .= '<a class="ppn-button" href="tel:' . esc_attr( $tel ) . '" data-ppn-event="call">Call</a>';
		}
		if ( '' !== trim( (string) $v['address'] ) ) {
			$h .= '<a class="ppn-button ppn-button--ghost" href="' . esc_url( Format::directions_url( (string) $v['address'] ) ) . '" target="_blank" rel="noopener" data-ppn-event="directions">Directions</a>';
		}
		if ( '' !== $v['website'] ) {
			$h .= '<a class="ppn-button ppn-button--ghost" href="' . esc_url( $v['website'] ) . '" target="_blank" rel="nofollow noopener" data-ppn-event="website">Website</a>';
		}
		$h .= '</p><p class="ppn-city-more"><a href="' . esc_url( $v['url'] ) . '">' . esc_html( 'More about ' . $v['name'] ) . '</a></p>';
		return $h . '</section>';
	}

	private static function league_block( string $city, array $venues ): string {
		$nights = City_Text::league_nights( $venues );
		if ( ! $nights ) {
			return '';
		}
		$h = '<section class="ppn-city-leagues" aria-labelledby="ppn-leagues"><h2 id="ppn-leagues">' . esc_html( 'League nights in ' . $city ) . '</h2><dl>';
		foreach ( $nights as $day => $lines ) {
			$h .= '<div><dt>' . esc_html( $day ) . '</dt>';
			foreach ( $lines as $line ) {
				$h .= '<dd>' . esc_html( $line ) . '</dd>';
			}
			$h .= '</div>';
		}
		return $h . '</dl></section>';
	}

	private static function faq_block( string $city, array $venues ): string {
		$faq = City_Text::faq( $city, $venues );
		if ( ! $faq ) {
			return '';
		}
		$h = '<section class="ppn-city-faq" aria-labelledby="ppn-faq"><h2 id="ppn-faq">Questions</h2>';
		foreach ( $faq as $item ) {
			$h .= '<h3>' . esc_html( $item['q'] ) . '</h3><p>' . esc_html( $item['a'] ) . '</p>';
		}
		return $h . '</section>';
	}

	private static function links_block( \WP_Term $term, string $city ): string {
		$hubs = self::hub_links();
		$near = self::nearby( $term );
		if ( ! $hubs && ! $near ) {
			return '';
		}
		$h = '<section class="ppn-city-links" aria-labelledby="ppn-more"><h2 id="ppn-more">' . esc_html( 'More pool near ' . $city ) . '</h2>';
		if ( $hubs ) {
			$h .= '<ul class="ppn-chips ppn-chips--links">';
			foreach ( $hubs as [ $label, $url ] ) {
				$h .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
			}
			$h .= '</ul>';
		}
		if ( $near ) {
			$h .= '<h3>Nearby cities</h3><ul class="ppn-chips ppn-chips--links">';
			foreach ( $near as $n ) {
				$h .= '<li><a href="' . esc_url( $n['url'] ) . '">' . esc_html( $n['name'] ) . '</a> <span class="ppn-city-miles">' . esc_html( sprintf( '%d miles', $n['miles'] ) ) . '</span></li>';
			}
			$h .= '</ul>';
		}
		return $h . '</section>';
	}

	/** Hub pages that exist and are indexed. @return array<int,array{0:string,1:string}> */
	public static function hub_links(): array {
		$out = [];
		foreach ( [ 'leagues' => 'Pool leagues', 'events' => 'Tournaments and events', 'instructors' => 'Pool lessons' ] as $slug => $label ) {
			$page = get_page_by_path( $slug );
			if ( ! $page || 'publish' !== $page->post_status || in_array( (int) $page->ID, Seo::noindex_page_ids(), true ) ) {
				continue;
			}
			if ( 'instructors' === $slug && empty( Stats::stats()['instructors'] ) ) {
				continue;
			}
			$out[] = [ $label, (string) get_permalink( $page ) ];
		}
		return $out;
	}

	/**
	 * Other city pages and metro pages, nearest first.
	 *
	 * @return array<int,array{name:string,url:string,miles:int}>
	 */
	public static function nearby( \WP_Term $term ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT tt.term_id, AVG(l.lat) AS lat, AVG(l.lng) AS lng
			 FROM {$wpdb->term_taxonomy} tt
			 JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
			 JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = %s AND p.post_status = 'publish'
			 JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_case27_listing_type' AND m.meta_value = %s
			 JOIN {$wpdb->prefix}mylisting_locations l ON l.listing_id = p.ID
			 WHERE tt.taxonomy = %s AND tt.parent <> 0
			 GROUP BY tt.term_id",
			Pool_Schema::POST_TYPE,
			Pool_Schema::VENUE_TYPE,
			Pool_Schema::TAX_REGION
		) );
		$centers = [];
		foreach ( $rows as $row ) {
			$centers[ (int) $row->term_id ] = [ (float) $row->lat, (float) $row->lng ];
		}
		$here = $centers[ $term->term_id ] ?? null;
		if ( ! $here ) {
			return [];
		}
		$out = [];
		foreach ( $centers as $id => $center ) {
			if ( $id === (int) $term->term_id || Locations::venue_count( $id ) < Seo::MIN_REGION_VENUES ) {
				continue;
			}
			$city = get_term( $id, Pool_Schema::TAX_REGION );
			$link = $city instanceof \WP_Term ? get_term_link( $city ) : '';
			if ( $link && ! is_wp_error( $link ) ) {
				$out[] = [ 'name' => self::where( $city ), 'url' => $link, 'miles' => self::miles( $here, $center ) ];
			}
		}
		foreach ( Metros::with_pages() as $metro ) {
			$out[] = [ 'name' => $metro['label'] . ' area', 'url' => Metros::url( $metro ), 'miles' => self::miles( $here, [ $metro['lat'], $metro['lng'] ] ) ];
		}
		$out = array_filter( $out, static fn( $n ) => $n['miles'] <= self::NEARBY_MILES && $n['miles'] > 0 );
		usort( $out, static fn( $a, $b ) => $a['miles'] <=> $b['miles'] );
		return array_slice( array_values( $out ), 0, self::NEARBY_LIMIT );
	}

	/** @param array{0:float,1:float} $a @param array{0:float,1:float} $b */
	private static function miles( array $a, array $b ): int {
		[ $lat1, $lng1 ] = array_map( 'deg2rad', $a );
		[ $lat2, $lng2 ] = array_map( 'deg2rad', $b );
		$d = sin( ( $lat2 - $lat1 ) / 2 ) ** 2 + cos( $lat1 ) * cos( $lat2 ) * sin( ( $lng2 - $lng1 ) / 2 ) ** 2;
		return (int) round( 3959 * 2 * asin( min( 1, sqrt( $d ) ) ) );
	}

	/* ------------------------------------------------------------- schema */

	public static function schema(): void {
		$term = self::current();
		if ( ! $term ) {
			return;
		}
		$city = html_entity_decode( $term->name, ENT_QUOTES );
		$venues = self::venues( $term );
		$link = get_term_link( $term );
		$url = is_wp_error( $link ) ? '' : $link;
		$graph = [];
		$items = [];
		foreach ( $venues as $i => $v ) {
			$items[] = [ '@type' => 'ListItem', 'position' => $i + 1, 'url' => $v['url'], 'name' => $v['name'] ];
		}
		$graph[] = [ '@type' => 'ItemList', '@id' => $url . '#places', 'name' => 'Places to play pool in ' . self::where( $term ), 'numberOfItems' => count( $items ), 'itemListElement' => $items ];
		$crumbs = [];
		foreach ( self::crumbs( $term ) as $i => [ $name, $crumb_url ] ) {
			$crumbs[] = [ '@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $crumb_url ?: $url ];
		}
		$graph[] = [ '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $crumbs ];
		$faq = City_Text::faq( $city, $venues );
		if ( count( $faq ) >= 2 ) {
			$graph[] = [
				'@type'      => 'FAQPage',
				'@id'        => $url . '#faq',
				'mainEntity' => array_map( static fn( $q ) => [ '@type' => 'Question', 'name' => $q['q'], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $q['a'] ] ], $faq ),
			];
		}
		echo '<script type="application/ld+json">' . wp_json_encode( [ '@context' => 'https://schema.org', '@graph' => $graph ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
	}
}
