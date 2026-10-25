<?php
/**
 * The keyword demand report (`wp ppn keywords report`): classified keywords set
 * against the pages the site already has, as markdown. Pure functions over plain
 * arrays; Cli\Keywords_Command gathers the site data.
 *
 * Site data shape:
 *   venues  [ [ title, url, city, state ] ]                      published venues
 *   cities  [ "<normalized city>|ST" => [ name, state, url, venues ] ]
 *           url is '' when the city page is too thin to be indexed
 *   metros  [ [ label, url, venues, cities => [ "<normalized city>|ST" ] ] ]  metros with a page
 *   states  [ ST => [ url, venues ] ]
 *   hubs    [ topic => [ url, indexed ] ]                       from HUB_PAGES, pages that exist;
 *           a noindexed hub answers no keyword
 *   places  the /places/ URL, which answers near-me searches
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Keyword_Report {

	/** A city page with at least this many venues counts as covered. */
	public const COVERED = 5;

	/** See business_names(). */
	public const NAME_VOLUME = 1000;

	/** Words that ask for a kind of place rather than name one. */
	public const PLACE_KIND = '#\b(pool|bars?|pubs?|lounges?|halls?|rooms?|near me|nearby|where|play|playing)\b#';

	/** Hub page slugs for the topics that already have one. */
	public const HUB_PAGES = [
		'APA leagues' => 'leagues',
		'BCA leagues' => 'leagues',
		'Leagues'     => 'leagues',
		'Tournaments' => 'events',
		'Lessons'     => 'instructors',
	];

	public const INTENTS = [ 'city', 'near_me', 'attribute', 'venue', 'other' ];

	/**
	 * Every keyword with its class and the pages that answer it.
	 *
	 * @param array<string,array{keyword:string,volume:int,sources:string[]}> $rows from Keywords::dedupe()
	 * @return array<int,array{keyword:string,volume:int,class:array,pages:array<int,array{kind:string,url:string}>}>
	 */
	public static function classify_all( array $rows, array $index, array $site ): array {
		$out = [];
		foreach ( $rows as $row ) {
			$out[] = [ 'keyword' => $row['keyword'], 'volume' => (int) $row['volume'], 'class' => Keywords::classify( $row['keyword'], $index ) ];
		}
		$out = self::business_names( $out );
		foreach ( $out as &$row ) {
			$row['pages'] = self::pages( $row['class'], $site );
		}
		unset( $row );
		usort( $out, static fn( $a, $b ) => [ $b['volume'], $a['keyword'] ] <=> [ $a['volume'], $b['keyword'] ] );
		return $out;
	}

	/**
	 * "clifton billiards" reads as a general search, but it is one business's name.
	 * Two signs, both for a place keyword with at least NAME_VOLUME searches:
	 * - keyword tools give it exactly the volume of a business search in the same
	 *   city ("clifton billiards nj"), as close variants share one volume;
	 * - it names no kind of place (no pool hall, bar, lounge ...) and has more
	 *   than three times the searches of the city's busiest "pool hall" keyword
	 *   ("springfield billiards" 3,600 against "pool hall springfield" 260).
	 * Such a keyword is marked as that business (our venue, when it is one of ours).
	 * Smaller volumes are left alone: too many keywords share them by chance.
	 *
	 * @param array<int,array{keyword:string,volume:int,class:array}> $rows
	 * @return array<int,array{keyword:string,volume:int,class:array}>
	 */
	public static function business_names( array $rows ): array {
		$names = [];
		$pool_hall = [];
		foreach ( $rows as $row ) {
			$c = $row['class'];
			if ( ! $c['city'] ) {
				continue;
			}
			$key = self::city_key( $c['city']['name'], $c['city']['state'] );
			if ( ( $c['named'] || $c['venue'] ) && $row['volume'] >= self::NAME_VOLUME ) {
				$names[ $key ][ $row['volume'] ] = $names[ $key ][ $row['volume'] ] ?? $c['venue'];
			} elseif ( ! $c['named'] && preg_match( '#\bpool (hall|room)s?\b#', Keywords::normalize( $row['keyword'] ) ) ) {
				$pool_hall[ $key ] = max( $pool_hall[ $key ] ?? 0, $row['volume'] );
			}
		}
		foreach ( $rows as $i => $row ) {
			$c = $row['class'];
			if ( 'city' !== $c['intent'] || ! $c['city'] || $c['named'] ) {
				continue;
			}
			$key = self::city_key( $c['city']['name'], $c['city']['state'] );
			$shared = array_key_exists( $row['volume'], $names[ $key ] ?? [] );
			$outsized = $row['volume'] >= self::NAME_VOLUME && $row['volume'] > 3 * ( $pool_hall[ $key ] ?? 0 )
				&& ! preg_match( self::PLACE_KIND, Keywords::normalize( $row['keyword'] ) );
			if ( ! $shared && ! $outsized ) {
				continue;
			}
			$rows[ $i ]['class']['named'] = true;
			if ( $shared && $names[ $key ][ $row['volume'] ] ) {
				$rows[ $i ]['class']['venue'] = $names[ $key ][ $row['volume'] ];
				$rows[ $i ]['class']['intent'] = 'venue';
			}
		}
		return $rows;
	}

	public static function city_key( string $name, string $state ): string {
		return Keywords::normalize( $name ) . '|' . strtoupper( $state );
	}

	/**
	 * The pages that answer a classified keyword. A search naming a business we
	 * do not list has none, even in a city we cover.
	 *
	 * @return array<int,array{kind:string,url:string}>
	 */
	public static function pages( array $class, array $site ): array {
		if ( $class['venue'] ) {
			foreach ( $site['venues'] as $v ) {
				if ( $v[0] === $class['venue'] ) {
					return [ [ 'kind' => 'venue', 'url' => $v[1] ] ];
				}
			}
			return [];
		}
		if ( 'city' === $class['intent'] ) {
			if ( $class['named'] ) {
				return [];
			}
			if ( ! $class['city'] ) {
				$state = $site['states'][ $class['state'] ] ?? null;
				return $state && $state[0] ? [ [ 'kind' => 'state', 'url' => $state[0] ] ] : [];
			}
			return self::location_pages( self::city_key( $class['city']['name'], $class['city']['state'] ), $site );
		}
		if ( in_array( $class['intent'], [ 'near_me', 'attribute' ], true ) ) {
			if ( $class['named'] ) {
				return [];
			}
			if ( ! $class['topics'] ) {
				return [ [ 'kind' => 'places', 'url' => $site['places'] ] ];
			}
			$hubs = [];
			foreach ( $class['topics'] as $topic ) {
				$hub = $site['hubs'][ $topic ] ?? null;
				if ( $hub && $hub[1] && ! in_array( $hub[0], array_column( $hubs, 'url' ), true ) ) {
					$hubs[] = [ 'kind' => 'hub', 'url' => $hub[0] ];
				}
			}
			return $hubs;
		}
		return [];
	}

	/** @return array<int,array{kind:string,url:string,venues:int}> the city's own page, then any metro page it is part of */
	public static function location_pages( string $key, array $site ): array {
		$pages = [];
		$city = $site['cities'][ $key ] ?? null;
		if ( $city && '' !== $city[2] ) {
			$pages[] = [ 'kind' => 'city', 'url' => $city[2], 'venues' => (int) $city[3] ];
		}
		foreach ( $site['metros'] as $m ) {
			if ( in_array( $key, $m['cities'], true ) ) {
				$pages[] = [ 'kind' => 'metro', 'url' => $m['url'], 'venues' => (int) $m['venues'] ];
			}
		}
		return $pages;
	}

	/**
	 * Cities by monthly searches for somewhere to play, then by all searches
	 * (close variants counted once), with our coverage.
	 *
	 * @return array<int,array{name:string,state:string,volume:int,generic:int,keywords:int,top:string,venues:int,page:string,page_venues:int,coverage:string}>
	 */
	public static function cities( array $classified, array $site ): array {
		$groups = [];
		foreach ( $classified as $row ) {
			$c = $row['class'];
			if ( 'city' !== $c['intent'] || ! $c['city'] ) {
				continue;
			}
			$key = self::city_key( $c['city']['name'], $c['city']['state'] );
			$groups[ $key ]['city'] = $c['city'];
			$groups[ $key ]['all'][] = $row;
			if ( ! $c['named'] ) {
				$groups[ $key ]['generic'][] = $row;
			}
		}
		$venues = [];
		foreach ( $site['venues'] as $v ) {
			$k = self::city_key( (string) $v[2], (string) $v[3] );
			$venues[ $k ] = ( $venues[ $k ] ?? 0 ) + 1;
		}
		$out = [];
		foreach ( $groups as $key => $g ) {
			$generic = $g['generic'] ?? [];
			$pages = self::location_pages( $key, $site );
			$page = $pages[0] ?? null;
			$out[] = [
				'name'        => $g['city']['name'],
				'state'       => $g['city']['state'],
				'volume'      => Keywords::total( $g['all'] ),
				'generic'     => Keywords::total( $generic ),
				'keywords'    => count( $g['all'] ),
				'top'         => ( $generic[0] ?? $g['all'][0] )['keyword'],
				'venues'      => $venues[ $key ] ?? 0,
				'page'        => $page['url'] ?? '',
				'page_venues' => $page['venues'] ?? 0,
				'coverage'    => self::coverage( $page ),
			];
		}
		usort( $out, static fn( $a, $b ) => [ $b['generic'], $b['volume'], $a['name'] ] <=> [ $a['generic'], $a['volume'], $b['name'] ] );
		return $out;
	}

	public static function coverage( ?array $page ): string {
		if ( ! $page ) {
			return 'No page';
		}
		return $page['venues'] < self::COVERED ? 'Page, under ' . self::COVERED . ' venues' : 'Covered';
	}

	/**
	 * Attribute topics by monthly searches, whatever the keyword's intent, leaving
	 * out equipment shopping and searches for one business.
	 *
	 * @return array<int,array{topic:string,volume:int,play:int,keywords:int,near_me:int,with_city:int,top:string[],hub:?array}>
	 */
	public static function topics( array $classified, array $site ): array {
		$groups = [];
		foreach ( $classified as $row ) {
			$c = $row['class'];
			if ( 'other' === $c['intent'] || 'venue' === $c['intent'] || $c['named'] ) {
				continue;
			}
			foreach ( $c['topics'] as $topic ) {
				$groups[ $topic ][] = $row;
			}
		}
		$out = [];
		foreach ( $groups as $topic => $rows ) {
			$play = array_filter( $rows, static fn( $r ) => $r['class']['venue_seeking'] );
			$out[] = [
				'topic'     => $topic,
				'volume'    => Keywords::total( $rows ),
				'play'      => Keywords::total( array_values( $play ) ),
				'keywords'  => count( $rows ),
				'near_me'   => count( array_filter( $rows, static fn( $r ) => $r['class']['near_me'] ) ),
				'with_city' => count( array_filter( $rows, static fn( $r ) => 'city' === $r['class']['intent'] ) ),
				'top'       => array_slice( array_column( self::one_per_variant( $rows ), 'keyword' ), 0, 3 ),
				'hub'       => $site['hubs'][ $topic ] ?? null,
			];
		}
		usort( $out, static fn( $a, $b ) => [ $b['volume'], $a['topic'] ] <=> [ $a['volume'], $b['topic'] ] );
		return $out;
	}

	/**
	 * The highest-volume keywords no page of ours answers (close variants once),
	 * leaving out the "other" class: shopping, rules, swimming pools.
	 *
	 * @return array<int,array{keyword:string,volume:int,class:array,pages:array,variants:int}>
	 */
	public static function gaps( array $classified, int $limit = 50 ): array {
		$rows = array_values( array_filter( $classified, static fn( $r ) => 'other' !== $r['class']['intent'] && ! $r['pages'] ) );
		return array_slice( self::one_per_variant( $rows ), 0, $limit );
	}

	/**
	 * Keywords that count as one: close variants, and the searches for one
	 * business in one city, which share a volume ("breaker billiards clifton",
	 * "breakers pool hall clifton nj").
	 */
	public static function group_key( array $row ): string {
		$c = $row['class'];
		if ( $c['named'] && $c['city'] && $row['volume'] >= self::NAME_VOLUME ) {
			return self::city_key( $c['city']['name'], $c['city']['state'] ) . '|' . $row['volume'];
		}
		return Keywords::variant_key( $row['keyword'] );
	}

	/**
	 * Keywords two of our pages could both rank for: the same city and the same
	 * intent served by a city page and a metro page, or by two city pages.
	 *
	 * @return array<int,array{keyword:string,volume:int,pages:string[]}>
	 */
	public static function competing( array $classified ): array {
		$out = [];
		foreach ( $classified as $row ) {
			$urls = array_values( array_unique( array_column( $row['pages'], 'url' ) ) );
			if ( count( $urls ) > 1 ) {
				$out[] = [ 'keyword' => $row['keyword'], 'volume' => $row['volume'], 'pages' => $urls ];
			}
		}
		return $out;
	}

	/**
	 * Highest volume first, one keyword per group (group_key()), with the group's size.
	 *
	 * @return array<int,array>
	 */
	public static function one_per_variant( array $rows ): array {
		$seen = [];
		foreach ( $rows as $row ) {
			$key = self::group_key( $row );
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = $row + [ 'variants' => 0 ];
			} elseif ( $row['volume'] > $seen[ $key ]['volume'] ) {
				$seen[ $key ] = $row + [ 'variants' => $seen[ $key ]['variants'] ];
			}
			++$seen[ $key ]['variants'];
		}
		$out = array_values( $seen );
		usort( $out, static fn( $a, $b ) => [ $b['volume'], $a['keyword'] ] <=> [ $a['volume'], $b['keyword'] ] );
		return $out;
	}

	/* -------------------------------------------------------------- markdown */

	/**
	 * @param array{date:string,files:string[],rows:int,home:string} $meta
	 */
	public static function markdown( array $classified, array $site, array $meta ): string {
		$home = rtrim( $meta['home'], '/' );
		$path = static fn( string $url ): string => '' === $url ? '' : ( str_starts_with( $url, $home ) ? substr( $url, strlen( $home ) ) : $url );
		$cities = self::cities( $classified, $site );
		$md = [];
		$md[] = '# Keyword demand report, ' . $meta['date'];
		$md[] = '';
		$md[] = sprintf( '%s keywords from %s rows in %d export%s (%s); %d venues, %d city pages and %d metro pages on the site.', number_format( count( $classified ) ), number_format( $meta['rows'] ), count( $meta['files'] ), 1 === count( $meta['files'] ) ? '' : 's', implode( ', ', $meta['files'] ), count( $site['venues'] ), count( array_filter( $site['cities'], static fn( $c ) => '' !== $c[2] ) ), count( $site['metros'] ) );
		$md[] = '';
		$md[] = 'Volumes are US monthly searches. Keyword tools give close variants (plurals, "billiard"/"billiards", word order, "near me"/"nearby") one shared volume, so every total counts each variant group once. "Business names" are searches for one particular hall or bar ("zanzibar billiards denver") rather than for somewhere to play.';
		$md[] = '';
		$md[] = '## Keywords by intent';
		$md[] = '';
		$md[] = '| Intent | Keywords | Searches/mo |';
		$md[] = '|---|--:|--:|';
		foreach ( self::INTENTS as $intent ) {
			$rows = array_values( array_filter( $classified, static fn( $r ) => $intent === $r['class']['intent'] ) );
			$md[] = sprintf( '| %s | %s | %s |', $intent, number_format( count( $rows ) ), number_format( Keywords::total( $rows ) ) );
		}
		$md[] = '';
		$md[] = '## Cities by search volume';
		$md[] = '';
		$md[] = sprintf( 'Keywords naming a US city of 50,000+ people, ranked by searches for somewhere to play. The total also counts searches for one business, which say more about that business than about the city ("breakers billiards clifton nj"). Coverage: our city page, or the metro page the city is part of, with %d+ venues is covered.', self::COVERED );
		$md[] = '';
		$md[] = '| # | City | Place-to-play searches/mo | Total searches/mo | Keywords | Top keyword | Our venues | Page | Coverage |';
		$md[] = '|--:|---|--:|--:|--:|---|--:|---|---|';
		foreach ( $cities as $i => $c ) {
			$md[] = sprintf( '| %d | %s, %s | %s | %s | %d | %s | %d | %s | %s |', $i + 1, $c['name'], $c['state'], number_format( $c['generic'] ), number_format( $c['volume'] ), $c['keywords'], self::cell( $c['top'] ), $c['venues'], $c['page'] ? $path( $c['page'] ) . ( 'metro' === self::page_kind( $c['page'], $site ) ? ' (metro)' : '' ) : '', $c['coverage'] );
		}
		$states = self::states( $classified, $site );
		if ( $states ) {
			$md[] = '';
			$md[] = 'Keywords naming a state but no city:';
			$md[] = '';
			$md[] = '| State | Searches/mo | Keywords | Top keyword | Our venues | Page |';
			$md[] = '|---|--:|--:|---|--:|---|';
			foreach ( $states as $s ) {
				$md[] = sprintf( '| %s | %s | %d | %s | %d | %s |', $s['state'], number_format( $s['volume'] ), $s['keywords'], self::cell( $s['top'] ), $s['venues'], $path( $s['page'] ) );
			}
		}
		$md[] = '';
		$md[] = '## Attribute topics (hub page candidates)';
		$md[] = '';
		$md[] = 'Every keyword with the topic, whatever else it names, except equipment shopping and searches for one business. "Looking to play" counts only keywords that ask for somewhere to play (pool hall, bar, near me, leagues ...); a bare "9 foot pool table" is usually someone buying one.';
		$md[] = '';
		$md[] = '| Topic | Searches/mo | Looking to play | Keywords | Near me | With a city | Top keywords | Hub page |';
		$md[] = '|---|--:|--:|--:|--:|--:|---|---|';
		foreach ( self::topics( $classified, $site ) as $t ) {
			$md[] = sprintf( '| %s | %s | %s | %d | %d | %d | %s | %s |', $t['topic'], number_format( $t['volume'] ), number_format( $t['play'] ), $t['keywords'], $t['near_me'], $t['with_city'], self::cell( implode( '; ', $t['top'] ) ), $t['hub'] ? $path( $t['hub'][0] ) . ( $t['hub'][1] ? '' : ' (noindex)' ) : 'none' );
		}
		$md[] = '';
		$md[] = '## Top 50 keywords with no page';
		$md[] = '';
		$md[] = 'No venue, city, metro, state or hub page answers these. "other" keywords (shopping, rules, swimming pools) are left out. A city we cover still shows up here when the keyword names a business we do not list.';
		$md[] = '';
		$md[] = '| # | Keyword | Searches/mo | Variants | Intent | Location | Topics |';
		$md[] = '|--:|---|--:|--:|---|---|---|';
		foreach ( self::gaps( $classified ) as $i => $g ) {
			$c = $g['class'];
			$md[] = sprintf( '| %d | %s | %s | %d | %s | %s | %s |', $i + 1, self::cell( $g['keyword'] ), number_format( $g['volume'] ), $g['variants'], $c['intent'] . ( $c['named'] ? ' (business name)' : '' ), self::location_label( $c ), implode( ', ', $c['topics'] ) );
		}
		$md[] = '';
		$md[] = '## Keywords two of our pages could compete for';
		$md[] = '';
		$competing = self::competing( $classified );
		if ( ! $competing ) {
			$md[] = 'None: every city keyword maps to a single page of ours.';
		} else {
			$md[] = 'Same city, same intent, more than one page: a city page inside a metro page, or two pages for one city. Pick one page to target each keyword and link the other to it.';
			$md[] = '';
			$md[] = '| Keyword | Searches/mo | Pages |';
			$md[] = '|---|--:|---|';
			foreach ( $competing as $c ) {
				$md[] = sprintf( '| %s | %s | %s |', self::cell( $c['keyword'] ), number_format( $c['volume'] ), implode( ', ', array_map( $path, $c['pages'] ) ) );
			}
		}
		$md[] = '';
		$md[] = '## How keywords are classified';
		$md[] = '';
		$md[] = '- **venue**: names one of our listings (its full title, or its distinctive part such as "rack city").';
		$md[] = '- **city**: names a US city of 50,000+ people (Census 2023 estimates) or a state. Ambiguous city names (Mobile, Columbia, Hollywood, Vancouver ...) count only with their state. NYC boroughs count as New York.';
		$md[] = '- **near_me**: "near me", "nearby", "close to me" without a place.';
		$md[] = '- **attribute**: a table size, bar box, brand, free pool, leagues (APA, BCA), open late, 24 hour, tournaments or lessons, without a place.';
		$md[] = '- **other**: everything else, including buying, moving or fixing a table, rules, and swimming pools.';
		$md[] = '';
		$md[] = 'Business names are spotted three ways: words beyond the generic ones ("zanzibar billiards denver"), an "&" ("8 ball sports bar & billiards"), and a place keyword with the same volume as a business search in that city, or with far more searches than the city\'s "pool hall" keywords ("springfield billiards").';
		$md[] = '';
		$md[] = 'Known limits: a business we do not list that is named after a city ("Brooklyn Billiards" elsewhere) can read as a search in that city when its volume is small; and a city under 50,000 people is not recognized, so its keywords fall to the state or the other classes.';
		$md[] = '';
		return implode( "\n", $md );
	}

	/** @return array<int,array{state:string,volume:int,keywords:int,top:string,venues:int,page:string}> */
	public static function states( array $classified, array $site ): array {
		$groups = [];
		foreach ( $classified as $row ) {
			$c = $row['class'];
			if ( 'city' === $c['intent'] && ! $c['city'] && $c['state'] ) {
				$groups[ $c['state'] ][] = $row;
			}
		}
		$out = [];
		foreach ( $groups as $state => $rows ) {
			$out[] = [
				'state'    => $state,
				'volume'   => Keywords::total( $rows ),
				'keywords' => count( $rows ),
				'top'      => $rows[0]['keyword'],
				'venues'   => (int) ( $site['states'][ $state ][1] ?? 0 ),
				'page'     => (string) ( $site['states'][ $state ][0] ?? '' ),
			];
		}
		usort( $out, static fn( $a, $b ) => [ $b['volume'], $a['state'] ] <=> [ $a['volume'], $b['state'] ] );
		return $out;
	}

	private static function page_kind( string $url, array $site ): string {
		return in_array( $url, array_column( $site['metros'], 'url' ), true ) ? 'metro' : 'city';
	}

	private static function location_label( array $class ): string {
		if ( $class['city'] ) {
			return $class['city']['name'] . ', ' . $class['city']['state'];
		}
		return (string) $class['state'];
	}

	private static function cell( string $text ): string {
		return str_replace( '|', '\|', $text );
	}
}
