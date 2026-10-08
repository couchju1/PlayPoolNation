<?php
/**
 * Venue "About" text from known facts. Deterministic, plain language, and it states
 * unknowns plainly instead of guessing. Pure function of its input so it is unit tested.
 *
 * Style: short sentences, contractions, no dashes or semicolons, no hype.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class About_Text {

	/** Venue type slug => [ noun, needs "with pool tables" ]. */
	private const TYPES = [
		'pool-halls'            => [ 'pool hall', false ],
		'billiards-lounge'      => [ 'billiards lounge', false ],
		'bars-with-pool-tables' => [ 'bar', true ],
		'sports-bar'            => [ 'sports bar', true ],
		'bowling-center'        => [ 'bowling center', true ],
		'recreation'            => [ 'recreation center', true ],
		'private-clubs'         => [ 'private club', true ],
	];

	private const SIZE_WORDS = [ '7-foot' => '7-foot', '8-foot' => '8-foot', '9-foot' => '9-foot', 'snooker' => 'snooker', 'carom' => 'carom' ];

	private const DAY_PLURALS = [ 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays', 'Sundays' ];

	private const NUMBER_WORDS = [ 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine' ];

	/**
	 * @param array{
	 *   name:string, type:string, street?:string, city_state?:string,
	 *   tables?:?int, size_counts?:array<string,int>, sizes?:string[], brands?:string[],
	 *   games?:string[], pricing?:string[], hourly_rate?:string, game_price?:string,
	 *   leagues?:bool, photo?:bool, claimed?:bool,
	 *   hours?:array<int,array{0:int,1:int}>, status?:string
	 * } $f Facts with a recorded source only. Missing keys mean unknown. Hours are
	 *      minutes from Monday 00:00, as My Listing stores them.
	 * @return array{sentences:string[],claim:?array{lead:string,action:string,rest:string}}
	 */
	public static function build( array $f ): array {
		$name = trim( (string) $f['name'] );
		$sentences = [ self::identity( $f ) ];
		foreach ( array_merge( self::hours( $f ), self::tables( $f ) ) as $s ) {
			$sentences[] = $s;
		}
		$brands = array_values( array_filter( (array) ( $f['brands'] ?? [] ), static fn( $b ) => '' !== trim( (string) $b ) && 'other brand' !== strtolower( (string) $b ) ) );
		if ( $brands ) {
			$sentences[] = sprintf( "They're %s tables.", self::and_list( $brands ) );
		}
		$price = self::price( $f );
		if ( '' !== $price ) {
			$sentences[] = $price;
		}
		$games = array_map( 'strtolower', array_values( array_filter( (array) ( $f['games'] ?? [] ) ) ) );
		if ( $games ) {
			$sentences[] = sprintf( "There's %s too.", self::and_list( array_map( [ __CLASS__, 'game' ], $games ) ) );
		}
		if ( ! empty( $f['leagues'] ) ) {
			$sentences[] = 'League nights are listed below.';
		}

		$claim = null;
		if ( empty( $f['claimed'] ) ) {
			$missing = self::unknowns( $f );
			$claim = [
				'lead'   => sprintf( 'Own %s?', $name ),
				'action' => 'Claim it',
				'rest'   => $missing ? ' to add ' . self::and_list( $missing ) . '.' : ' to keep it current.',
			];
		}
		return [ 'sentences' => $sentences, 'claim' => $claim ];
	}

	/** "Rack City Billiards is a pool hall at 309 S Bahnson Ave in Sioux Falls, SD." */
	public static function identity( array $f ): string {
		[ $noun, $with_tables ] = self::TYPES[ $f['type'] ?? '' ] ?? [ 'place to play pool', false ];
		$street = trim( (string) ( $f['street'] ?? '' ) );
		$where = trim( (string) ( $f['city_state'] ?? '' ) );
		$at = ( '' !== $street ? ' ' . ( preg_match( '/^\d/', $street ) ? 'at' : 'on' ) . ' ' . $street : '' ) . ( '' !== $where ? ' in ' . $where : '' );
		$article = preg_match( '/^[aeiou]/i', $noun ) ? 'an' : 'a';
		return sprintf( '%s is %s %s%s%s.', trim( (string) $f['name'] ), $article, $noun, $at, $with_tables ? ' with pool tables' : '' );
	}

	/**
	 * "It's closed on Mondays. It stays open until 2 AM on Fridays and Saturdays."
	 *
	 * @return string[]
	 */
	public static function hours( array $f ): array {
		$status = (string) ( $f['status'] ?? '' );
		if ( 'temporarily-closed' === $status ) {
			return [ "It's temporarily closed." ];
		}
		if ( 'permanently-closed' === $status ) {
			return [ "It's permanently closed." ];
		}
		$ranges = array_values( array_filter( (array) ( $f['hours'] ?? [] ), static fn( $r ) => (int) $r[1] > (int) $r[0] ) );
		if ( ! $ranges ) {
			return [];
		}
		$week = 10080;
		$total = array_sum( array_map( static fn( $r ) => min( $week, (int) $r[1] - (int) $r[0] ), $ranges ) );
		if ( $total >= $week - 1 ) {
			return [ "It's open 24 hours a day." ];
		}
		// My Listing splits Sunday night at the end of the week ([…, 10080] then [0, 120]):
		// a range that starts where another ends belongs to that earlier night.
		$ranges = array_map( static fn( $r ) => [ (int) $r[0], (int) $r[1] ], $ranges );
		foreach ( $ranges as $i => [ $s, $e ] ) {
			if ( 0 !== $s % 1440 ) {
				continue;
			}
			foreach ( $ranges as $j => [ $s2, $e2 ] ) {
				if ( $i !== $j && isset( $ranges[ $j ] ) && ( $e2 === $s || ( 0 === $s && $week === $e2 ) ) ) {
					$ranges[ $j ][1] += $e - $s;
					unset( $ranges[ $i ] );
					break;
				}
			}
		}
		// Latest closing time for each day the venue opens, in minutes after that day's midnight.
		$close = [];
		foreach ( $ranges as [ $s, $e ] ) {
			$day = intdiv( (int) $s, 1440 ) % 7;
			$close[ $day ] = max( $close[ $day ] ?? 0, (int) $e - intdiv( (int) $s, 1440 ) * 1440 );
		}
		ksort( $close );
		$closed = array_diff( range( 0, 6 ), array_keys( $close ) );
		$out = [ $closed ? sprintf( "It's closed on %s.", self::and_list( array_map( static fn( $d ) => self::DAY_PLURALS[ $d ], $closed ) ) ) : "It's open every day." ];

		$latest = max( $close );
		if ( $latest >= 1440 ) {
			$time = 1440 === $latest ? 'midnight' : Format::clock( $latest - 1440 );
			$late = array_keys( array_filter( $close, static fn( $c ) => $c === $latest ) );
			if ( count( $late ) < count( $close ) ) {
				$out[] = sprintf( 'It stays open until %s on %s.', $time, self::and_list( array_map( static fn( $d ) => self::DAY_PLURALS[ $d ], $late ) ) );
			} elseif ( $closed ) {
				$out[] = sprintf( 'The other nights it stays open until %s.', $time );
			} else {
				$out = [ sprintf( "It's open every day until %s.", $time ) ];
			}
		}
		return $out;
	}

	/** @return string[] */
	private static function tables( array $f ): array {
		$total = isset( $f['tables'] ) && null !== $f['tables'] ? (int) $f['tables'] : null;
		$counts = array_filter( (array) ( $f['size_counts'] ?? [] ), static fn( $n ) => (int) $n > 0 );
		$sizes = array_values( array_unique( array_merge( array_keys( $counts ), (array) ( $f['sizes'] ?? [] ) ) ) );
		$sizes = array_values( array_intersect( array_keys( self::SIZE_WORDS ), $sizes ) );

		if ( null !== $total && 0 === $total ) {
			return [ "It doesn't have pool tables right now." ];
		}
		if ( null === $total && $counts ) {
			$total = (int) array_sum( $counts );
		}
		if ( null === $total ) {
			if ( ! $sizes ) {
				return [ "We don't know the table count or sizes yet." ];
			}
			return [ sprintf( 'The tables are %s. We don\'t know how many there are yet.', self::and_list( array_map( static fn( $s ) => self::SIZE_WORDS[ $s ], $sizes ) ) ) ];
		}

		$out = [ sprintf( 'It has %s %s.', self::number( $total ), 1 === $total ? 'table' : 'tables' ) ];
		if ( $counts && array_sum( $counts ) === $total ) {
			arsort( $counts );
			$main = (string) array_key_first( $counts );
			if ( 1 === count( $counts ) ) {
				$out[] = 1 === $total ? sprintf( "It's %s.", self::a( self::SIZE_WORDS[ $main ] ) ) : sprintf( 'All of them are %s.', self::SIZE_WORDS[ $main ] );
			} elseif ( $counts[ $main ] * 2 > $total ) {
				$rest = [];
				foreach ( array_slice( $counts, 1, null, true ) as $size => $n ) {
					$rest[] = self::count_of( $n, self::SIZE_WORDS[ $size ] );
				}
				$out[] = sprintf( 'Most are %s, plus %s.', self::SIZE_WORDS[ $main ], self::and_list( $rest ) );
			} else {
				$parts = [];
				foreach ( $counts as $size => $n ) {
					$parts[] = self::number( (int) $n ) . ' ' . self::SIZE_WORDS[ $size ];
				}
				$out[] = sprintf( 'That\'s %s.', self::and_list( $parts ) );
			}
		} elseif ( $sizes ) {
			$out[] = sprintf( 'They come in %s.', self::and_list( array_map( static fn( $s ) => self::SIZE_WORDS[ $s ], $sizes ) ) );
		} else {
			$out[] = "We don't know the sizes yet.";
		}
		return $out;
	}

	private static function price( array $f ): string {
		$hourly = trim( (string) ( $f['hourly_rate'] ?? '' ) );
		$game = trim( (string) ( $f['game_price'] ?? '' ) );
		$models = (array) ( $f['pricing'] ?? [] );
		if ( '' !== $hourly && '' !== $game ) {
			return sprintf( 'Pool is %s an hour or %s a game.', $hourly, $game );
		}
		if ( '' !== $hourly ) {
			return sprintf( 'Pool is %s an hour.', $hourly );
		}
		if ( '' !== $game ) {
			return sprintf( 'Pool is %s a game.', $game );
		}
		if ( in_array( 'free-pool', $models, true ) ) {
			return 'Pool is free here.';
		}
		if ( in_array( 'hourly', $models, true ) ) {
			return 'You pay for pool by the hour.';
		}
		if ( in_array( 'coin-op', $models, true ) ) {
			return 'The tables are coin-op.';
		}
		if ( in_array( 'per-game', $models, true ) ) {
			return 'You pay for pool by the game.';
		}
		return '';
	}

	/** @return string[] What an owner could add, in the order players ask about it. */
	public static function unknowns( array $f ): array {
		$missing = [];
		$total_known = isset( $f['tables'] ) && null !== $f['tables'];
		if ( ! $total_known && empty( $f['size_counts'] ) ) {
			$missing[] = 'the table count';
		}
		if ( empty( $f['sizes'] ) && empty( $f['size_counts'] ) && ! ( $total_known && 0 === (int) $f['tables'] ) ) {
			$missing[] = 'table sizes';
		}
		if ( '' === trim( (string) ( $f['hourly_rate'] ?? '' ) ) && '' === trim( (string) ( $f['game_price'] ?? '' ) ) ) {
			$missing[] = 'hourly rates';
		}
		if ( empty( $f['leagues'] ) ) {
			$missing[] = 'league nights';
		}
		if ( empty( $f['photo'] ) ) {
			$missing[] = 'photos';
		}
		return $missing;
	}

	/** "a, b and c". */
	public static function and_list( array $items ): string {
		$items = array_values( array_filter( array_map( 'strval', $items ), 'strlen' ) );
		$last = array_pop( $items );
		return $items ? implode( ', ', $items ) . ' and ' . $last : (string) $last;
	}

	private static function number( int $n ): string {
		return self::NUMBER_WORDS[ $n ] ?? (string) $n;
	}

	/** "two 9-foot tables", "one snooker table". */
	private static function count_of( int $n, string $size ): string {
		return self::number( $n ) . ' ' . $size . ' ' . ( 1 === $n ? 'table' : 'tables' );
	}

	private static function a( string $size ): string {
		return ( preg_match( '/^(8|[aeiou])/i', $size ) ? 'an ' : 'a ' ) . $size . ' table';
	}

	private static function game( string $game ): string {
		return 'arcade' === $game ? 'an arcade' : $game;
	}
}
