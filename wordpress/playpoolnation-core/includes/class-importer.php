<?php
/**
 * Controlled venue import (CSV) with duplicate detection and provenance.
 *
 * Rules:
 *  - A row matching an existing venue by provider ID, or with a confident duplicate
 *    score, updates that venue, but only fields that are empty or whose current
 *    source ranks no higher than the import source (see Provenance::RANKS).
 *  - Uncertain matches are created as pending and flagged for human review.
 *  - Nothing is ever deleted. Every row is logged in {prefix}ppn_import_log.
 *
 * CSV columns (header row required, unknown columns ignored):
 *   name, street, city, state, zip, lat, lng, phone, website, provider, external_id,
 *   venue_type, tables_total, tables_7ft, tables_8ft, tables_9ft, tables_snooker,
 *   tables_carom, table_brands, pricing, amenities, source_url
 * List columns use ";" between values (e.g. "Diamond;Valley").
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Address;
use PlayPoolNation\Core\Helpers\Dedupe;

defined( 'ABSPATH' ) || exit;

final class Importer {

	public const LOG_TABLE = 'ppn_import_log';

	private const COUNT_COLUMNS = [
		'tables_total'   => 'number-of-tables',
		'tables_7ft'     => 'tables-7ft',
		'tables_8ft'     => 'tables-8ft',
		'tables_9ft'     => 'tables-9ft',
		'tables_snooker' => 'tables-snooker',
		'tables_carom'   => 'tables-carom',
	];

	private const LIST_COLUMNS = [
		'table_brands' => 'table-brand',
		'pricing'      => 'pool-pricing',
		'amenities'    => Pool_Schema::TAX_AMENITY,
	];

	public static function boot(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'ppn import', [ __CLASS__, 'cli' ] );
		}
	}

	public static function log_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::LOG_TABLE;
	}

	/* --------------------------------------------------------- parsing */

	/** @return array<int,array<string,string>> */
	public static function parse_csv( string $path ): array {
		$rows = [];
		$fh = fopen( $path, 'r' );
		if ( ! $fh ) {
			return [];
		}
		$header = fgetcsv( $fh, 0, ',', '"', '\\' );
		if ( ! $header ) {
			fclose( $fh );
			return [];
		}
		$header = array_map( static fn( $h ) => sanitize_key( str_replace( ' ', '_', strtolower( trim( (string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF" ) ) ) ), $header );
		while ( ( $line = fgetcsv( $fh, 0, ',', '"', '\\' ) ) !== false ) {
			if ( [ null ] === $line ) {
				continue;
			}
			$row = [];
			foreach ( $header as $i => $key ) {
				$row[ $key ] = trim( (string) ( $line[ $i ] ?? '' ) );
			}
			$rows[] = $row;
		}
		fclose( $fh );
		return $rows;
	}

	/* ------------------------------------------------------- matching */

	/**
	 * Nearby or same-named venues to compare against.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function candidates( ?float $lat, ?float $lng, string $name ): array {
		global $wpdb;
		$ids = [];
		if ( null !== $lat && null !== $lng ) {
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT l.listing_id FROM {$wpdb->prefix}mylisting_locations l JOIN {$wpdb->posts} p ON p.ID = l.listing_id
				 WHERE p.post_type = %s AND p.post_status IN ('publish','pending','draft') AND l.lat BETWEEN %f AND %f AND l.lng BETWEEN %f AND %f LIMIT 50",
				Pool_Schema::POST_TYPE,
				$lat - 0.02,
				$lat + 0.02,
				$lng - 0.025,
				$lng + 0.025
			) );
		}
		$norm = Dedupe::normalize_name( $name );
		if ( '' !== $norm ) {
			$first = explode( ' ', $norm )[0];
			$ids = array_merge( $ids, $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','pending','draft') AND post_title LIKE %s LIMIT 50",
				Pool_Schema::POST_TYPE,
				'%' . $wpdb->esc_like( $first ) . '%'
			) ) );
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		Venue::prime( $ids );
		$out = [];
		foreach ( $ids as $id ) {
			if ( ! Venue::is_venue( $id ) ) {
				continue;
			}
			$v = Venue::get( $id );
			$loc = $v ? $v->location() : null;
			$out[] = [
				'id'           => $id,
				'name'         => $v ? $v->name() : '',
				'phone'        => $v ? $v->phone() : '',
				'website'      => $v ? $v->website() : '',
				'lat'          => $loc ? (float) $loc->lat : '',
				'lng'          => $loc ? (float) $loc->lng : '',
				'external_ids' => Provenance::external_ids( $id ),
			];
		}
		return $out;
	}

	/** @return array{class:string,id:int,score:int,reasons:string[]} */
	public static function match( array $row ): array {
		$provider = sanitize_key( $row['provider'] ?? '' );
		if ( $provider && ! empty( $row['external_id'] ) ) {
			$id = Provenance::find_by_external_id( $provider, $row['external_id'] );
			if ( $id ) {
				return [ 'class' => Dedupe::MATCH, 'id' => $id, 'score' => 100, 'reasons' => [ "same {$provider} id" ] ];
			}
		}
		$lat = is_numeric( $row['lat'] ?? '' ) ? (float) $row['lat'] : null;
		$lng = is_numeric( $row['lng'] ?? '' ) ? (float) $row['lng'] : null;
		$best = [ 'class' => Dedupe::DISTINCT, 'id' => 0, 'score' => 0, 'reasons' => [] ];
		$incoming = [
			'name'         => $row['name'] ?? '',
			'phone'        => $row['phone'] ?? '',
			'website'      => $row['website'] ?? '',
			'lat'          => $lat ?? '',
			'lng'          => $lng ?? '',
			'external_ids' => $provider ? [ $provider => $row['external_id'] ?? '' ] : [],
		];
		foreach ( self::candidates( $lat, $lng, (string) ( $row['name'] ?? '' ) ) as $candidate ) {
			$cmp = Dedupe::compare( $incoming, $candidate );
			if ( $cmp['score'] > $best['score'] ) {
				$best = [ 'class' => $cmp['class'], 'id' => (int) $candidate['id'], 'score' => $cmp['score'], 'reasons' => $cmp['reasons'] ];
			}
		}
		return $best;
	}

	/* --------------------------------------------------------- writing */

	/**
	 * @param array<int,array<string,string>> $rows
	 * @return array{created:int,updated:int,flagged:int,skipped:int,run:string,results:array}
	 */
	public static function import( array $rows, string $source, bool $publish = false, bool $dry_run = false ): array {
		$source = sanitize_text_field( $source ) ?: 'import';
		$run = gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 4, false );
		$summary = [ 'created' => 0, 'updated' => 0, 'flagged' => 0, 'skipped' => 0, 'run' => $run, 'results' => [] ];

		foreach ( $rows as $i => $row ) {
			$line = $i + 2; // Header is line 1.
			$name = sanitize_text_field( $row['name'] ?? '' );
			$state = strtoupper( sanitize_text_field( $row['state'] ?? '' ) );
			if ( '' === $name || ( '' === ( $row['street'] ?? '' ) && ! is_numeric( $row['lat'] ?? '' ) ) ) {
				self::log( $run, $line, 'skipped', 0, 'Missing name or location' );
				++$summary['skipped'];
				$summary['results'][] = [ $line, $name, 'skipped', 'Missing name or location' ];
				continue;
			}
			if ( '' !== $state && ! isset( Address::STATES[ $state ] ) ) {
				$state = Address::state_abbr( $state );
			}
			$row['state'] = $state;
			$m = self::match( $row );

			if ( Dedupe::MATCH === $m['class'] ) {
				$changed = $dry_run ? [] : self::update_venue( $m['id'], $row, $source );
				$msg = 'Matched #' . $m['id'] . ' (' . implode( ', ', $m['reasons'] ) . '); ' . ( $dry_run ? 'dry run' : ( $changed ? 'updated: ' . implode( ', ', $changed ) : 'no changes' ) );
				self::log( $run, $line, 'updated', $m['id'], $msg, $dry_run );
				++$summary['updated'];
				$summary['results'][] = [ $line, $name, 'updated', $msg ];
				continue;
			}

			$flag = Dedupe::POSSIBLE === $m['class'];
			$id = $dry_run ? 0 : self::create_venue( $row, $source, $publish && ! $flag );
			if ( $id && $flag ) {
				update_post_meta( $id, '_ppn_possible_duplicate_of', $m['id'] );
			}
			$msg = $flag ? 'Possible duplicate of #' . $m['id'] . ' (score ' . $m['score'] . '); created as pending for review' : 'Created' . ( $publish ? '' : ' as pending' );
			self::log( $run, $line, $flag ? 'flagged' : 'created', $id, $dry_run ? 'Dry run: ' . $msg : $msg, $dry_run );
			$flag ? ++$summary['flagged'] : ++$summary['created'];
			$summary['results'][] = [ $line, $name, $flag ? 'flagged' : 'created', $msg ];
		}
		Stats::flush();
		return $summary;
	}

	private static function list_values( string $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ';', $value ) ), 'strlen' ) );
	}

	private static function term_ids( string $taxonomy, array $names ): array {
		$ids = [];
		foreach ( $names as $name ) {
			$term = get_term_by( 'slug', sanitize_title( $name ), $taxonomy ) ?: get_term_by( 'name', $name, $taxonomy );
			if ( $term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return $ids;
	}

	public static function create_venue( array $row, string $source, bool $publish ): int {
		$street = sanitize_text_field( $row['street'] ?? '' );
		$address = trim( implode( ', ', array_filter( [ $street, sanitize_text_field( $row['city'] ?? '' ), trim( ( $row['state'] ?? '' ) . ' ' . preg_replace( '/\D/', '', $row['zip'] ?? '' ) ) ] ) ) );
		$id = wp_insert_post( [
			'post_type'   => Pool_Schema::POST_TYPE,
			'post_status' => $publish ? 'publish' : 'pending',
			'post_title'  => sanitize_text_field( $row['name'] ),
			'post_author' => get_current_user_id() ?: 1,
		], true );
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_case27_listing_type', Pool_Schema::VENUE_TYPE );
		update_post_meta( $id, '_job_location', $address );
		if ( is_numeric( $row['lat'] ?? '' ) && is_numeric( $row['lng'] ?? '' ) ) {
			global $wpdb;
			$wpdb->insert( $wpdb->prefix . 'mylisting_locations', [ 'listing_id' => $id, 'address' => $address, 'lat' => round( (float) $row['lat'], 5 ), 'lng' => round( (float) $row['lng'], 5 ) ] );
		}
		$type = sanitize_title( $row['venue_type'] ?? '' );
		if ( $type && term_exists( $type, Pool_Schema::TAX_VENUE_TYPE ) ) {
			wp_set_object_terms( $id, $type, Pool_Schema::TAX_VENUE_TYPE );
		}
		Provenance::set_origin( $id, $source, (string) ( $row['source_url'] ?? '' ) );
		Provenance::touch_checked( $id );
		self::update_venue( $id, $row, $source );
		Locations::sync( $id );
		return (int) $id;
	}

	/**
	 * Apply row values that are allowed by provenance rules.
	 *
	 * @return string[] changed field names
	 */
	public static function update_venue( int $id, array $row, string $source ): array {
		$changed = [];
		$url = (string) ( $row['source_url'] ?? '' );
		$provider = sanitize_key( $row['provider'] ?? '' );
		if ( $provider && ! empty( $row['external_id'] ) ) {
			Provenance::set_external_id( $id, $provider, (string) $row['external_id'] );
		}
		$scalars = [ 'phone' => '_job_phone', 'website' => '_job_website' ];
		foreach ( $scalars as $col => $meta ) {
			$value = 'website' === $col ? esc_url_raw( $row[ $col ] ?? '' ) : sanitize_text_field( $row[ $col ] ?? '' );
			if ( '' === $value ) {
				continue;
			}
			$current = (string) get_post_meta( $id, $meta, true );
			if ( $current !== $value && Provenance::can_overwrite( $id, $col, $source, '' === $current ) ) {
				update_post_meta( $id, $meta, $value );
				Provenance::record_field( $id, $col, $source, $url );
				$changed[] = $col;
			}
		}
		foreach ( self::COUNT_COLUMNS as $col => $field ) {
			$raw = trim( (string) ( $row[ $col ] ?? '' ) );
			if ( '' === $raw || ! ctype_digit( $raw ) ) {
				continue; // Blank means unknown, never zero.
			}
			$current = (string) get_post_meta( $id, '_' . $field, true );
			if ( $current !== $raw && Provenance::can_overwrite( $id, $field, $source, '' === $current ) ) {
				update_post_meta( $id, '_' . $field, $raw );
				Provenance::record_field( $id, $field, $source, $url );
				$changed[] = $field;
			}
		}
		foreach ( self::LIST_COLUMNS as $col => $taxonomy ) {
			$ids = self::term_ids( $taxonomy, self::list_values( (string) ( $row[ $col ] ?? '' ) ) );
			if ( ! $ids ) {
				continue;
			}
			// Imports only add "yes" values. They never remove terms or override an explicit "no".
			$no = ( Venue::get( $id ) ? Venue::get( $id )->known_no()[ $taxonomy ] ?? [] : [] );
			$add = [];
			foreach ( $ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );
				if ( $term && ! in_array( $term->slug, $no, true ) && ! has_term( $term_id, $taxonomy, $id ) ) {
					$add[] = $term_id;
					Provenance::record_field( $id, $taxonomy . ':' . $term->slug, $source, $url );
				}
			}
			if ( $add ) {
				wp_add_object_terms( $id, $add, $taxonomy );
				$changed[] = $taxonomy;
			}
		}
		Pool_Data::sync_size_terms( $id );
		return $changed;
	}

	private static function log( string $run, int $line, string $action, int $venue_id, string $message, bool $dry_run = false ): void {
		global $wpdb;
		if ( $dry_run ) {
			return;
		}
		$wpdb->insert( self::log_table(), [
			'run_id'     => $run,
			'row_num'    => $line,
			'action'     => $action,
			'venue_id'   => $venue_id,
			'message'    => mb_substr( $message, 0, 500 ),
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		] );
	}

	/* ------------------------------------------------------------ admin */

	public static function menu(): void {
		add_submenu_page( Moderation::MENU, 'Import venues', 'Import venues', 'manage_options', 'ppn-import', [ __CLASS__, 'page' ] );
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$result = null;
		if ( isset( $_POST['ppn_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppn_import_nonce'] ) ), 'ppn_import' ) && ! empty( $_FILES['ppn_csv']['tmp_name'] ) ) {
			$file = $_FILES['ppn_csv']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
			$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
			if ( 'csv' !== $ext || (int) $file['size'] > 5 * MB_IN_BYTES || ! is_uploaded_file( $file['tmp_name'] ) ) {
				echo '<div class="notice notice-error"><p>Please upload a CSV file under 5 MB.</p></div>';
			} else {
				$source = isset( $_POST['ppn_source'] ) ? sanitize_text_field( wp_unslash( $_POST['ppn_source'] ) ) : 'import';
				$result = self::import( self::parse_csv( $file['tmp_name'] ), 'import:' . sanitize_key( $source ), ! empty( $_POST['ppn_publish'] ), ! empty( $_POST['ppn_dry_run'] ) );
			}
		}
		echo '<div class="wrap"><h1>Import venues</h1>';
		echo '<p>Upload a CSV with a header row. Columns: <code>name, street, city, state, zip, lat, lng, phone, website, provider, external_id, venue_type, tables_total, tables_7ft, tables_8ft, tables_9ft, tables_snooker, tables_carom, table_brands, pricing, amenities, source_url</code>. Use <code>;</code> between list values. Blank means unknown.</p>';
		echo '<p>Matching venues are updated only where a field is empty or came from an equal or lower-trust source; verified and owner-entered data is never overwritten. Uncertain matches are created as pending and flagged for review.</p>';
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'ppn_import', 'ppn_import_nonce' );
		echo '<p><label>CSV file <input type="file" name="ppn_csv" accept=".csv" required></label></p>';
		echo '<p><label>Source name <input type="text" name="ppn_source" placeholder="e.g. osm-2026-10" required></label></p>';
		echo '<p><label><input type="checkbox" name="ppn_dry_run" value="1" checked> Dry run (show what would happen, change nothing)</label></p>';
		echo '<p><label><input type="checkbox" name="ppn_publish" value="1"> Publish new venues immediately (otherwise they wait for review)</label></p>';
		submit_button( 'Import' );
		echo '</form>';
		if ( $result ) {
			printf( '<h2>Result</h2><p>%d created, %d updated, %d flagged for review, %d skipped. Run <code>%s</code>.</p>', (int) $result['created'], (int) $result['updated'], (int) $result['flagged'], (int) $result['skipped'], esc_html( $result['run'] ) );
			echo '<table class="widefat striped"><thead><tr><th>Line</th><th>Name</th><th>Action</th><th>Detail</th></tr></thead><tbody>';
			foreach ( $result['results'] as $r ) {
				echo '<tr>' . implode( '', array_map( static fn( $c ) => '<td>' . esc_html( (string) $c ) . '</td>', $r ) ) . '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	/**
	 * Import venues from a CSV file.
	 *
	 * ## OPTIONS
	 * <file>
	 * : Path to the CSV file.
	 * --source=<name>
	 * : Short name of the data source, e.g. osm-2026-10.
	 * [--publish]
	 * : Publish new venues instead of leaving them pending.
	 * [--dry-run]
	 * : Report what would happen without changing anything.
	 *
	 * @param array $args
	 * @param array $assoc
	 */
	public static function cli( $args, $assoc ): void {
		$path = $args[0] ?? '';
		if ( ! is_readable( $path ) ) {
			\WP_CLI::error( 'File not readable: ' . $path );
		}
		$result = self::import( self::parse_csv( $path ), 'import:' . sanitize_key( $assoc['source'] ?? 'cli' ), isset( $assoc['publish'] ), isset( $assoc['dry-run'] ) );
		foreach ( $result['results'] as $r ) {
			\WP_CLI::log( implode( ' | ', $r ) );
		}
		\WP_CLI::success( sprintf( '%d created, %d updated, %d flagged, %d skipped (run %s).', $result['created'], $result['updated'], $result['flagged'], $result['skipped'], $result['run'] ) );
	}
}
