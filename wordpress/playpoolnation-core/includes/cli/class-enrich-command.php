<?php
/**
 * `wp ppn enrich`: hand data between tools/enrich and the review queue.
 *
 *     wp ppn enrich export sioux-falls > listings-export.json   # our listings, for the tool
 *     wp ppn enrich import sioux-falls                          # load tools/enrich/out/sioux-falls/ for review
 *     wp ppn enrich status
 *
 * Run from a checkout of the repository (or pass --dir). Importing changes
 * nothing on the public site: items wait under PlayPoolNation → Review.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Cli;

use PlayPoolNation\Core\Enrich_Queue;
use PlayPoolNation\Core\Pool_Schema;
use PlayPoolNation\Core\Provenance;
use PlayPoolNation\Core\Venue;

defined( 'ABSPATH' ) || exit;

final class Enrich_Command {

	public static function boot(): void {
		\WP_CLI::add_command( 'ppn enrich', __CLASS__ );
	}

	/**
	 * Load a city's enrichment output into the review queue.
	 *
	 * ## OPTIONS
	 *
	 * <city>
	 * : City slug, as in tools/enrich/out/<city>/.
	 *
	 * [--dir=<dir>]
	 * : The city's output folder. Default: tools/enrich/out/<city> in the current directory.
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc
	 */
	public function import( array $args, array $assoc ): void {
		$city = sanitize_title( $args[0] );
		$dir = rtrim( (string) ( $assoc['dir'] ?? getcwd() . '/tools/enrich/out/' . $city ), '/' );
		if ( ! is_dir( $dir ) ) {
			\WP_CLI::error( "No folder {$dir}. Run tools/enrich/enrich.py run {$city} first, or pass --dir." );
		}
		$sum = Enrich_Queue::import_dir( $dir, $city );
		foreach ( $sum['unresolved'] as $slug ) {
			\WP_CLI::warning( "{$slug}: no matching listing on this site; its facts were skipped." );
		}
		if ( $sum['invalid'] ) {
			\WP_CLI::warning( sprintf( '%d facts were not in a form we can use and were skipped.', $sum['invalid'] ) );
		}
		\WP_CLI::success( sprintf(
			'%s: %d new, %d updated, %d unchanged, %d reopened after a change (%d new venues). Review at %s',
			$city,
			$sum['inserted'],
			$sum['updated'],
			$sum['unchanged'],
			$sum['reopened'],
			$sum['new_venues'],
			Enrich_Queue::page_url( [ 'city' => $city ] )
		) );
	}

	/**
	 * Print a city's listings as JSON for tools/enrich (--listings).
	 *
	 * ## OPTIONS
	 *
	 * <city>
	 * : City region slug, e.g. sioux-falls.
	 *
	 * @param array<int,string> $args
	 */
	public function export( array $args ): void {
		$terms = get_terms( [ 'taxonomy' => Pool_Schema::TAX_REGION, 'slug' => sanitize_title( $args[0] ), 'hide_empty' => false ] );
		$term = is_array( $terms ) ? ( array_values( array_filter( $terms, static fn( $t ) => 0 !== (int) $t->parent ) )[0] ?? null ) : null;
		if ( ! $term ) {
			\WP_CLI::error( 'No city region with slug ' . $args[0] . '.' );
		}
		$ids = array_map( 'intval', get_posts( [
			'post_type'      => Pool_Schema::POST_TYPE,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_case27_listing_type',
			'meta_value'     => Pool_Schema::VENUE_TYPE,
			'tax_query'      => [ [ 'taxonomy' => Pool_Schema::TAX_REGION, 'terms' => (int) $term->term_id ] ],
		] ) );
		Venue::prime( $ids );
		$out = [];
		foreach ( $ids as $id ) {
			$v = Venue::get( $id );
			$a = $v->address_parts();
			$loc = $v->location();
			$links = get_post_meta( $id, '_links', true );
			$out[] = [
				'id'      => $id,
				'slug'    => $v->post->post_name,
				'url'     => $v->url(),
				'name'    => $v->name(),
				'type'    => $v->term_slugs( Pool_Schema::TAX_VENUE_TYPE )[0] ?? '',
				'street'  => $a['street'],
				'city'    => $a['city'],
				'state'   => $a['state'],
				'zip'     => $a['postal_code'],
				'phone'   => $v->phone(),
				'website' => $v->website(),
				'links'   => is_array( $links ) ? array_values( array_filter( array_map( static fn( $l ) => is_array( $l ) ? (string) ( $l['url'] ?? '' ) : '', $links ) ) ) : [],
				'lat'     => $loc ? (float) $loc->lat : null,
				'lng'     => $loc ? (float) $loc->lng : null,
				'current' => [ 'tables' => $v->total_tables(), 'sources' => array_keys( Provenance::field_sources( $id ) ) ],
			];
		}
		\WP_CLI::line( (string) wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Items in the review queue per city and status.
	 *
	 * [--format=<format>]
	 * : table, csv or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc
	 */
	public function status( array $args, array $assoc ): void {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT city, kind, status, COUNT(*) AS items, COUNT(DISTINCT venue_slug) AS venues FROM ' . Enrich_Queue::table() . ' GROUP BY city, kind, status ORDER BY city, kind, status', ARRAY_A );
		\WP_CLI\Utils\format_items( (string) ( $assoc['format'] ?? 'table' ), $rows ?: [], [ 'city', 'kind', 'status', 'items', 'venues' ] );
	}
}
