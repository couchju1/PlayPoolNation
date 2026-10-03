<?php
/**
 * Duplicate detection scoring. Pure functions so they can be unit tested.
 *
 * The score never deletes or merges anything by itself: callers use the
 * classification to create, update, or flag a record for human review.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Dedupe {

	public const MATCH    = 'match';     // Same venue: safe to update (subject to provenance rules).
	public const POSSIBLE = 'possible';  // Needs a human to decide.
	public const DISTINCT = 'distinct';  // Different venue.

	/** Words that carry no identity in venue names. */
	private const NOISE = [ 'the', 'and', 'a', 'of', 'bar', 'grill', 'lounge', 'club', 'pub', 'inc', 'llc', 'co' ];

	public static function normalize_name( string $name ): string {
		$name = strtolower( html_entity_decode( $name, ENT_QUOTES ) );
		$name = str_replace( [ '&', '+' ], ' and ', $name );
		$name = preg_replace( "/['\x{2019}`]/u", '', $name );
		$name = preg_replace( '/[^a-z0-9]+/', ' ', $name );
		$words = array_filter( explode( ' ', $name ), static fn( $w ) => '' !== $w && ! in_array( $w, self::NOISE, true ) );
		return implode( ' ', $words );
	}

	/** Last 10 digits of a US phone number, or '' when there are not enough digits. */
	public static function normalize_phone( string $phone ): string {
		$digits = preg_replace( '/\D+/', '', $phone );
		return strlen( $digits ) >= 10 ? substr( $digits, -10 ) : '';
	}

	/** Registrable-ish host without "www."; social/profile hosts are ignored because they are not unique. */
	public static function domain( string $url ): string {
		if ( '' === trim( $url ) ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'http://' . $url;
		}
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );
		$shared = [ 'facebook.com', 'instagram.com', 'linktr.ee', 'yelp.com', 'google.com', 'wixsite.com', 'squarespace.com', 'business.site' ];
		foreach ( $shared as $s ) {
			if ( $host === $s || str_ends_with( $host, '.' . $s ) ) {
				return '';
			}
		}
		return $host;
	}

	/** Great-circle distance in meters. */
	public static function distance_m( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$r = 6371000.0;
		$dlat = deg2rad( $lat2 - $lat1 );
		$dlng = deg2rad( $lng2 - $lng1 );
		$a = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) ** 2;
		return 2 * $r * asin( min( 1.0, sqrt( $a ) ) );
	}

	/** 0..1 similarity of two normalized names. */
	public static function name_similarity( string $a, string $b ): float {
		$a = self::normalize_name( $a );
		$b = self::normalize_name( $b );
		if ( '' === $a || '' === $b ) {
			return 0.0;
		}
		if ( $a === $b ) {
			return 1.0;
		}
		similar_text( $a, $b, $pct );
		$contains = ( str_contains( $a, $b ) || str_contains( $b, $a ) ) ? 0.15 : 0.0;
		return min( 1.0, $pct / 100 + $contains );
	}

	/**
	 * Compare two venue records. Keys used (all optional): name, phone, website, lat, lng,
	 * external_ids (provider => id).
	 *
	 * @return array{score:int,class:string,reasons:string[]}
	 */
	public static function compare( array $a, array $b ): array {
		$reasons = [];

		foreach ( (array) ( $a['external_ids'] ?? [] ) as $provider => $id ) {
			if ( '' !== (string) $id && ( $b['external_ids'][ $provider ] ?? null ) === $id ) {
				return [ 'score' => 100, 'class' => self::MATCH, 'reasons' => [ "same {$provider} id" ] ];
			}
		}

		$score = 0.0;
		$sim = self::name_similarity( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
		$score += $sim * 40;
		if ( $sim >= 0.85 ) {
			$reasons[] = 'similar name';
		}

		$pa = self::normalize_phone( (string) ( $a['phone'] ?? '' ) );
		if ( '' !== $pa && $pa === self::normalize_phone( (string) ( $b['phone'] ?? '' ) ) ) {
			$score += 35;
			$reasons[] = 'same phone';
		}

		$da = self::domain( (string) ( $a['website'] ?? '' ) );
		if ( '' !== $da && $da === self::domain( (string) ( $b['website'] ?? '' ) ) ) {
			// Chains share a domain across locations, so a domain match alone is weak evidence.
			$score += 10;
			$reasons[] = 'same website';
		}

		$has_geo = isset( $a['lat'], $a['lng'], $b['lat'], $b['lng'] ) && '' !== $a['lat'] && '' !== $b['lat'];
		if ( $has_geo ) {
			$d = self::distance_m( (float) $a['lat'], (float) $a['lng'], (float) $b['lat'], (float) $b['lng'] );
			if ( $d <= 60 ) {
				$score += 30;
				$reasons[] = 'within 60 m';
			} elseif ( $d <= 250 ) {
				$score += 15;
				$reasons[] = 'within 250 m';
			} elseif ( $d > 5000 ) {
				$score -= 60; // Far apart: same name is probably a chain or coincidence.
				$reasons[] = 'more than 5 km apart';
			}
		}

		$score = (int) max( 0, min( 100, round( $score ) ) );
		$class = $score >= 75 ? self::MATCH : ( $score >= 45 ? self::POSSIBLE : self::DISTINCT );
		return [ 'score' => $score, 'class' => $class, 'reasons' => $reasons ];
	}
}
