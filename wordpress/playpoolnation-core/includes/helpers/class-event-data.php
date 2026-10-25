<?php
/**
 * Listing click events: what a request may contain, how a referrer becomes a
 * traffic source, and how old events roll up into monthly counts. Pure
 * functions, so they are unit tested without WordPress.
 *
 * Nothing here identifies a person: no IP address, user agent, user ID or full
 * referrer is ever part of an event.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Event_Data {

	public const EVENTS   = [ 'call', 'directions', 'website', 'save', 'share', 'suggest_edit', 'claim' ];
	public const CONTEXTS = [ 'venue_page', 'city_page', 'hub_page', 'card', 'my_pool' ];
	public const DEVICES  = [ 'mobile', 'desktop' ];
	public const SOURCES  = [ 'google', 'bing', 'facebook', 'reddit', 'direct', 'other' ];

	/** A venue page view: counted per venue per day, not stored as an event row. */
	public const VIEW = 'view';

	/** Raw events older than this many months are rolled into monthly counts. */
	public const KEEP_MONTHS = 13;

	/**
	 * The traffic source bucket for a referrer host name (never stored itself).
	 * No referrer, or one from this site, is direct.
	 */
	public static function source( string $host, string $own_host ): string {
		$host = strtolower( trim( $host, ". \t\n" ) );
		$own = (string) preg_replace( '#^www\.#', '', strtolower( $own_host ) );
		if ( '' === $host || $host === $own || str_ends_with( $host, '.' . $own ) ) {
			return 'direct';
		}
		if ( preg_match( '#(^|\.)google(\.[a-z]{2,3}){1,2}$#', $host ) || 'com.google.android.googlequicksearchbox' === $host ) {
			return 'google';
		}
		if ( preg_match( '#(^|\.)bing\.com$#', $host ) ) {
			return 'bing';
		}
		if ( preg_match( '#(^|\.)(facebook\.com|fb\.com|fb\.me)$#', $host ) || 'com.facebook.katana' === $host ) {
			return 'facebook';
		}
		if ( preg_match( '#(^|\.)(reddit\.com|redd\.it)$#', $host ) ) {
			return 'reddit';
		}
		return 'other';
	}

	/**
	 * Check an event request against the allow-lists. Returns the row to store
	 * (without the city, which comes from the venue), or null when anything is off.
	 *
	 * @param array<string,mixed> $raw venue, type, where, path, device, ref (referrer host)
	 * @return array{venue_id:int,event_type:string,context:string,path:string,device:string,source:string}|null
	 */
	public static function validate( array $raw, string $own_host ): ?array {
		$venue = is_scalar( $raw['venue'] ?? null ) ? (string) $raw['venue'] : '';
		$type = is_string( $raw['type'] ?? null ) ? $raw['type'] : '';
		$where = is_string( $raw['where'] ?? null ) ? $raw['where'] : '';
		$device = is_string( $raw['device'] ?? null ) ? $raw['device'] : '';
		$ref = is_string( $raw['ref'] ?? null ) ? strtolower( $raw['ref'] ) : '';
		if ( ! preg_match( '#^[1-9][0-9]{0,18}$#', $venue ) || ! in_array( $device, self::DEVICES, true ) ) {
			return null;
		}
		if ( self::VIEW === $type ) {
			$where = 'venue_page';
		} elseif ( ! in_array( $type, self::EVENTS, true ) || ! in_array( $where, self::CONTEXTS, true ) ) {
			return null;
		}
		$path = self::path( is_string( $raw['path'] ?? null ) ? $raw['path'] : '' );
		if ( null === $path || ( '' !== $ref && ! preg_match( '#^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$#', $ref ) ) ) {
			return null;
		}
		return [
			'venue_id'   => (int) $venue,
			'event_type' => $type,
			'context'    => $where,
			'path'       => $path,
			'device'     => $device,
			'source'     => self::source( $ref, $own_host ),
		];
	}

	/** The page path without query string or fragment, or null when it is not a plain site path. */
	public static function path( string $path ): ?string {
		$path = (string) preg_replace( '#[?\#].*$#s', '', $path );
		if ( '' === $path || '/' !== $path[0] || strlen( $path ) > 200 || ! preg_match( '#^/[A-Za-z0-9/_.~%-]*$#', $path ) || str_contains( $path, '//' ) ) {
			return null;
		}
		return $path;
	}

	/** Raw rows older than this (UTC) are rolled up: whole months only, KEEP_MONTHS back. */
	public static function rollup_cutoff( int $now ): string {
		$month = gmdate( 'Y-m-01', $now );
		return gmdate( 'Y-m-d 00:00:00', (int) strtotime( $month . ' -' . self::KEEP_MONTHS . ' months UTC' ) );
	}

	/**
	 * Monthly counts from event rows (each counts 1) or view rows (which carry a count).
	 *
	 * @param iterable<array<string,mixed>> $rows venue_id, event_type, context, city, device, source,
	 *                                            created_at (Y-m-d H:i:s) or day (Y-m-d), optional count
	 * @return array<int,array{month:string,venue_id:int,event_type:string,context:string,city:string,device:string,source:string,count:int}>
	 */
	public static function monthly( iterable $rows ): array {
		$out = [];
		foreach ( $rows as $row ) {
			$date = (string) ( $row['created_at'] ?? $row['day'] ?? '' );
			if ( ! preg_match( '#^(\d{4}-\d{2})-\d{2}#', $date, $m ) ) {
				continue;
			}
			$entry = [
				'month'      => $m[1] . '-01',
				'venue_id'   => (int) ( $row['venue_id'] ?? 0 ),
				'event_type' => (string) ( $row['event_type'] ?? '' ),
				'context'    => (string) ( $row['context'] ?? '' ),
				'city'       => (string) ( $row['city'] ?? '' ),
				'device'     => (string) ( $row['device'] ?? '' ),
				'source'     => (string) ( $row['source'] ?? '' ),
			];
			$key = implode( '|', $entry );
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = $entry + [ 'count' => 0 ];
			}
			$out[ $key ]['count'] += isset( $row['count'] ) ? (int) $row['count'] : 1;
		}
		return array_values( $out );
	}
}
