<?php
/**
 * `wp ppn keywords report`: read keyword research exports (Ahrefs, Semrush,
 * DataForSEO ...), classify every keyword, and write a markdown report of
 * search demand against the pages the site has, to plan which cities, hubs and
 * venues to build next. It only reads the site; nothing is created or changed.
 *
 * Run it from a checkout of the repository, where the exports live in
 * data/keywords/ and the report goes to data/reports/:
 *
 *     wp ppn keywords report --path=/path/to/wordpress
 *     wp ppn keywords report --source=~/exports --out=~/reports
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Cli;

use PlayPoolNation\Core\Helpers\Address;
use PlayPoolNation\Core\Helpers\Keyword_Report;
use PlayPoolNation\Core\Helpers\Keywords;
use PlayPoolNation\Core\Locations;
use PlayPoolNation\Core\Metros;
use PlayPoolNation\Core\Pool_Schema;
use PlayPoolNation\Core\Seo;
use PlayPoolNation\Core\Stats;

defined( 'ABSPATH' ) || exit;

final class Keywords_Command {

	public static function boot(): void {
		\WP_CLI::add_command( 'ppn keywords', __CLASS__ );
	}

	/**
	 * Write the keyword demand report.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<dir>]
	 * : Folder of keyword exports (*.csv). Default: data/keywords in the current directory.
	 *
	 * [--out=<dir>]
	 * : Folder for the report. Default: data/reports in the current directory.
	 *
	 * [--date=<Y-m-d>]
	 * : Date in the report's name and title. Default: today.
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc
	 */
	public function report( array $args, array $assoc ): void {
		$cwd = (string) getcwd();
		$source = rtrim( (string) ( $assoc['source'] ?? $cwd . '/data/keywords' ), '/' );
		$out = rtrim( (string) ( $assoc['out'] ?? $cwd . '/data/reports' ), '/' );
		$date = (string) ( $assoc['date'] ?? wp_date( 'Y-m-d' ) );
		if ( ! preg_match( '#^\d{4}-\d{2}-\d{2}$#', $date ) ) {
			\WP_CLI::error( '--date must be Y-m-d.' );
		}
		$files = glob( $source . '/*.csv' ) ?: [];
		if ( ! $files ) {
			\WP_CLI::error( "No CSV files in {$source}." );
		}
		$rows = [];
		$read = [];
		foreach ( $files as $file ) {
			$export = Keywords::read_export( (string) file_get_contents( $file ) );
			if ( '' !== $export['error'] ) {
				\WP_CLI::warning( basename( $file ) . ': ' . $export['error'] );
				continue;
			}
			foreach ( $export['rows'] as $row ) {
				$rows[] = $row + [ 'source' => basename( $file ) ];
			}
			$read[] = basename( $file );
			\WP_CLI::log( sprintf( '%s: %d keywords', basename( $file ), count( $export['rows'] ) ) );
		}
		if ( ! $rows ) {
			\WP_CLI::error( 'No keywords read.' );
		}

		$site = self::site();
		$index = Keywords::index( require PPN_CORE_DIR . '/includes/data/us-cities.php', Address::STATES, array_map( static fn( $v ) => [ $v[0], $v[2], $v[3] ], $site['venues'] ) );
		$classified = Keyword_Report::classify_all( Keywords::dedupe( $rows ), $index, $site );
		$markdown = Keyword_Report::markdown( $classified, $site, [
			'date'  => $date,
			'files' => $read,
			'rows'  => count( $rows ),
			'home'  => home_url( '/' ),
		] );

		if ( ! is_dir( $out ) && ! wp_mkdir_p( $out ) ) {
			\WP_CLI::error( "Cannot create {$out}." );
		}
		$path = $out . '/keyword-report-' . $date . '.md';
		if ( false === file_put_contents( $path, $markdown ) ) {
			\WP_CLI::error( "Cannot write {$path}." );
		}
		\WP_CLI::success( sprintf( '%d keywords classified; report written to %s', count( $classified ), $path ) );
	}

	/** The site's venues and location and hub pages, in the shape Keyword_Report reads. */
	private static function site(): array {
		$venues = [];
		$ids = get_posts( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_case27_listing_type', 'meta_value' => Pool_Schema::VENUE_TYPE ] );
		foreach ( $ids as $id ) {
			$venues[] = [ html_entity_decode( get_the_title( $id ), ENT_QUOTES ), (string) get_permalink( $id ), (string) get_post_meta( $id, '_ppn_city', true ), (string) get_post_meta( $id, '_ppn_state', true ) ];
		}

		$cities = [];
		$states = [];
		$terms = get_terms( [ 'taxonomy' => Pool_Schema::TAX_REGION, 'hide_empty' => false ] );
		$terms = is_array( $terms ) ? $terms : [];
		$by_id = array_column( array_map( static fn( $t ) => [ $t->term_id, $t ], $terms ), 1, 0 );
		foreach ( $terms as $term ) {
			$count = Locations::venue_count( (int) $term->term_id );
			$url = $count >= Seo::MIN_REGION_VENUES ? (string) get_term_link( $term ) : '';
			if ( 0 === (int) $term->parent ) {
				$abbr = Address::state_abbr( $term->name );
				if ( '' !== $abbr ) {
					$states[ $abbr ] = [ $url, $count ];
				}
				continue;
			}
			$parent = $by_id[ $term->parent ] ?? null;
			$abbr = $parent ? Address::state_abbr( $parent->name ) : '';
			if ( '' === $abbr ) {
				continue;
			}
			$key = Keyword_Report::city_key( $term->name, $abbr );
			if ( ! isset( $cities[ $key ] ) || $count > $cities[ $key ][3] ) {
				$cities[ $key ] = [ $term->name, $abbr, $url, $count ];
			}
		}

		$metros = [];
		foreach ( Metros::with_pages() as $metro ) {
			$names = array_map( [ Keywords::class, 'normalize' ], Metros::cities( $metro ) );
			$keys = [];
			foreach ( $venues as $v ) {
				if ( in_array( Keywords::normalize( $v[2] ), $names, true ) ) {
					$keys[] = Keyword_Report::city_key( $v[2], $v[3] );
				}
			}
			// The metro's own cities, as its location and label name them ("Minneapolis-St. Paul").
			$parts = array_map( 'trim', explode( ',', $metro['location'] ) );
			if ( 2 === count( $parts ) ) {
				foreach ( array_merge( [ $parts[0] ], preg_split( '#\s*[-–/]\s*#u', $metro['label'] ) ) as $name ) {
					$keys[] = Keyword_Report::city_key( $name, $parts[1] );
				}
			}
			$metros[] = [ 'label' => $metro['label'], 'url' => Metros::url( $metro ), 'venues' => Metros::venue_count( $metro ), 'cities' => array_values( array_unique( $keys ) ) ];
		}

		$hubs = [];
		foreach ( Keyword_Report::HUB_PAGES as $topic => $slug ) {
			$page = get_page_by_path( $slug );
			if ( ! $page || 'publish' !== $page->post_status ) {
				continue;
			}
			$indexed = ! in_array( (int) $page->ID, Seo::noindex_page_ids(), true ) && ( 'instructors' !== $slug || ! empty( Stats::stats()['instructors'] ) );
			$hubs[ $topic ] = [ (string) get_permalink( $page ), $indexed ];
		}

		return [
			'venues' => $venues,
			'cities' => $cities,
			'metros' => $metros,
			'states' => $states,
			'hubs'   => $hubs,
			'places' => home_url( user_trailingslashit( Locations::REGION_BASE ) ),
		];
	}
}
