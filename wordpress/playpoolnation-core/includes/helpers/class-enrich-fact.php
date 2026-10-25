<?php
/**
 * Facts from tools/enrich: checking what the JSON says, showing it in the review
 * screen, reading an edited value back, and the writes an approved fact makes.
 * Pure functions so they can be unit tested; Pool_Data::apply_fact() runs the writes.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Enrich_Fact {

	public const FIELDS = [
		'table_count'   => 'Table count',
		'table_sizes'   => 'Table size',
		'table_brands'  => 'Table brand',
		'pricing_model' => 'Pricing',
		'league_nights' => 'League night',
		'age_policy'    => 'Age policy',
		'food'          => 'Food',
		'full_bar'      => 'Full bar',
		'venue_status'  => 'Closed',
	];

	public const SIZES = [
		'7ft'     => [ 'tables-7ft', '7-foot' ],
		'8ft'     => [ 'tables-8ft', '8-foot' ],
		'9ft'     => [ 'tables-9ft', '9-foot' ],
		'10ft'    => [ 'tables-other', null ],
		'12ft'    => [ 'tables-other', null ],
		'snooker' => [ 'tables-snooker', 'snooker' ],
	];

	/** Brands with their own term; anything else is "Other brand" plus the name in Table models. */
	public const BRAND_TERMS = [ 'diamond', 'valley', 'brunswick', 'rasson', 'predator', 'olhausen', 'gandy' ];

	public const PRICING = [ 'coin-op' => 'Coin-op', 'hourly' => 'Hourly', 'per-game' => 'Per game', 'free-pool' => 'Free pool' ];

	public const LEAGUE_ORGS = [ 'APA' => 'apa', 'BCA' => 'bca', 'USAPL' => 'usapl', 'TAP' => 'tap', 'VNEA' => 'vnea', 'ACS' => 'acs' ];

	public const AGES = [ '21-plus' => '21+', '18-plus' => '18+', 'all-ages' => 'All ages' ];

	public const DAYS = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];

	public const STATUSES = [ 'permanently-closed' => 'Permanently closed', 'temporarily-closed' => 'Temporarily closed' ];

	/**
	 * A fact from a venue file, cleaned, or null when it is not one we can use.
	 *
	 * @return array{field:string,key:string,value:mixed,source_url:string,source_type:string,quote:string,checked_at:string,confidence:string,flags:string[],conflicts:array}|null
	 */
	public static function normalize( array $fact ): ?array {
		$field = (string) ( $fact['field'] ?? '' );
		if ( ! isset( self::FIELDS[ $field ] ) ) {
			return null;
		}
		$value = self::clean_value( $field, $fact['value'] ?? null );
		$url = (string) ( $fact['source_url'] ?? '' );
		if ( null === $value || ! preg_match( '#^https?://[^\s]+$#i', $url ) ) {
			return null;
		}
		$confidence = in_array( $fact['confidence'] ?? '', [ 'stated', 'implied' ], true ) ? $fact['confidence'] : 'implied';
		$date = (string) ( $fact['checked_at'] ?? '' );
		return [
			'field'       => $field,
			'key'         => substr( preg_replace( '/[^a-z0-9_:\-]/', '', strtolower( (string) ( $fact['key'] ?? $field ) ) ), 0, 120 ),
			'value'       => $value,
			'source_url'  => $url,
			'source_type' => in_array( $fact['source_type'] ?? '', [ 'official_website', 'facebook', 'league_operator' ], true ) ? $fact['source_type'] : 'web_research',
			'quote'       => self::short_quote( (string) ( $fact['quote_snippet'] ?? '' ) ),
			'checked_at'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : gmdate( 'Y-m-d' ),
			'confidence'  => $confidence,
			'flags'       => array_values( array_map( 'strval', (array) ( $fact['flags'] ?? [] ) ) ),
			'conflicts'   => array_values( (array) ( $fact['conflicts'] ?? [] ) ),
		];
	}

	/** Under 20 words, for the reviewer only. */
	public static function short_quote( string $quote ): string {
		$words = preg_split( '/\s+/', trim( $quote ) ) ?: [];
		$words = array_values( array_filter( $words, static fn( $w ) => '' !== $w ) );
		$real = 0;
		$out = [];
		foreach ( $words as $w ) {
			if ( '…' !== $w ) {
				if ( ++$real > 19 ) {
					$out[] = '…';
					break;
				}
			}
			$out[] = $w;
		}
		return mb_substr( implode( ' ', $out ), 0, 290 );
	}

	/** @return mixed|null */
	public static function clean_value( string $field, $value ) {
		switch ( $field ) {
			case 'table_count':
				return is_numeric( $value ) && (int) $value > 0 && (int) $value <= 200 ? (int) $value : null;
			case 'table_sizes':
				if ( ! is_array( $value ) || ! isset( self::SIZES[ $value['size'] ?? '' ] ) ) {
					return null;
				}
				$count = $value['count'] ?? null;
				return [ 'size' => $value['size'], 'count' => is_numeric( $count ) && (int) $count > 0 ? (int) $count : null ];
			case 'table_brands':
				$brand = trim( wp_strip_all_tags_safe( (string) $value ) );
				return '' !== $brand && mb_strlen( $brand ) <= 40 ? $brand : null;
			case 'pricing_model':
				$model = is_array( $value ) ? (string) ( $value['model'] ?? '' ) : '';
				if ( ! isset( self::PRICING[ $model ] ) ) {
					return null;
				}
				$out = [ 'model' => $model ];
				if ( in_array( $model, [ 'hourly', 'per-game' ], true ) ) {
					$out['rate'] = isset( $value['rate'] ) && is_numeric( $value['rate'] ) && $value['rate'] > 0 ? round( (float) $value['rate'], 2 ) : null;
				}
				if ( 'free-pool' === $model ) {
					$out['days'] = array_values( array_intersect( self::DAYS, (array) ( $value['days'] ?? [] ) ) );
				}
				return $out;
			case 'league_nights':
				$league = is_array( $value ) ? trim( (string) ( $value['league'] ?? '' ) ) : '';
				$day = is_array( $value ) ? ( $value['weekday'] ?? null ) : null;
				if ( '' === $league || mb_strlen( $league ) > 60 ) {
					return null;
				}
				return [ 'league' => $league, 'weekday' => in_array( $day, self::DAYS, true ) ? $day : null ];
			case 'age_policy':
				return isset( self::AGES[ (string) $value ] ) ? (string) $value : null;
			case 'food':
			case 'full_bar':
				return is_bool( $value ) ? $value : null;
			case 'venue_status':
				return isset( self::STATUSES[ (string) $value ] ) ? (string) $value : null;
		}
		return null;
	}

	/** What the review screen shows for a value. */
	public static function label( string $field, $value ): string {
		switch ( $field ) {
			case 'table_count':
				return sprintf( '%d tables', (int) $value );
			case 'table_sizes':
				$size = 'snooker' === $value['size'] ? 'Snooker' : str_replace( 'ft', ' ft', $value['size'] );
				return $size . ( $value['count'] ? sprintf( ': %d tables', $value['count'] ) : ' (count not stated)' );
			case 'table_brands':
				return (string) $value;
			case 'pricing_model':
				$label = self::PRICING[ $value['model'] ];
				if ( ! empty( $value['rate'] ) ) {
					$rate = (float) $value['rate'];
					$label .= sprintf( ', $%s %s', floor( $rate ) === $rate ? (string) (int) $rate : number_format( $rate, 2, '.', '' ), 'hourly' === $value['model'] ? 'an hour' : 'a game' );
				}
				if ( 'free-pool' === $value['model'] ) {
					$label .= $value['days'] ? ': ' . implode( ', ', array_map( 'ucfirst', $value['days'] ) ) : ' (day not stated)';
				}
				return $label;
			case 'league_nights':
				return $value['league'] . ( $value['weekday'] ? ' on ' . ucfirst( $value['weekday'] ) : ' (night not stated)' );
			case 'age_policy':
				return self::AGES[ $value ];
			case 'food':
			case 'full_bar':
				return $value ? 'Yes' : 'No';
			case 'venue_status':
				return self::STATUSES[ $value ];
		}
		return '';
	}

	/** The value as one line of text for the Edit box; from_text() reads it back. */
	public static function to_text( string $field, $value ): string {
		switch ( $field ) {
			case 'table_sizes':
				return $value['size'] . ( $value['count'] ? ' ' . $value['count'] : '' );
			case 'pricing_model':
				$parts = [ $value['model'] ];
				if ( ! empty( $value['rate'] ) ) {
					$parts[] = (string) $value['rate'];
				}
				if ( ! empty( $value['days'] ) ) {
					$parts[] = implode( ',', $value['days'] );
				}
				return implode( ' ', $parts );
			case 'league_nights':
				return $value['league'] . ( $value['weekday'] ? ' / ' . $value['weekday'] : '' );
			case 'food':
			case 'full_bar':
				return $value ? 'yes' : 'no';
		}
		return (string) $value;
	}

	/** @return mixed|null null when the text cannot be read as this field */
	public static function from_text( string $field, string $text ) {
		$text = trim( $text );
		$lower = strtolower( $text );
		switch ( $field ) {
			case 'table_count':
				return preg_match( '/^\d+$/', $text ) ? self::clean_value( $field, (int) $text ) : null;
			case 'table_sizes':
				if ( ! preg_match( '/^(7|8|9|10|12)\s*(?:ft|foot|\')?\b\s*(\d+)?$|^(snooker)\s*(\d+)?$/i', $lower, $m ) ) {
					return null;
				}
				$size = ! empty( $m[3] ) ? 'snooker' : $m[1] . 'ft';
				$count = ! empty( $m[3] ) ? ( $m[4] ?? '' ) : ( $m[2] ?? '' );
				return self::clean_value( $field, [ 'size' => $size, 'count' => '' === $count ? null : (int) $count ] );
			case 'table_brands':
				return self::clean_value( $field, $text );
			case 'pricing_model':
				$parts = preg_split( '/\s+/', $lower ) ?: [];
				$first = (string) array_shift( $parts );
				$model = [ 'coin' => 'coin-op', 'coin-op' => 'coin-op', 'hourly' => 'hourly', 'hour' => 'hourly', 'per-game' => 'per-game', 'game' => 'per-game', 'free' => 'free-pool', 'free-pool' => 'free-pool' ][ $first ] ?? $first;
				$rate = null;
				$days = [];
				foreach ( $parts as $p ) {
					if ( is_numeric( ltrim( $p, '$' ) ) ) {
						$rate = (float) ltrim( $p, '$' );
					} else {
						foreach ( preg_split( '/[,\s]+/', $p ) ?: [] as $d ) {
							$day = self::day( $d );
							if ( $day ) {
								$days[] = $day;
							}
						}
					}
				}
				return self::clean_value( $field, [ 'model' => $model, 'rate' => $rate, 'days' => $days ] );
			case 'league_nights':
				$parts = array_map( 'trim', explode( '/', $text, 2 ) );
				$day = isset( $parts[1] ) ? self::day( $parts[1] ) : null;
				if ( isset( $parts[1] ) && '' !== $parts[1] && ! $day ) {
					return null;
				}
				return self::clean_value( $field, [ 'league' => $parts[0], 'weekday' => $day ] );
			case 'age_policy':
				$map = [ '21' => '21-plus', '21+' => '21-plus', '18' => '18-plus', '18+' => '18-plus', 'all' => 'all-ages', 'all ages' => 'all-ages' ];
				return self::clean_value( $field, $map[ $lower ] ?? $lower );
			case 'food':
			case 'full_bar':
				return in_array( $lower, [ 'yes', 'y', 'true', '1' ], true ) ? true : ( in_array( $lower, [ 'no', 'n', 'false', '0' ], true ) ? false : null );
			case 'venue_status':
				return self::clean_value( $field, str_replace( ' ', '-', $lower ) );
		}
		return null;
	}

	private static function day( string $text ): ?string {
		$text = strtolower( trim( $text ) );
		if ( strlen( $text ) < 3 ) {
			return null;
		}
		foreach ( self::DAYS as $day ) {
			if ( str_starts_with( $day, rtrim( $text, 's' ) ) || $day . 's' === $text ) {
				return $day;
			}
		}
		return null;
	}

	/**
	 * Writes an approved fact makes. Each op is one of:
	 *  [ 'count', field, int ]                 My Listing number field (`_<field>`)
	 *  [ 'term', taxonomy, slug ]              Yes for that term
	 *  [ 'meta', field, string ]               Plain field value
	 *  [ 'append', field, string ]             Add a line to a text field unless it is there
	 *  [ 'league', org_slug, league_name, day ] League listing at the venue
	 *  [ 'status', venue_status ]
	 *
	 * Each op also names the provenance key it records (last element).
	 *
	 * @return array<int,array>
	 */
	public static function plan( string $field, $value ): array {
		switch ( $field ) {
			case 'table_count':
				return [ [ 'count', 'number-of-tables', (int) $value, 'number-of-tables' ] ];
			case 'table_sizes':
				[ $count_field, $term ] = self::SIZES[ $value['size'] ];
				$ops = [];
				if ( $value['count'] && null !== $term ) {
					$ops[] = [ 'count', $count_field, (int) $value['count'], $count_field ];
				}
				if ( null !== $term ) {
					$ops[] = [ 'term', 'table-size', $term, 'table-size:' . $term ];
				} else {
					$ops[] = [ 'append', 'equipment-notes', str_replace( 'ft', '-foot', $value['size'] ) . ' tables' . ( $value['count'] ? ': ' . $value['count'] : '' ), 'equipment-notes' ];
				}
				return $ops;
			case 'table_brands':
				$slug = sanitize_title_safe( (string) $value );
				if ( in_array( $slug, self::BRAND_TERMS, true ) ) {
					return [ [ 'term', 'table-brand', $slug, 'table-brand:' . $slug ] ];
				}
				return [ [ 'term', 'table-brand', 'other-brand', 'table-brand:other-brand' ], [ 'append', 'table-models', (string) $value, 'table-models' ] ];
			case 'pricing_model':
				$ops = [ [ 'term', 'pool-pricing', $value['model'], 'pool-pricing:' . $value['model'] ] ];
				if ( 'hourly' === $value['model'] && ! empty( $value['rate'] ) ) {
					$ops[] = [ 'meta', 'hourly-rate', (string) $value['rate'], 'hourly-rate' ];
				}
				if ( 'per-game' === $value['model'] && ! empty( $value['rate'] ) ) {
					$ops[] = [ 'meta', 'game-price', (string) $value['rate'], 'game-price' ];
				}
				if ( 'free-pool' === $value['model'] && $value['days'] ) {
					$ops[] = [ 'append', 'pricing-notes', 'Free pool ' . implode( ', ', array_map( static fn( $d ) => ucfirst( $d ) . 's', $value['days'] ) ) . '.', 'pricing-notes' ];
				}
				return $ops;
			case 'league_nights':
				$org = self::LEAGUE_ORGS[ strtoupper( $value['league'] ) ] ?? 'local-league';
				return [ [ 'league', $org, $value['league'], $value['weekday'], 'league:' . $org . ':' . ( $value['weekday'] ?? '' ) ] ];
			case 'age_policy':
				return [ [ 'term', 'case27_job_listing_tags', $value, 'case27_job_listing_tags:' . $value ] ];
			case 'food':
			case 'full_bar':
				$slug = 'food' === $field ? 'food' : 'full-bar';
				return [ [ $value ? 'term' : 'no', 'case27_job_listing_tags', $slug, 'case27_job_listing_tags:' . $slug ] ];
			case 'venue_status':
				return [ [ 'status', $value, 'venue-status' ] ];
		}
		return [];
	}
}

/** Strip tags without WordPress (unit tests) or with it. */
function wp_strip_all_tags_safe( string $s ): string {
	return function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $s ) : trim( strip_tags( $s ) );
}

function sanitize_title_safe( string $s ): string {
	return function_exists( 'sanitize_title' ) ? sanitize_title( $s ) : trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $s ) ), '-' );
}
