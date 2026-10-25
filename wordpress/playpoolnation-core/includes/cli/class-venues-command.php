<?php
/**
 * `wp ppn venues indexable`: how many venue pages are indexed under
 * Venue::is_indexable(), per city, and what the others still need.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Cli;

use PlayPoolNation\Core\Pool_Schema;
use PlayPoolNation\Core\Venue;

defined( 'ABSPATH' ) || exit;

final class Venues_Command {

	public static function boot(): void {
		\WP_CLI::add_command( 'ppn venues', __CLASS__ );
	}

	/**
	 * Venue pages indexed today, per city.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * [--venues]
	 * : List every venue with what it still needs, instead of the per-city totals.
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc
	 */
	public function indexable( array $args, array $assoc ): void {
		$ids = array_map( 'intval', get_posts( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_key' => '_case27_listing_type', 'meta_value' => Pool_Schema::VENUE_TYPE ] ) );
		Venue::prime( $ids );
		$rows = [];
		$cities = [];
		foreach ( $ids as $id ) {
			$v = Venue::get( $id );
			$city = $v ? ( $v->city_state() ?: '(no city)' ) : '(no city)';
			$gaps = Venue::index_gaps( $id );
			$rows[] = [ 'city' => $city, 'venue' => $v ? $v->name() : (string) $id, 'indexed' => $gaps ? 'no' : 'yes', 'needs' => implode( ', ', $gaps ) ];
			$cities[ $city ] = $cities[ $city ] ?? [ 'city' => $city, 'venues' => 0, 'indexed' => 0, 'needs table facts' => 0, 'needs hours' => 0, 'needs phone or website' => 0 ];
			$cities[ $city ]['venues']++;
			$cities[ $city ]['indexed'] += $gaps ? 0 : 1;
			foreach ( [ 'table facts', 'hours', 'phone or website' ] as $gap ) {
				$cities[ $city ][ 'needs ' . $gap ] += in_array( $gap, $gaps, true ) ? 1 : 0;
			}
		}
		$format = (string) ( $assoc['format'] ?? 'table' );
		if ( isset( $assoc['venues'] ) ) {
			usort( $rows, static fn( $a, $b ) => [ $a['city'], $a['venue'] ] <=> [ $b['city'], $b['venue'] ] );
			\WP_CLI\Utils\format_items( $format, $rows, [ 'city', 'venue', 'indexed', 'needs' ] );
			return;
		}
		ksort( $cities );
		\WP_CLI\Utils\format_items( $format, array_values( $cities ), [ 'city', 'venues', 'indexed', 'needs table facts', 'needs hours', 'needs phone or website' ] );
		$indexed = count( array_filter( $rows, static fn( $r ) => 'yes' === $r['indexed'] ) );
		\WP_CLI::success( sprintf( '%d of %d venue pages are indexable.', $indexed, count( $rows ) ) );
	}
}
