<?php
/**
 * Yes / No / Unknown values.
 *
 * Storage convention used across the plugin:
 *  - Taxonomy-backed attributes (amenities, table brands, pricing, ...):
 *      term assigned                 => yes
 *      term slug in the `_ppn_known_no` meta list => no
 *      neither                       => unknown
 *  - Counts (tables per size): '' (meta missing) => unknown, '0' => no, positive integer => yes.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Tri_State {

	public const YES     = 'yes';
	public const NO      = 'no';
	public const UNKNOWN = 'unknown';

	public static function from_term( bool $has_term, bool $known_no ): string {
		if ( $has_term ) {
			return self::YES;
		}
		return $known_no ? self::NO : self::UNKNOWN;
	}

	/**
	 * @param mixed $raw Stored count value.
	 */
	public static function from_count( $raw ): string {
		$count = self::count( $raw );
		if ( null === $count ) {
			return self::UNKNOWN;
		}
		return $count > 0 ? self::YES : self::NO;
	}

	/**
	 * Parse a stored count. Returns null when unknown.
	 *
	 * @param mixed $raw
	 */
	public static function count( $raw ): ?int {
		if ( is_int( $raw ) ) {
			return max( 0, $raw );
		}
		if ( is_array( $raw ) ) {
			$raw = reset( $raw );
		}
		$raw = trim( (string) $raw );
		if ( '' === $raw || ! preg_match( '/^\d+$/', $raw ) ) {
			return null;
		}
		return (int) $raw;
	}

	public static function is_valid( string $value ): bool {
		return in_array( $value, [ self::YES, self::NO, self::UNKNOWN ], true );
	}
}
