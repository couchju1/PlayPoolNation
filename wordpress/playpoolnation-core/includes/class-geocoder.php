<?php
/**
 * Geocoding provider boundary.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side geocoding through the Google key configured in My Listing.
 * Returns [] when no key is configured or the address cannot be found.
 */
final class Geocoder {
	public static function geocode( string $address ): array {
		// Lets tests (or another provider) answer without calling Google.
		$pre = apply_filters( 'ppn_geocode_pre', null, $address );
		if ( is_array( $pre ) ) {
			return $pre;
		}
		$maps = json_decode( (string) get_option( 'mylisting_maps' ), true );
		$key = is_array( $maps ) ? (string) ( $maps['gmaps_api_key'] ?? '' ) : '';
		if ( '' === $key || '' === trim( $address ) ) {
			return [];
		}
		$res = wp_remote_get( add_query_arg( [ 'address' => rawurlencode( $address ), 'region' => 'us', 'key' => $key ], 'https://maps.googleapis.com/maps/api/geocode/json' ), [ 'timeout' => 10 ] );
		$body = is_wp_error( $res ) ? [] : json_decode( wp_remote_retrieve_body( $res ), true );
		$first = $body['results'][0] ?? null;
		if ( ! $first || 'OK' !== ( $body['status'] ?? '' ) ) {
			return [];
		}
		return [
			'address' => preg_replace( '/, USA$/', '', (string) $first['formatted_address'] ),
			'lat'     => (float) $first['geometry']['location']['lat'],
			'lng'     => (float) $first['geometry']['location']['lng'],
		];
	}
}
