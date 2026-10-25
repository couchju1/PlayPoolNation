<?php
/**
 * Words for a city page (City_Page): the opening paragraph, each venue's fact
 * lines, weekly hours, league nights, the FAQ, and the title and description.
 * Pure functions of the venues' facts, so they are unit tested.
 *
 * Style, as in About_Text: short sentences a 7th to 9th grader reads easily,
 * contractions, no dashes or semicolons, and only facts we have. Comparisons
 * say what they compare ("of the 2 places where we know the count").
 *
 * A venue's facts (City_Page::facts()), with sourced values only:
 *   name, anchor, type (venue type slug), street,
 *   tables ?int, size_counts [size slug => n], sizes [size slugs], brands [names],
 *   pricing [slugs], hourly_rate, game_price, food bool, games [names],
 *   leagues [ [ day (0 = Monday), time, orgs [names], name ] ],
 *   hours [ [start, end] minutes from Monday 00:00 ], status
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class City_Text {

	/** Venue type slug => [ singular, plural, label ]. */
	private const TYPES = [
		'pool-halls'            => [ 'pool hall', 'pool halls', 'Pool hall' ],
		'billiards-lounge'      => [ 'billiards lounge', 'billiards lounges', 'Billiards lounge' ],
		'bars-with-pool-tables' => [ 'bar', 'bars', 'Bar' ],
		'sports-bar'            => [ 'sports bar', 'sports bars', 'Sports bar' ],
		'bowling-center'        => [ 'bowling center', 'bowling centers', 'Bowling center' ],
		'recreation'            => [ 'recreation center', 'recreation centers', 'Recreation center' ],
		'private-clubs'         => [ 'private club', 'private clubs', 'Private club' ],
	];

	private const SIZES = [ '7-foot' => '7-foot', '8-foot' => '8-foot', '9-foot' => '9-foot', 'snooker' => 'snooker', 'carom' => 'carom' ];

	public const DAYS = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];

	private const DAY_PLURALS = [ 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays', 'Sundays' ];

	public const DAY_SHORT = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];

	public const TITLE_LIMIT = 59;
	public const DESCRIPTION_LIMIT = 154;

	/* ------------------------------------------------------------- facts */

	public static function type_label( string $slug ): string {
		return self::TYPES[ $slug ][2] ?? 'Place to play';
	}

	/** Known table count: the total, else the sum of the per-size counts. */
	public static function tables( array $v ): ?int {
		if ( isset( $v['tables'] ) && null !== $v['tables'] ) {
			return (int) $v['tables'];
		}
		$counts = array_filter( (array) ( $v['size_counts'] ?? [] ), static fn( $n ) => (int) $n > 0 );
		return $counts ? (int) array_sum( $counts ) : null;
	}

	/** @return string[] size slugs, in size order */
	public static function sizes( array $v ): array {
		$known = array_merge( array_keys( array_filter( (array) ( $v['size_counts'] ?? [] ), static fn( $n ) => (int) $n > 0 ) ), (array) ( $v['sizes'] ?? [] ) );
		return array_values( array_intersect( array_keys( self::SIZES ), $known ) );
	}

	public static function has_size( array $v, string $size ): bool {
		return in_array( $size, self::sizes( $v ), true );
	}

	/** @return string[] */
	public static function brands( array $v ): array {
		return array_values( array_filter( (array) ( $v['brands'] ?? [] ), static fn( $b ) => '' !== trim( (string) $b ) && 'other brand' !== strtolower( (string) $b ) ) );
	}

	public static function free_pool( array $v ): bool {
		return in_array( 'free-pool', (array) ( $v['pricing'] ?? [] ), true );
	}

	/**
	 * How many of the three table facts are known: the count, the sizes, the brands.
	 * Venue::is_indexable() uses this.
	 */
	public static function table_facts( array $v ): int {
		return (int) ( null !== self::tables( $v ) ) + (int) (bool) self::sizes( $v ) + (int) (bool) self::brands( $v );
	}

	/** @return int[] weekdays with a league night, 0 = Monday */
	public static function league_days( array $v ): array {
		$days = array_values( array_unique( array_filter( array_map( static fn( $l ) => $l['day'] ?? null, (array) ( $v['leagues'] ?? [] ) ), 'is_int' ) ) );
		sort( $days );
		return $days;
	}

	/* ------------------------------------------------------- comparisons */

	/**
	 * The venue or venues with the most tables, among those whose count we know.
	 * Null when fewer than two counts are known: one count compares with nothing.
	 *
	 * @return array{names:string[],tables:int,known:int,all:bool}|null
	 */
	public static function most_tables( array $venues ): ?array {
		$known = array_values( array_filter( $venues, static fn( $v ) => null !== self::tables( $v ) && self::tables( $v ) > 0 ) );
		if ( count( $known ) < 2 ) {
			return null;
		}
		$max = max( array_map( [ __CLASS__, 'tables' ], $known ) );
		$names = array_values( array_map( static fn( $v ) => (string) $v['name'], array_filter( $known, static fn( $v ) => self::tables( $v ) === $max ) ) );
		return [ 'names' => $names, 'tables' => $max, 'known' => count( $known ), 'all' => count( $known ) === count( $venues ) ];
	}

	/** @return string[] names of venues with a table size */
	public static function with_size( array $venues, string $size ): array {
		return array_values( array_map( static fn( $v ) => (string) $v['name'], array_filter( $venues, static fn( $v ) => self::has_size( $v, $size ) ) ) );
	}

	/* ------------------------------------------------------------- intro */

	/** "Sioux Falls has 10 places to play pool. That's 3 pool halls and 7 bars. …" */
	public static function intro( string $city, array $venues ): string {
		$n = count( $venues );
		if ( ! $n ) {
			return '';
		}
		$out = [ 1 === $n ? sprintf( '%s has one place to play pool on our map.', $city ) : sprintf( '%s has %d places to play pool on our map.', $city, $n ) ];
		$mix = self::type_mix( $venues );
		if ( $n > 1 && $mix ) {
			$out[] = sprintf( "That's %s.", $mix );
		}
		$most = self::most_tables( $venues );
		if ( $most ) {
			$out[] = self::most_sentence( $most );
		}
		$nine = self::with_size( $venues, '9-foot' );
		if ( $nine ) {
			$out[] = self::names_have( $nine, $n, '9-foot tables' );
		}
		$free = array_values( array_map( static fn( $v ) => (string) $v['name'], array_filter( $venues, [ __CLASS__, 'free_pool' ] ) ) );
		if ( $free ) {
			$out[] = self::names_have( $free, $n, 'free pool' );
		}
		$league = array_values( array_filter( $venues, static fn( $v ) => (bool) self::league_days( $v ) ) );
		if ( $league ) {
			$orgs = array_values( array_intersect( ...array_map( static fn( $v ) => array_merge( ...array_map( static fn( $l ) => (array) ( $l['orgs'] ?? [] ), $v['leagues'] ) ), $league ) ) );
			$kind = $orgs ? $orgs[0] . ' leagues' : 'league nights';
			$out[] = 1 === count( $league )
				? sprintf( '%s runs %s during the week.', $league[0]['name'], $kind )
				: sprintf( '%d of them run %s during the week.', count( $league ), $kind );
		}
		return implode( ' ', $out );
	}

	/** "3 pool halls and 7 bars", biggest group first. */
	public static function type_mix( array $venues ): string {
		$counts = [];
		foreach ( $venues as $v ) {
			$type = isset( self::TYPES[ $v['type'] ?? '' ] ) ? $v['type'] : '';
			if ( '' === $type ) {
				return '';
			}
			$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
		}
		arsort( $counts );
		$parts = [];
		foreach ( $counts as $type => $c ) {
			$parts[] = $c . ' ' . self::TYPES[ $type ][ 1 === $c ? 0 : 1 ];
		}
		return About_Text::and_list( $parts );
	}

	private static function most_sentence( array $most ): string {
		$who = About_Text::and_list( $most['names'] );
		$verb = 1 === count( $most['names'] ) ? 'has' : 'have';
		$each = 1 === count( $most['names'] ) ? '' : ' each';
		if ( $most['all'] ) {
			return sprintf( '%s %s the most tables, with %d%s.', $who, $verb, $most['tables'], $each );
		}
		return sprintf( 'Of the %d places where we know the count, %s %s the most tables, with %d%s.', $most['known'], $who, $verb, $most['tables'], $each );
	}

	/** "Rack City Billiards has 9-foot tables." "A and B have …" "4 of them have …" */
	private static function names_have( array $names, int $total, string $what ): string {
		if ( count( $names ) > 3 ) {
			return sprintf( '%d of them have %s.', count( $names ), $what );
		}
		return sprintf( '%s %s %s.', About_Text::and_list( $names ), 1 === count( $names ) ? 'has' : 'have', $what );
	}

	/* -------------------------------------------------------- venue lines */

	/**
	 * Short fact lines for a venue's section, known facts only.
	 *
	 * @return array<int,array{0:string,1:string}> [ label, value ]
	 */
	public static function fact_lines( array $v ): array {
		$lines = [];
		$street = trim( (string) ( $v['street'] ?? '' ) );
		$lines[] = [ 'Type', self::type_label( (string) ( $v['type'] ?? '' ) ) ];
		if ( '' !== $street ) {
			$lines[] = [ 'Address', $street ];
		}
		$tables = self::tables( $v );
		if ( null !== $tables ) {
			$lines[] = [ 'Tables', 0 === $tables ? 'None right now' : (string) $tables ];
		}
		$sizes = self::sizes( $v );
		if ( $sizes ) {
			$counts = (array) ( $v['size_counts'] ?? [] );
			$lines[] = [ 'Sizes', About_Text::and_list( array_map( static fn( $s ) => self::SIZES[ $s ] . ( ! empty( $counts[ $s ] ) ? ' (' . (int) $counts[ $s ] . ')' : '' ), $sizes ) ) ];
		}
		$brands = self::brands( $v );
		if ( $brands ) {
			$lines[] = [ 'Brands', About_Text::and_list( $brands ) ];
		}
		$price = self::price( $v );
		if ( '' !== $price ) {
			$lines[] = [ 'Pool', $price ];
		}
		if ( ! empty( $v['food'] ) ) {
			$lines[] = [ 'Food', 'Yes' ];
		}
		$games = array_values( array_filter( (array) ( $v['games'] ?? [] ) ) );
		if ( $games ) {
			$lines[] = [ 'Also', About_Text::and_list( $games ) ];
		}
		$leagues = self::league_lines( $v );
		if ( $leagues ) {
			$lines[] = [ 'Leagues', implode( '. ', $leagues ) ];
		}
		return $lines;
	}

	private static function price( array $v ): string {
		$hourly = trim( (string) ( $v['hourly_rate'] ?? '' ) );
		$game = trim( (string) ( $v['game_price'] ?? '' ) );
		if ( '' !== $hourly && '' !== $game ) {
			return sprintf( '%s an hour or %s a game', $hourly, $game );
		}
		if ( '' !== $hourly ) {
			return $hourly . ' an hour';
		}
		if ( '' !== $game ) {
			return $game . ' a game';
		}
		$models = (array) ( $v['pricing'] ?? [] );
		foreach ( [ 'free-pool' => 'Free', 'hourly' => 'By the hour', 'per-game' => 'By the game', 'coin-op' => 'Coin-op tables' ] as $slug => $label ) {
			if ( in_array( $slug, $models, true ) ) {
				return $label;
			}
		}
		return '';
	}

	/** @return string[] "APA on Tuesdays at 7 PM" */
	public static function league_lines( array $v ): array {
		$out = [];
		foreach ( (array) ( $v['leagues'] ?? [] ) as $l ) {
			$who = $l['orgs'] ? About_Text::and_list( (array) $l['orgs'] ) : (string) ( $l['name'] ?? 'League' );
			$when = isset( $l['day'] ) && is_int( $l['day'] ) ? ' on ' . self::DAY_PLURALS[ $l['day'] ] : '';
			$time = trim( (string) ( $l['time'] ?? '' ) );
			$out[] = $who . $when . ( '' !== $time ? ' at ' . $time : '' );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * League nights across the city by weekday, Monday first.
	 *
	 * @return array<string,string[]> day name => "APA at Rack City Billiards, 7 PM"
	 */
	public static function league_nights( array $venues ): array {
		$days = [];
		foreach ( $venues as $v ) {
			foreach ( (array) ( $v['leagues'] ?? [] ) as $l ) {
				if ( ! isset( $l['day'] ) || ! is_int( $l['day'] ) ) {
					continue;
				}
				$who = $l['orgs'] ? About_Text::and_list( (array) $l['orgs'] ) : (string) ( $l['name'] ?? 'League' );
				$time = trim( (string) ( $l['time'] ?? '' ) );
				$days[ $l['day'] ][] = sprintf( '%s at %s%s', $who, $v['name'], '' !== $time ? ', ' . $time : '' );
			}
		}
		ksort( $days );
		$out = [];
		foreach ( $days as $day => $lines ) {
			$out[ self::DAYS[ $day ] ] = array_values( array_unique( $lines ) );
		}
		return $out;
	}

	/* -------------------------------------------------------------- hours */

	/**
	 * Opening hours by weekday, Monday first. A night that runs past midnight
	 * stays on the day it started (My Listing splits it at the end of the week).
	 *
	 * @param array<int,array{0:int,1:int}> $ranges minutes from Monday 00:00
	 * @return array<int,array{day:string,hours:string}> empty when the hours are unknown
	 */
	public static function week_hours( array $ranges ): array {
		$week = 10080;
		$ranges = array_values( array_map( static fn( $r ) => [ (int) $r[0], (int) $r[1] ], array_filter( $ranges, static fn( $r ) => (int) $r[1] > (int) $r[0] ) ) );
		if ( ! $ranges ) {
			return [];
		}
		foreach ( $ranges as $i => [ $s, $e ] ) {
			if ( 0 !== $s % 1440 ) {
				continue;
			}
			foreach ( $ranges as $j => [ $s2, $e2 ] ) {
				if ( $i !== $j && isset( $ranges[ $i ], $ranges[ $j ] ) && ( $e2 === $s || ( 0 === $s && $week === $e2 ) ) ) {
					$ranges[ $j ][1] += $e - $s;
					unset( $ranges[ $i ] );
					break;
				}
			}
		}
		$by_day = [];
		foreach ( $ranges as [ $s, $e ] ) {
			$by_day[ intdiv( $s, 1440 ) % 7 ][] = [ $s, $e ];
		}
		$out = [];
		foreach ( self::DAYS as $d => $name ) {
			$spans = $by_day[ $d ] ?? [];
			usort( $spans, static fn( $a, $b ) => $a[0] <=> $b[0] );
			$text = array_map( static fn( $r ) => $r[1] - $r[0] >= 1440 ? 'Open 24 hours' : self::clock( $r[0] ) . ' to ' . self::clock( $r[1] ), $spans );
			$out[] = [ 'day' => $name, 'hours' => $text ? ucfirst( implode( ', ', $text ) ) : 'Closed' ];
		}
		return $out;
	}

	private static function number( int $n ): string {
		return [ 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine' ][ $n ] ?? (string) $n;
	}

	private static function clock( int $minutes ): string {
		$c = Format::clock( $minutes );
		return in_array( $c, [ 'Midnight', 'Noon' ], true ) ? strtolower( $c ) : $c;
	}

	/* ---------------------------------------------------------------- FAQ */

	/**
	 * Questions the data answers, and only those.
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function faq( string $city, array $venues ): array {
		$out = [];
		$most = self::most_tables( $venues );
		if ( $most ) {
			$who = About_Text::and_list( $most['names'] );
			$verb = 1 === count( $most['names'] ) ? 'has' : 'have';
			$out[] = [
				'q' => sprintf( 'Which pool hall in %s has the most tables?', $city ),
				'a' => $most['all']
					? sprintf( '%s %s the most, with %d tables%s.', $who, $verb, $most['tables'], 1 === count( $most['names'] ) ? '' : ' each' )
					: sprintf( 'Of the %d places in %s where we know the table count, %s %s the most, with %d tables%s.', $most['known'], $city, $who, $verb, $most['tables'], 1 === count( $most['names'] ) ? '' : ' each' ),
			];
		}
		$nine = array_values( array_filter( $venues, static fn( $v ) => self::has_size( $v, '9-foot' ) ) );
		if ( $nine ) {
			$parts = array_map( static function ( $v ) {
				$n = (int) ( $v['size_counts']['9-foot'] ?? 0 );
				return $n ? sprintf( '%s has %s', $v['name'], 1 === $n ? 'one 9-foot table' : self::number( $n ) . ' 9-foot tables' ) : sprintf( '%s has 9-foot tables', $v['name'] );
			}, $nine );
			$out[] = [
				'q' => sprintf( 'Where can I play on a 9-foot table in %s?', $city ),
				'a' => ucfirst( About_Text::and_list( $parts ) ) . '.',
			];
		}
		$free = array_values( array_map( static fn( $v ) => (string) $v['name'], array_filter( $venues, [ __CLASS__, 'free_pool' ] ) ) );
		if ( $free ) {
			$out[] = [
				'q' => sprintf( 'Is there free pool in %s?', $city ),
				'a' => sprintf( 'Yes. %s %s free pool.', About_Text::and_list( $free ), 1 === count( $free ) ? 'has' : 'have' ),
			];
		}
		return $out;
	}

	/* -------------------------------------------------- title, description */

	/** Under 60 characters, the most specific version that fits. */
	public static function title( string $where, int $n, string $brand ): string {
		$places = 1 === $n ? 'A Place to Play Pool' : $n . ' Places to Play Pool';
		foreach ( [
			sprintf( '%s in %s | %s', $places, $where, $brand ),
			sprintf( 'Pool Halls & Bars in %s | %s', $where, $brand ),
			sprintf( '%s in %s', $places, $where ),
			sprintf( 'Pool Halls in %s', $where ),
		] as $title ) {
			if ( mb_strlen( $title ) <= self::TITLE_LIMIT ) {
				return $title;
			}
		}
		return mb_substr( $where, 0, self::TITLE_LIMIT );
	}

	/** Under 155 characters: what the page compares, then its strongest fact if it fits. */
	public static function description( string $where, array $venues ): string {
		$n = count( $venues );
		$text = sprintf( '%s in %s, with table counts, sizes, league nights and who\'s open now.', 1 === $n ? 'One place to play pool' : $n . ' places to play pool', $where );
		$most = self::most_tables( $venues );
		$facts = [];
		if ( $most && 1 === count( $most['names'] ) ) {
			$facts[] = sprintf( '%s has %d tables.', $most['names'][0], $most['tables'] );
		}
		$nine = self::with_size( $venues, '9-foot' );
		if ( $nine ) {
			$facts[] = self::names_have( $nine, $n, '9-foot tables' );
		}
		foreach ( $facts as $fact ) {
			if ( mb_strlen( $text . ' ' . $fact ) <= self::DESCRIPTION_LIMIT ) {
				$text .= ' ' . $fact;
			}
		}
		return mb_strlen( $text ) <= self::DESCRIPTION_LIMIT ? $text : mb_substr( $text, 0, self::DESCRIPTION_LIMIT - 1 ) . '…';
	}
}
