<?php
/**
 * Keyword research exports: reading them, classifying each keyword's intent, and
 * the demand report used to decide which cities, hubs and venues to build next
 * (Cli\Keywords_Command). Pure functions over plain arrays, so they are unit
 * tested without WordPress.
 *
 * Intents, in order of precedence:
 *   venue      the keyword names one of our venues
 *   city       a US city of 50,000+ people or a state (location)
 *   near_me    "near me", "nearby", "close to me"
 *   attribute  a table size, brand, free pool, leagues, open late, tournaments, lessons
 *   other      everything else (equipment shopping, rules, swimming pools ...)
 * Location, near-me and attribute signals are all recorded, whatever the intent,
 * and so is whether the keyword names a business ("zanzibar billiards denver")
 * rather than asking generally ("pool hall denver").
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Keywords {

	public const NEAR_ME = '#\b(near me|nearby|near by|close to me|closest to me|around me|near my location)\b#';

	/** Attribute topics, the hub page candidates. Patterns run on normalized text. */
	public const TOPICS = [
		'9-foot tables'     => '#\b(9|nine) ?(ft|foot|feet)\b|\bregulation (size )?(pool )?tables?\b#',
		'8-foot tables'     => '#\b(8|eight) ?(ft|foot|feet)\b#',
		'7-foot tables'     => '#\b(7|seven) ?(ft|foot|feet)\b#',
		'Bar box'           => '#\bbar ?box(es)?\b|\bcoin (op|operated)\b#',
		'Brand: Diamond'    => '#\bdiamond\b#',
		'Brand: Brunswick'  => '#\bbrunswick\b#',
		'Brand: Olhausen'   => '#\bolhausen\b#',
		'Brand: Valley'     => '#\bvalley\b#',
		'Brand: Predator'   => '#\bpredator\b#',
		'Brand: Simonis'    => '#\bsimonis\b#',
		'Brand: Aramith'    => '#\baramith\b#',
		'Brand: Gandy'      => '#\bgandy\b#',
		'Brand: Connelly'   => '#\bconnelly\b#',
		'Brand: Golden West' => '#\bgolden west\b#',
		'Brand: Rasson'     => '#\brasson\b#',
		'Brand: Dynamic'    => '#\bdynamic\b#',
		'Free pool'         => '#\bfree (pool|billiards)\b|\b(pool|billiards) (for )?free\b#',
		'APA leagues'       => '#\bapa\b#',
		'BCA leagues'       => '#\bbca\b#',
		'Leagues'           => '#\bleagues?\b|\busapl\b|\bvnea\b#',
		'Open late'         => '#\bopen late\b|\blate night\b|\bopen (till|until|til)\b|\bafter hours\b#',
		'24 hour'           => '#\b24 ?(hours?|hrs?)\b|\b24 7\b|\ball night\b#',
		'Tournaments'       => '#\btournaments?\b|\btourney\b#',
		'Lessons'           => '#\blessons?\b|\binstructors?\b|\bcoach(es|ing)?\b|\bclass(es)?\b|\bacademy\b|\bteach(er|ing)?\b|\btraining\b#',
	];

	/** Searching for somewhere to play, as opposed to buying a table or reading rules. */
	public const VENUE_SEEKING = '#\bpool halls?\b|\bbilliards? (halls?|clubs?|rooms?|parlou?rs?|lounges?|bars?|cafes?)\b|\bpool (rooms?|bars?|clubs?|lounges?)\b|\bbars?\b|\bpubs?\b|\blounges?\b|\bplaces? to play\b|\bwhere (to|can i) play\b|\bplay pool\b|\bleagues?\b|\btournaments?\b|\bopen\b#';

	/** A table as a thing, which with no place named is a table to buy. */
	public const TABLE = '#\b(pool|billiards?|snooker) tables?\b|\btables? (8|9|7) ?(ft|foot|feet)\b|\b(ft|foot|feet) tables?\b#';

	/** Swimming pools share the word "pool"; those searches are never ours. */
	public const NOT_BILLIARDS = '#\bswim(ming)?\b|\binground\b|\babove ground\b|\bpool (party|parties|cleaning|service|pump|heater|cover|fence|deck|float)s?\b|\bhot tubs?\b|\bwater park\b|\bhall (of fame )?pool\b#';

	/** Buying, moving or fixing equipment, playing online or league logins, unless the keyword is also looking for somewhere to play. */
	public const SHOPPING = '#\bfor sale\b|\bsales?\b|\bretail\b|\bgames?\b|\bonline\b|\bapps?\b|\bapk\b|\blog ?in\b|\bbuy\b|\bprices?\b|\bcost\b|\bused\b|\bcovers?\b|\bfelt\b|\bcloth\b|\bcues?\b|\bsticks?\b|\blights?\b|\bdimensions?\b|\bsizes?\b|\bmovers?\b|\bmoving\b|\brepair\b|\brecover(ing)?\b|\brefelt\b|\bstores?\b|\bshops?\b|\bsuppl(y|ies)\b|\bsuperstore\b|\bassembly\b|\binstall(ation)?\b|\bdealers?\b|\bmanufacturers?\b|\bdining\b|\bconversion\b|\bballs\b|\bball sets?\b|\brules\b|\bhow to\b#';

	/** Words that describe what is searched for, not who; anything else left in a keyword names a business. */
	private const GENERIC_WORDS = [ 'pool', 'pools', 'hall', 'halls', 'billiard', 'billiards', 'bar', 'bars', 'table', 'tables', 'room', 'rooms', 'club', 'clubs', 'lounge', 'lounges', 'parlor', 'parlors', 'parlour', 'sports', 'sport', 'cafe', 'pub', 'pubs', 'near', 'me', 'in', 'the', 'and', 'of', 'with', 'a', 'an', 'at', 'on', 'for', 'to', 'best', 'top', 'good', 'cheap', 'open', 'now', 'today', 'tonight', 'place', 'places', 'play', 'playing', 'where', 'can', 'i', 'go', 'game', 'games', 'area', 'city', 'downtown', 'midtown', 'nearby', 'close', 'closest', 'around', 'my', 'location', 'league', 'leagues', 'tournament', 'tournaments', 'night', 'nights', 'free', 'local', 'greater', 'metro', 'central', 'north', 'south', 'east', 'west', 'northern', 'southern', 'eastern', 'western', 'new', 'that', 'have', 'has', 'is', 'are', 'what', 'which', 'any', 'hours', 'hour', 'late', 'schedule', 'schedules', 'snooker', 'ball', '8', '9', 'nine', 'eight', 'grill', 'restaurant', 'restaurants', 'food', 'drinks', 'beer', 'kids', 'family', 'friendly', 'sign', 'up', 'join', 'or', 'county', 'st', 'state' ];

	/** Short names for cities, mapped to [ city, state ]. */
	public const ALIASES = [
		'nyc'           => [ 'New York', 'NY' ],
		'manhattan'     => [ 'New York', 'NY' ],
		'brooklyn'      => [ 'New York', 'NY' ],
		'queens'        => [ 'New York', 'NY' ],
		'bronx'         => [ 'New York', 'NY' ],
		'staten island' => [ 'New York', 'NY' ],
		'dc'            => [ 'Washington', 'DC' ],
		'washington dc' => [ 'Washington', 'DC' ],
		'philly'        => [ 'Philadelphia', 'PA' ],
		'vegas'         => [ 'Las Vegas', 'NV' ],
		'nola'          => [ 'New Orleans', 'LA' ],
	];

	/**
	 * City names that are also ordinary words, people or places abroad; they count
	 * only with their state ("mobile al", "vancouver wa").
	 */
	public const NEEDS_STATE = [ 'mobile', 'orange', 'normal', 'mission', 'surprise', 'temple', 'independence', 'victoria', 'florence', 'commerce', 'allen', 'sandy', 'bend', 'davis', 'vista', 'hollywood', 'ontario', 'columbia', 'jackson', 'warren', 'lawrence', 'dublin', 'melbourne', 'vancouver', 'bristol', 'hamilton', 'georgetown', 'cambridge' ];

	/** Words left out when a venue title is matched by its distinctive part ("Rack City" of "Rack City Billiards"). */
	private const GENERIC_TITLE_WORDS = [ 'the', 'and', 'billiards', 'billiard', 'pool', 'hall', 'halls', 'bar', 'grill', 'club', 'lounge', 'room', 'sports', 'cafe', 'pub', 'tavern', 'restaurant', 'parlor', 'center', 'family', 'co', 'inc', 'llc' ];

	/* ------------------------------------------------------------------ input */

	/**
	 * Rows from one export, whatever the tool: Ahrefs (often UTF-16, tab separated),
	 * Semrush, Google Keyword Planner or DataForSEO. Columns are found by header name.
	 *
	 * @return array{rows:array<int,array{keyword:string,volume:int}>,error:string}
	 */
	public static function read_export( string $raw ): array {
		if ( str_starts_with( $raw, "\xFF\xFE" ) || str_starts_with( $raw, "\xFE\xFF" ) ) {
			$raw = (string) mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', str_starts_with( $raw, "\xFF\xFE" ) ? 'UTF-16LE' : 'UTF-16BE' );
		}
		$raw = (string) preg_replace( '#^\xEF\xBB\xBF#', '', $raw );
		$lines = preg_split( '#\r\n|\r|\n#', $raw );
		$header_line = (string) ( $lines[0] ?? '' );
		$delimiter = str_contains( $header_line, "\t" ) ? "\t" : ( substr_count( $header_line, ';' ) > substr_count( $header_line, ',' ) ? ';' : ',' );
		$header = array_map( static fn( $h ) => strtolower( trim( (string) $h, " \"'" ) ), str_getcsv( $header_line, $delimiter, '"', '' ) );
		$kw = self::column( $header, '#^(keyword|keywords|query|search term|search query)$#' );
		$vol = self::column( $header, '#^(search_volume|search volume|volume|avg\.? monthly searches|monthly searches|sv|global volume)$#' ) ?? self::column( $header, '#volume#' );
		if ( null === $kw || null === $vol ) {
			return [ 'rows' => [], 'error' => 'no keyword or volume column in: ' . implode( ', ', $header ) ];
		}
		$rows = [];
		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = str_getcsv( $line, $delimiter, '"', '' );
			$keyword = trim( (string) ( $cells[ $kw ] ?? '' ) );
			if ( '' !== $keyword ) {
				$rows[] = [ 'keyword' => $keyword, 'volume' => self::volume( (string) ( $cells[ $vol ] ?? '' ) ) ];
			}
		}
		return [ 'rows' => $rows, 'error' => '' ];
	}

	private static function column( array $header, string $pattern ): ?int {
		foreach ( $header as $i => $h ) {
			if ( preg_match( $pattern, $h ) ) {
				return $i;
			}
		}
		return null;
	}

	/** "1,600", "1.6K", "2M", "1K – 10K" (the lower bound), "" => 0 */
	public static function volume( string $v ): int {
		if ( ! preg_match( '#([\d.,]+)\s*([kKmM])?#', $v, $m ) ) {
			return 0;
		}
		$n = (float) str_replace( ',', '', $m[1] );
		$mult = [ 'k' => 1000, 'm' => 1000000 ][ strtolower( $m[2] ?? '' ) ] ?? 1;
		return (int) round( $n * $mult );
	}

	/**
	 * One row per keyword (case and spacing ignored), keeping the highest volume.
	 *
	 * @param array<int,array{keyword:string,volume:int,source?:string}> $rows
	 * @return array<string,array{keyword:string,volume:int,sources:string[]}> normalized keyword => row
	 */
	public static function dedupe( array $rows ): array {
		$out = [];
		foreach ( $rows as $row ) {
			$key = (string) preg_replace( '#\s+#', ' ', strtolower( trim( $row['keyword'] ) ) );
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = [ 'keyword' => $key, 'volume' => 0, 'sources' => [] ];
			}
			$out[ $key ]['volume'] = max( $out[ $key ]['volume'], (int) $row['volume'] );
			if ( ! empty( $row['source'] ) && ! in_array( $row['source'], $out[ $key ]['sources'], true ) ) {
				$out[ $key ]['sources'][] = $row['source'];
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------- classify */

	/** Lowercase words without accents, "&" as "and", "saint"/"st." alike, punctuation as spaces. */
	public static function normalize( string $text ): string {
		$text = mb_strtolower( html_entity_decode( $text, ENT_QUOTES ) );
		$text = strtr( $text, [ 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ï' => 'i', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c' ] );
		$text = str_replace( [ '&', '’', "'" ], [ ' and ', '', '' ], $text );
		$text = (string) preg_replace( '#[^a-z0-9]+#', ' ', $text );
		$text = (string) preg_replace( '#\bsaint\b#', 'st', $text );
		return trim( (string) preg_replace( '#\s+#', ' ', $text ) );
	}

	/**
	 * Look-up tables for classify(): cities by normalized name (largest first),
	 * states by normalized name and abbreviation, and venue titles.
	 *
	 * @param array<int,array{0:string,1:string,2:int}> $cities  [ name, state, population ]
	 * @param array<string,string>                      $states  abbreviation => name
	 * @param array<int,string|array{0:string,1:string,2:string}> $venues listing titles, or [ title, city, state ]
	 */
	public static function index( array $cities, array $states, array $venues = [] ): array {
		$city_index = [];
		foreach ( $cities as [ $name, $state, $pop ] ) {
			$city_index[ self::normalize( $name ) ][] = [ 'name' => $name, 'state' => $state, 'pop' => (int) $pop ];
		}
		foreach ( $city_index as &$list ) {
			usort( $list, static fn( $a, $b ) => $b['pop'] <=> $a['pop'] );
		}
		unset( $list );
		$state_names = [];
		foreach ( $states as $abbr => $name ) {
			$state_names[ self::normalize( 'DC' === $abbr ? 'District of Columbia' : $name ) ] = $abbr;
		}
		$venue_index = [];
		foreach ( $venues as $venue ) {
			[ $title, $city, $state ] = is_array( $venue ) ? $venue + [ '', '', '' ] : [ $venue, '', '' ];
			$full = self::normalize( $title );
			$core = implode( ' ', array_diff( explode( ' ', $full ), self::GENERIC_TITLE_WORDS ) );
			$venue_index[] = [ 'title' => $title, 'full' => $full, 'core' => $core, 'city' => '' === $city ? '' : self::normalize( $city ) . '|' . strtoupper( $state ) ];
		}
		return [ 'cities' => $city_index, 'states' => $state_names, 'abbrs' => array_change_key_case( $states, CASE_LOWER ), 'venues' => $venue_index ];
	}

	/**
	 * @return array{intent:string,city:?array{name:string,state:string},state:?string,near_me:bool,topics:string[],venue:?string,venue_seeking:bool,shopping:bool,named:bool}
	 */
	public static function classify( string $keyword, array $index ): array {
		$text = self::normalize( $keyword );
		$result = [ 'intent' => 'other', 'city' => null, 'state' => null, 'near_me' => false, 'topics' => [], 'venue' => null, 'venue_seeking' => false, 'shopping' => false, 'named' => false ];
		if ( '' === $text || preg_match( self::NOT_BILLIARDS, $text ) ) {
			return $result;
		}
		[ $city, $state, $rest ] = self::location( $text, $index );
		$result['venue'] = self::venue_match( $text, $index['venues'], $city ? self::normalize( $city['name'] ) . '|' . $city['state'] : '', $state );
		// A city in the venue's own name ("Buffalo Billiards" in DC) is not where the keyword looks.
		if ( $result['venue'] && $city && str_contains( ' ' . self::normalize( $result['venue'] ) . ' ', ' ' . self::normalize( $city['name'] ) . ' ' ) ) {
			[ $city, $state, $rest ] = [ null, null, $text ];
		}
		$result['city'] = $city;
		$result['state'] = $state;
		$result['near_me'] = (bool) preg_match( self::NEAR_ME, $text );
		foreach ( self::TOPICS as $topic => $pattern ) {
			if ( preg_match( $pattern, $rest ) ) {
				$result['topics'][] = $topic;
			}
		}
		if ( in_array( 'APA leagues', $result['topics'], true ) || in_array( 'BCA leagues', $result['topics'], true ) ) {
			$result['topics'] = array_values( array_diff( $result['topics'], [ 'Leagues' ] ) );
		}
		$seeking = (bool) preg_match( self::VENUE_SEEKING, $text );
		$result['venue_seeking'] = $seeking || $result['near_me'];
		// "pool tables for sale near me" is shopping; so is a table with no place to play it ("brunswick pool table").
		$result['shopping'] = ! $seeking && ( preg_match( self::SHOPPING, $text ) || ( ! $result['near_me'] && ! $city && ! $state && preg_match( self::TABLE, $text ) ) );
		$left = (string) preg_replace( self::NEAR_ME, ' ', $rest );
		foreach ( self::TOPICS as $pattern ) {
			$left = (string) preg_replace( $pattern, ' ', $left );
		}
		// "&" joins the words of a business name ("8 ball sports bar & billiards"), hardly ever a search for a kind of place.
		$result['named'] = (bool) array_diff( array_filter( explode( ' ', $left ) ), self::GENERIC_WORDS ) || (bool) preg_match( '#\w ?& ?\w#', $keyword );
		if ( $result['venue'] ) {
			$result['intent'] = 'venue';
		} elseif ( $result['shopping'] ) {
			$result['intent'] = 'other';
		} elseif ( $city || $state ) {
			$result['intent'] = 'city';
		} elseif ( $result['near_me'] ) {
			$result['intent'] = 'near_me';
		} elseif ( $result['topics'] ) {
			$result['intent'] = 'attribute';
		}
		return $result;
	}

	/**
	 * The venue a keyword names: its full title ("amsterdam billiards"), or the
	 * distinctive part of it ("rack city" of "Rack City Billiards"). A one-word
	 * title ("The Rack", "Lucky's") or distinctive part ("Shooters") also needs
	 * the venue's city in the keyword, and that word cannot be the city itself
	 * ("Chicago Billiard Cafe" is not every Chicago search). A distinctive part
	 * in another state names some other business ("hall of fame billiards
	 * brooklyn" is not the one in Warren, MI).
	 */
	private static function venue_match( string $text, array $venues, string $city, ?string $state ): ?string {
		$padded = ' ' . $text . ' ';
		$best = null;
		foreach ( $venues as $v ) {
			$in_city = '' !== $city && $city === $v['city'];
			$elsewhere = $state && '' !== $v['city'] && ! str_ends_with( $v['city'], '|' . $state );
			$full = str_word_count( (string) preg_replace( '#^the #', '', $v['full'] ) ) >= 2 || $in_city ? $v['full'] : '';
			$core = ( str_word_count( $v['core'] ) >= 2 && ! $elsewhere ) || ( $in_city && ! str_starts_with( $city, $v['core'] . '|' ) ) ? $v['core'] : '';
			foreach ( [ $full, $core ] as $needle ) {
				if ( '' !== $needle && str_contains( $padded, ' ' . $needle . ' ' ) && ( ! $best || strlen( $needle ) > $best[1] ) ) {
					$best = [ $v['title'], strlen( $needle ) ];
				}
			}
		}
		return $best[0] ?? null;
	}

	/**
	 * The city (with its state) or state a keyword names, and the text with that
	 * name removed, so "moreno valley" is not read as the Valley brand.
	 *
	 * @return array{0:?array{name:string,state:string},1:?string,2:string}
	 */
	private static function location( string $text, array $index ): array {
		$words = explode( ' ', $text );
		$n = count( $words );
		$alias_index = [];
		foreach ( self::ALIASES as $alias => $city ) {
			$alias_index[ $alias ] = $city;
		}
		for ( $len = min( 4, $n ); $len >= 1; $len-- ) {
			for ( $i = 0; $i + $len <= $n; $i++ ) {
				$phrase = implode( ' ', array_slice( $words, $i, $len ) );
				$after = array_slice( $words, $i + $len, 2 );
				$qualifier = self::state_after( $after, $index );
				if ( isset( $alias_index[ $phrase ] ) ) {
					[ $name, $state ] = $alias_index[ $phrase ];
					return [ [ 'name' => $name, 'state' => $state ], $state, self::without( $words, $i, $len + ( $qualifier ? $qualifier[1] : 0 ) ) ];
				}
				// "New York" is the city, not the state, unless the keyword says "state".
				if ( 'new york' === $phrase && in_array( 'state', $words, true ) ) {
					continue;
				}
				if ( ! isset( $index['cities'][ $phrase ] ) || 'county' === ( $after[0] ?? '' ) ) {
					continue;
				}
				// A city named like its state ("wyoming") is the state, unless qualified.
				if ( isset( $index['states'][ $phrase ] ) && ! $qualifier && 'new york' !== $phrase ) {
					continue;
				}
				// "Washington" alone is the state; the city needs "dc".
				if ( 'washington' === $phrase && ( ! $qualifier || 'DC' !== $qualifier[0] ) ) {
					continue;
				}
				$candidates = $index['cities'][ $phrase ];
				$city = null;
				if ( $qualifier ) {
					foreach ( $candidates as $c ) {
						if ( $c['state'] === $qualifier[0] ) {
							$city = $c;
						}
					}
				}
				if ( ! $city && ! in_array( $phrase, self::NEEDS_STATE, true ) && ! $qualifier ) {
					$city = $candidates[0];
				}
				if ( $city ) {
					return [ [ 'name' => $city['name'], 'state' => $city['state'] ], $city['state'], self::without( $words, $i, $len + ( $qualifier ? $qualifier[1] : 0 ) ) ];
				}
			}
		}
		for ( $len = min( 3, $n ); $len >= 1; $len-- ) {
			for ( $i = 0; $i + $len <= $n; $i++ ) {
				$phrase = implode( ' ', array_slice( $words, $i, $len ) );
				if ( isset( $index['states'][ $phrase ] ) ) {
					return [ null, $index['states'][ $phrase ], self::without( $words, $i, $len ) ];
				}
			}
		}
		return [ null, null, $text ];
	}

	/** A state abbreviation or name right after a city: [ abbreviation, words used ] or null. */
	private static function state_after( array $after, array $index ): ?array {
		for ( $len = 2; $len >= 1; $len-- ) {
			if ( count( $after ) < $len ) {
				continue;
			}
			$phrase = implode( ' ', array_slice( $after, 0, $len ) );
			if ( isset( $index['states'][ $phrase ] ) ) {
				return [ $index['states'][ $phrase ], $len ];
			}
			if ( 1 === $len && isset( $index['abbrs'][ $phrase ] ) ) {
				return [ strtoupper( $phrase ), 1 ];
			}
		}
		return null;
	}

	private static function without( array $words, int $start, int $len ): string {
		array_splice( $words, $start, $len );
		return implode( ' ', $words );
	}

	/**
	 * Close variants (plural, "billiard"/"billiards", "close to me"/"near me",
	 * word order) share one search volume in keyword tools, so totals count each
	 * group once.
	 */
	public static function variant_key( string $keyword ): string {
		$text = (string) preg_replace( self::NEAR_ME, 'near me', self::normalize( $keyword ) );
		$words = array_diff( explode( ' ', $text ), [ 'in', 'the', 'a', 'an', 'for', 'of', 'to', 'with', 'and', 'at', 'on' ] );
		$words = array_map( static fn( $w ) => strlen( $w ) > 3 ? (string) preg_replace( '#(es|s)$#', '', $w ) : $w, $words );
		sort( $words );
		return implode( ' ', array_unique( $words ) );
	}

	/**
	 * Sum of volumes counting each variant group once (its highest volume).
	 *
	 * @param array<int,array{keyword:string,volume:int}> $rows
	 */
	public static function total( array $rows ): int {
		$groups = [];
		foreach ( $rows as $row ) {
			$key = self::variant_key( $row['keyword'] );
			$groups[ $key ] = max( $groups[ $key ] ?? 0, (int) $row['volume'] );
		}
		return array_sum( $groups );
	}
}
