<?php
/**
 * US address helpers. Pure functions: no WordPress dependency, so they are unit tested.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Address {

	public const STATES = [
		'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
		'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'Washington, D.C.',
		'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois',
		'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
		'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
		'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
		'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
		'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon',
		'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
		'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
		'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
		'PR' => 'Puerto Rico',
	];

	/**
	 * Split a one-line US address ("110 E 11th St, New York, NY 10003[, USA]") into parts.
	 * Unknown parts are returned as empty strings; nothing is guessed.
	 *
	 * @return array{street:string,city:string,state:string,postal_code:string,country:string}
	 */
	public static function parse( string $address ): array {
		$out = [ 'street' => '', 'city' => '', 'state' => '', 'postal_code' => '', 'country' => '' ];
		$parts = array_values( array_filter( array_map( 'trim', explode( ',', $address ) ), 'strlen' ) );
		if ( ! $parts ) {
			return $out;
		}

		$last = end( $parts );
		if ( preg_match( '/^(USA|US|United States( of America)?)$/i', $last ) ) {
			$out['country'] = 'US';
			array_pop( $parts );
		}

		// "NY 10003", "NY 10003-1234" or just "NY".
		$tail = $parts ? end( $parts ) : '';
		if ( preg_match( '/^([A-Z]{2})(?:\s+(\d{5})(?:-\d{4})?)?$/', $tail, $m ) && isset( self::STATES[ $m[1] ] ) ) {
			$out['state'] = $m[1];
			$out['postal_code'] = $m[2] ?? '';
			$out['country'] = 'US';
			array_pop( $parts );
			if ( $parts ) {
				$out['city'] = (string) array_pop( $parts );
			}
		}

		// Drop a Google plus-code prefix such as "773M+R8".
		if ( $parts && preg_match( '/^[A-Z0-9]{4}\+[A-Z0-9]{2,3}$/', $parts[0] ) ) {
			array_shift( $parts );
		}
		$out['street'] = implode( ', ', $parts );
		return $out;
	}

	public static function state_name( string $abbr ): string {
		return self::STATES[ strtoupper( $abbr ) ] ?? '';
	}

	public static function state_abbr( string $name ): string {
		$key = array_search( strtolower( trim( $name ) ), array_map( 'strtolower', self::STATES ), true );
		return false === $key ? '' : (string) $key;
	}
}
