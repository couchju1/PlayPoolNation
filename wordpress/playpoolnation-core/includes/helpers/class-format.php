<?php
/**
 * Formatting helpers. Pure functions so they are unit tested.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Format {

	public const SIZE_LABELS = [
		'7-foot'  => "7'",
		'8-foot'  => "8'",
		'9-foot'  => "9'",
		'snooker' => 'Snooker',
		'carom'   => 'Carom',
	];

	private const DAYS = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];

	public static function slugify( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES );
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );
			if ( false !== $converted ) {
				$text = $converted;
			}
		}
		$text = strtolower( str_replace( [ '&', '+' ], ' and ', $text ) );
		$text = preg_replace( "/['`\"]/", '', $text );
		$text = preg_replace( '/[^a-z0-9]+/', '-', $text );
		return trim( $text, '-' );
	}

	/**
	 * Stable, readable venue slug: "rack-city-billiards-sioux-falls-sd".
	 * The city is not repeated when the name already ends with it.
	 */
	public static function venue_slug( string $name, string $city, string $state_abbr ): string {
		$name_slug = self::slugify( $name );
		$city_slug = self::slugify( $city );
		$parts = [ $name_slug ];
		if ( '' !== $city_slug && ! str_ends_with( $name_slug, $city_slug ) ) {
			$parts[] = $city_slug;
		}
		if ( '' !== $state_abbr ) {
			$parts[] = strtolower( $state_abbr );
		}
		return implode( '-', array_filter( $parts, 'strlen' ) );
	}

	/** "7' & 9'", "7', 8' & 9'", "9' & Snooker". */
	public static function sizes_label( array $slugs ): string {
		$labels = [];
		foreach ( self::SIZE_LABELS as $slug => $label ) {
			if ( in_array( $slug, $slugs, true ) ) {
				$labels[] = $label;
			}
		}
		return self::join_list( $labels );
	}

	public static function join_list( array $items ): string {
		$items = array_values( array_filter( array_map( 'strval', $items ), 'strlen' ) );
		$n = count( $items );
		if ( $n <= 1 ) {
			return $items[0] ?? '';
		}
		return implode( ', ', array_slice( $items, 0, -1 ) ) . ' & ' . $items[ $n - 1 ];
	}

	public static function money( $amount ): string {
		if ( '' === $amount || null === $amount || ! is_numeric( $amount ) ) {
			return '';
		}
		$amount = (float) $amount;
		return '$' . ( floor( $amount ) == $amount ? number_format( $amount, 0 ) : number_format( $amount, 2 ) );
	}

	/** Minutes since midnight to "1 AM", "11:30 PM", "Midnight", "Noon". */
	public static function clock( int $minutes ): string {
		$minutes = ( ( $minutes % 1440 ) + 1440 ) % 1440;
		if ( 0 === $minutes ) {
			return 'Midnight';
		}
		if ( 720 === $minutes ) {
			return 'Noon';
		}
		$h = intdiv( $minutes, 60 );
		$m = $minutes % 60;
		$suffix = $h >= 12 ? 'PM' : 'AM';
		$h12 = $h % 12 ?: 12;
		return $m ? sprintf( '%d:%02d %s', $h12, $m, $suffix ) : sprintf( '%d %s', $h12, $suffix );
	}

	/** "19:00" or "7:00 PM" to minutes since midnight, or null if unparseable. */
	public static function parse_time( string $value ): ?int {
		$value = strtoupper( trim( $value ) );
		if ( ! preg_match( '/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)?$/', $value, $m ) ) {
			return null;
		}
		$h = (int) $m[1];
		$min = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
		$ampm = $m[3] ?? '';
		if ( $min > 59 || $h > 23 || ( $ampm && ( $h < 1 || $h > 12 ) ) ) {
			return null;
		}
		if ( 'PM' === $ampm && $h < 12 ) {
			$h += 12;
		} elseif ( 'AM' === $ampm && 12 === $h ) {
			$h = 0;
		}
		return $h * 60 + $min;
	}

	/**
	 * Open/closed summary from weekly ranges.
	 *
	 * @param array<int,array{0:int,1:int}> $ranges  Minutes from Monday 00:00 (local), as stored by My Listing.
	 * @param \DateTimeImmutable             $now     Current time in the venue's timezone.
	 * @return array{state:string,label:string} state is open|closed|unknown.
	 */
	public static function open_status( array $ranges, \DateTimeImmutable $now ): array {
		if ( ! $ranges ) {
			return [ 'state' => 'unknown', 'label' => '' ];
		}
		$week = 10080;
		$minute = ( (int) $now->format( 'N' ) - 1 ) * 1440 + (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );

		// Normalize and merge ranges (wrapping past Sunday night back to Monday).
		$norm = [];
		foreach ( $ranges as $r ) {
			[ $s, $e ] = [ (int) $r[0], (int) $r[1] ];
			if ( $e <= $s ) {
				continue;
			}
			if ( $e - $s >= $week ) {
				return [ 'state' => 'open', 'label' => 'Open 24 hours' ];
			}
			$norm[] = [ $s, $e ];
		}
		usort( $norm, static fn( $a, $b ) => $a[0] <=> $b[0] );
		if ( ! $norm ) {
			return [ 'state' => 'unknown', 'label' => '' ];
		}
		$total = array_sum( array_map( static fn( $r ) => $r[1] - $r[0], $norm ) );
		if ( $total >= $week - 1 ) {
			return [ 'state' => 'open', 'label' => 'Open 24 hours' ];
		}

		foreach ( $norm as [ $s, $e ] ) {
			foreach ( [ 0, $week, -$week ] as $shift ) {
				if ( $minute >= $s + $shift && $minute < $e + $shift ) {
					$close = $e;
					// A range ending exactly at the start of another range continues.
					foreach ( $norm as [ $s2, $e2 ] ) {
						if ( ( $s2 === $close % $week ) && $e2 > $s2 ) {
							$close = $close + ( $e2 - $s2 );
						}
					}
					return [ 'state' => 'open', 'label' => 'Open until ' . self::clock( $close % 1440 ) ];
				}
			}
		}

		// Closed: find the next opening.
		$best = null;
		foreach ( $norm as [ $s ] ) {
			$delta = ( $s - $minute + $week ) % $week;
			if ( null === $best || $delta < $best[0] ) {
				$best = [ $delta, $s ];
			}
		}
		$open_day = intdiv( $best[1] % $week, 1440 );
		$today = intdiv( $minute, 1440 );
		$time = self::clock( $best[1] % 1440 );
		$label = ( $open_day === $today && $best[0] < 1440 ) ? "Opens {$time}" : 'Opens ' . self::DAYS[ $open_day ] . " {$time}";
		return [ 'state' => 'closed', 'label' => $label ];
	}
}
