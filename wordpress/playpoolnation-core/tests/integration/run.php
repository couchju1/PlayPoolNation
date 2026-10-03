<?php
/**
 * Integration tests against a live WordPress + My Listing install.
 *
 * Everything runs inside a database transaction that is rolled back, and rows in
 * My Listing's own tables are deleted explicitly. Email and geocoding are stubbed.
 * Run as an administrator: `wp eval-file tests/integration/run.php` or include it.
 *
 * @package PlayPoolNation\Core
 */

use PlayPoolNation\Core\Display;
use PlayPoolNation\Core\Forms;
use PlayPoolNation\Core\Importer;
use PlayPoolNation\Core\Moderation;
use PlayPoolNation\Core\Play;
use PlayPoolNation\Core\Pool_Data;
use PlayPoolNation\Core\Pool_Schema;
use PlayPoolNation\Core\Provenance;
use PlayPoolNation\Core\Schema_Org;
use PlayPoolNation\Core\Seo;
use PlayPoolNation\Core\Seo_Meta;
use PlayPoolNation\Core\Venue;
use PlayPoolNation\Core\Verification;
use PlayPoolNation\Core\Locations;

defined( 'ABSPATH' ) || exit;

return ( static function (): array {
	global $wpdb;
	$results = [];
	$check = static function ( string $name, bool $ok, string $detail = '' ) use ( &$results ) {
		$results[] = ( $ok ? 'PASS ' : 'FAIL ' ) . $name . ( $ok || '' === $detail ? '' : ' :: ' . $detail );
	};
	$created = [];
	$add_location = static function ( int $id, string $address, float $lat, float $lng ) use ( $wpdb ) {
		$wpdb->insert( $wpdb->prefix . 'mylisting_locations', [ 'listing_id' => $id, 'address' => $address, 'lat' => $lat, 'lng' => $lng ] );
		update_post_meta( $id, '_job_location', $address );
	};
	$make = static function ( string $type, string $title, string $status = 'publish' ) use ( &$created ): int {
		$id = (int) wp_insert_post( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => $status, 'post_title' => $title, 'post_author' => 1 ] );
		update_post_meta( $id, '_case27_listing_type', $type );
		update_post_meta( $id, '_ppn_test_fixture', 1 );
		$created[] = $id;
		return $id;
	};

	add_filter( 'pre_wp_mail', '__return_true' );
	add_filter( 'ppn_geocode_pre', static fn() => [ 'address' => '1 Test Way, Testville, SD 57000', 'lat' => 44.0001, 'lng' => -97.0001 ] );
	$wpdb->query( 'START TRANSACTION' );

	try {
		/* ---------- venue data model, unknown vs no ---------- */
		$v = $make( Pool_Schema::VENUE_TYPE, 'PPN Test Hall' );
		$add_location( $v, '1 Test Way, Testville, SD 57000', 44.0, -97.0 );
		Venue::flush_cache( $v );
		$venue = Venue::get( $v );
		$check( 'new venue: brand unknown', 'unknown' === $venue->state_of( 'table-brand', 'diamond' ) );
		$check( 'new venue: total tables unknown', null === $venue->total_tables() );

		Pool_Data::set_state( $v, 'table-brand', 'diamond', 'no' );
		$check( 'explicit no stored', 'no' === Venue::get( $v )->state_of( 'table-brand', 'diamond' ) );
		Pool_Data::set_state( $v, 'table-brand', 'diamond', 'yes' );
		$check( 'yes replaces no', 'yes' === Venue::get( $v )->state_of( 'table-brand', 'diamond' ) && ! in_array( 'diamond', Venue::get( $v )->known_no()['table-brand'] ?? [], true ) );

		update_post_meta( $v, '_tables-9ft', '4' );
		update_post_meta( $v, '_tables-7ft', '0' );
		Pool_Data::sync_size_terms( $v );
		$venue = Venue::get( $v );
		$check( 'count>0 sets size yes', 'yes' === $venue->state_of( 'table-size', '9-foot' ) );
		$check( 'count=0 sets size no', 'no' === $venue->state_of( 'table-size', '7-foot' ) );
		$check( 'blank count stays unknown', 'unknown' === $venue->state_of( 'table-size', '8-foot' ) );
		$check( 'total from per-size counts', 4 === $venue->total_tables() );
		$check( 'card fields', '4 tables' === get_post_meta( $v, '_card-tables', true ) && "Diamond, 9'" === get_post_meta( $v, '_card-equipment', true ), get_post_meta( $v, '_card-equipment', true ) );

		/* ---------- display ---------- */
		$GLOBALS['post'] = get_post( $v );
		$tables = Display::pool_tables( [ 'id' => $v ] );
		$check( 'pool tables section renders data', str_contains( $tables, 'ppn-total-num' ) && str_contains( $tables, 'Diamond' ) );
		$check( 'amenities empty renders marker only', Display::EMPTY === Display::amenities( [ 'id' => $v ] ) );
		$check( 'no leagues renders marker only', Display::EMPTY === Display::leagues( [ 'id' => $v ] ) );
		$check( 'trust section has suggest form', str_contains( Display::trust( [ 'id' => $v ] ), 'ppn_suggest_edit' ) );
		$check( 'no verification badge without data', ! str_contains( Display::trust( [ 'id' => $v ] ), 'ppn-badge' ) );

		/* ---------- verification ---------- */
		update_post_meta( $v, '_claimed', 1 ); // What My Listing does when an admin approves a claim.
		$ver = Verification::get( $v );
		$check( 'claim approval => Owner Verified with date', 'owner' === $ver['status'] && '' !== $ver['date'] );
		$check( 'badge shown once verified', str_contains( Display::trust( [ 'id' => $v ] ), 'Owner Verified' ) );
		$check( 'claimed venue shows no claim link', '' === PlayPoolNation\Core\Claims::claim_url( $v ) );

		/* ---------- schema ---------- */
		$schema = Schema_Org::venue( $v );
		$check( 'schema has no rating without reviews', ! isset( $schema['aggregateRating'] ) );
		$check( 'schema has no image without photo', ! isset( $schema['image'] ) );
		$check( 'schema address from data', 'Testville' === ( $schema['address']['addressLocality'] ?? '' ) );

		/* ---------- leagues & tournaments ---------- */
		$league = $make( Pool_Schema::LEAGUE_TYPE, 'PPN Test APA 8-Ball' );
		$wpdb->insert( $wpdb->prefix . 'mylisting_relations', [ 'parent_listing_id' => $v, 'child_listing_id' => $league, 'field_key' => Pool_Schema::LEAGUE_VENUE_FIELD, 'item_order' => 0 ] );
		wp_set_object_terms( $league, 'apa', 'league-org' );
		update_post_meta( $league, '_league-day', 'tuesday' );
		update_post_meta( $league, '_league-time', '7:00 PM' );
		Play::on_save( $league );
		$check( 'league time normalized', 1140 === (int) get_post_meta( $league, '_ppn_league_minutes', true ) );
		$venue = Venue::get( $v );
		$check( 'venue gets derived league terms', in_array( 'apa', $venue->term_slugs( 'league-org' ), true ) && in_array( 'leagues', $venue->term_slugs( 'pool-play' ), true ) );
		$check( 'leagues section lists league', str_contains( Display::leagues( [ 'id' => $v ] ), 'PPN Test APA 8-Ball' ) );
		$check( 'league inherits venue location', (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mylisting_locations WHERE listing_id = %d", $league ) ) );

		$future = $make( Pool_Schema::TOURNAMENT_TYPE, 'PPN Test 9-Ball Open' );
		$past = $make( Pool_Schema::TOURNAMENT_TYPE, 'PPN Test Old Event' );
		foreach ( [ $future => '+10 days', $past => '-10 days' ] as $tid => $when ) {
			$wpdb->insert( $wpdb->prefix . 'mylisting_relations', [ 'parent_listing_id' => $v, 'child_listing_id' => $tid, 'field_key' => Pool_Schema::TOURNAMENT_VENUE_FIELD, 'item_order' => 0 ] );
			$date = gmdate( 'Y-m-d 19:00:00', strtotime( $when ) );
			$wpdb->insert( $wpdb->prefix . 'mylisting_events', [ 'listing_id' => $tid, 'start_date' => $date, 'end_date' => $date, 'frequency' => 0, 'repeat_unit' => 'NONE', 'repeat_end' => $date, 'field_key' => 'event-date' ] );
		}
		$weekly = $make( Pool_Schema::TOURNAMENT_TYPE, 'PPN Test Weekly' );
		$wpdb->insert( $wpdb->prefix . 'mylisting_relations', [ 'parent_listing_id' => $v, 'child_listing_id' => $weekly, 'field_key' => Pool_Schema::TOURNAMENT_VENUE_FIELD, 'item_order' => 0 ] );
		$start = gmdate( 'Y-m-d 19:00:00', strtotime( '-30 days' ) );
		$wpdb->insert( $wpdb->prefix . 'mylisting_events', [ 'listing_id' => $weekly, 'start_date' => $start, 'end_date' => $start, 'frequency' => 7, 'repeat_unit' => 'DAY', 'repeat_end' => gmdate( 'Y-m-d 00:00:00', strtotime( '+90 days' ) ), 'field_key' => 'event-date' ] );
		$upcoming = Play::upcoming_tournaments( $v );
		$names = wp_list_pluck( $upcoming, 'name' );
		$check( 'upcoming includes future event', in_array( 'PPN Test 9-Ball Open', $names, true ) );
		$check( 'upcoming excludes past one-off', ! in_array( 'PPN Test Old Event', $names, true ) );
		$check( 'recurring event next date in future', in_array( 'PPN Test Weekly', $names, true ) );
		update_post_meta( $future, '_tournament-status', 'cancelled' );
		$check( 'cancelled tournaments hidden', ! in_array( 'PPN Test 9-Ball Open', wp_list_pluck( Play::upcoming_tournaments( $v ), 'name' ), true ) );
		Play::sync_venue( $v );
		$check( 'venue tagged for tournaments filter', in_array( 'tournaments', Venue::get( $v )->term_slugs( 'pool-play' ), true ) );

		/* ---------- suggestions & permissions ---------- */
		$sid = Moderation::create_suggestion( [ 'venue_id' => $v, 'reason' => 'table-count', 'details' => 'Now 6 tables', 'name' => '', 'email' => '' ] );
		$created[] = $sid;
		$check( 'suggestion stored pending', 'pending' === get_post_status( $sid ) );
		$check( 'suggestion did not change venue', '4' === get_post_meta( $v, '_tables-9ft', true ) );
		$admin = get_current_user_id();
		wp_set_current_user( 0 );
		$check( 'anonymous cannot edit suggestions', ! current_user_can( 'edit_post', $sid ) );
		$check( 'anonymous cannot edit venues', ! current_user_can( 'edit_post', $v ) );
		wp_set_current_user( $admin );
		$check( 'form token valid after delay', Forms::verify_token( Forms::make_token( time() - 10 ) ) );
		$check( 'form token rejected when instant', ! Forms::verify_token( Forms::make_token( time() ) ) );
		$check( 'forged token rejected', ! Forms::verify_token( ( time() - 10 ) . '.' . str_repeat( 'a', 64 ) ) );

		/* ---------- add a venue ---------- */
		$res = Forms::create_pending_venue( [ 'name' => 'PPN Test Hall', 'street' => '1 Test Way', 'city' => 'Testville', 'state' => 'SD', 'zip' => '57000', 'phone' => '', 'website' => '', 'venue_type' => 'pool-halls', 'tables' => '', 'notes' => '', 'relation' => 'player', 'email' => '' ] );
		$created[] = $res['id'];
		$check( 'submission is pending', 'pending' === get_post_status( $res['id'] ) );
		$check( 'duplicate flagged', $v === $res['duplicate_of'], 'got ' . $res['duplicate_of'] );
		$check( 'blank tables stays unknown', '' === (string) get_post_meta( $res['id'], '_number-of-tables', true ) );

		/* ---------- import + provenance ---------- */
		update_post_meta( $v, '_job_phone', '(605) 555-0100' );
		Provenance::record_field( $v, 'phone', 'admin' );
		Provenance::set_external_id( $v, 'testprov', 'T-1' );
		$summary = Importer::import( [
			[ 'name' => 'PPN Test Hall', 'street' => '1 Test Way', 'city' => 'Testville', 'state' => 'SD', 'provider' => 'testprov', 'external_id' => 'T-1', 'phone' => '(605) 555-9999', 'website' => 'https://example.org', 'tables_total' => '12', 'table_brands' => 'Valley' ],
			[ 'name' => 'PPN Brand New Room', 'street' => '9 Far Rd', 'city' => 'Elsewhere', 'state' => 'SD', 'lat' => '45.5', 'lng' => '-99.5' ],
			[ 'name' => '', 'street' => '' ],
		], 'import:test', false, false );
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT venue_id FROM ' . Importer::log_table() . ' WHERE run_id = %s', $summary['run'] ) ) as $vid ) {
			$created[] = (int) $vid;
		}
		$check( 'import counts', 1 === $summary['updated'] && 1 === $summary['created'] && 1 === $summary['skipped'], wp_json_encode( $summary['results'] ) );
		$check( 'admin-sourced phone not overwritten', '(605) 555-0100' === get_post_meta( $v, '_job_phone', true ) );
		$check( 'empty website filled', 'https://example.org' === get_post_meta( $v, '_job_website', true ) );
		$check( 'imported brand added as yes', 'yes' === Venue::get( $v )->state_of( 'table-brand', 'valley' ) );
		$check( 'import never removes existing brand', 'yes' === Venue::get( $v )->state_of( 'table-brand', 'diamond' ) );

		/* ---------- urls & seo ---------- */
		update_post_meta( $v, '_ppn_city', 'Testville' );
		update_post_meta( $v, '_ppn_state', 'SD' );
		Locations::maybe_set_slug( $v );
		$check( 'readable venue slug', str_starts_with( get_post_field( 'post_name', $v ), 'ppn-test-hall-testville-sd' ), get_post_field( 'post_name', $v ) );
		$check( 'listing found by old slug', $v === Seo::listing_by_slug( 'ppn-test-hall' ) );
		$args = Seo::sitemap_query_args( [ 'post_type' => [ 'page', 'product', 'job_listing' ] ] );
		$check( 'sitemap drops products', ! in_array( 'product', $args['post_type'], true ) && in_array( 'job_listing', $args['post_type'], true ) );
		$terms = Seo::sitemap_term_args( [ 'taxonomy' => [ 'region', 'table-brand', 'job_listing_category' ] ] );
		$check( 'sitemap drops filter taxonomies', ! in_array( 'table-brand', $terms['taxonomy'], true ) && in_array( 'region', $terms['taxonomy'], true ) );
		$thin = wp_insert_term( 'PPN Test Town', Pool_Schema::TAX_REGION );
		$thin_id = is_wp_error( $thin ) ? 0 : (int) $thin['term_id'];
		$check( 'sitemap excludes thin regions', $thin_id && in_array( $thin_id, Seo::sitemap_term_args( [ 'taxonomy' => [ 'region' ] ] )['exclude'], true ) );
		$only_filters = Seo::sitemap_term_args( [ 'taxonomy' => [ 'table-brand' ] ] );
		$check( 'filter-only term query matches nothing', [ 0 ] === $only_filters['include'] );
		$noindexed = Seo::noindex_page_ids();
		$with_front = Seo::sitemap_query_args( [ 'post_type' => [ 'page' ], 'exclude' => [ 99999999 ] ] );
		$check( 'sitemap keeps noindexed pages out even with exclude set', ! $noindexed || ! array_diff( $noindexed, $with_front['exclude'] ) );

		set_query_var( 'explore_tab', 'regions' );
		set_query_var( 'explore_region', 'south-dakota' );
		$check( 'region page title', str_starts_with( Seo_Meta::computed_title(), 'Pool Halls & Places to Play Pool in South Dakota' ), Seo_Meta::computed_title() );
		$check( 'region page canonical', str_ends_with( untrailingslashit( Seo_Meta::canonical( home_url( '/places/' ) ) ), '/places/south-dakota' ) );
		set_query_var( 'explore_region', 'no-such-region-ppn' );
		$check( 'unknown region is noindexed', Seo::should_noindex() );
		set_query_var( 'explore_tab', 'table-brand' );
		$check( 'filter page is noindexed', Seo::should_noindex() );
		set_query_var( 'explore_tab', '' );
		set_query_var( 'explore_region', '' );
	} catch ( \Throwable $e ) {
		$check( 'no exceptions', false, get_class( $e ) . ': ' . $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine() );
	} finally {
		$wpdb->query( 'ROLLBACK' );
		// My Listing tables may not be transactional; remove fixture rows explicitly.
		$ids = array_filter( array_map( 'intval', $created ) );
		if ( $ids ) {
			$in = implode( ',', $ids );
			foreach ( [ 'mylisting_locations' => 'listing_id', 'mylisting_events' => 'listing_id', 'mylisting_workhours' => 'listing_id' ] as $table => $col ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}{$table} WHERE {$col} IN ({$in})" );
			}
			$wpdb->query( "DELETE FROM {$wpdb->prefix}mylisting_relations WHERE parent_listing_id IN ({$in}) OR child_listing_id IN ({$in})" );
			$wpdb->query( "DELETE FROM " . Importer::log_table() . " WHERE venue_id IN ({$in})" );
			foreach ( $ids as $id ) {
				if ( get_post( $id ) ) {
					wp_delete_post( $id, true ); // Only reached if the posts table was not rolled back.
				}
			}
		}
		$wpdb->query( "DELETE FROM " . Importer::log_table() . " WHERE message LIKE '%PPN Test%'" );
		wp_cache_flush();
		PlayPoolNation\Core\Stats::flush();
	}
	$pass = count( array_filter( $results, static fn( $r ) => str_starts_with( $r, 'PASS' ) ) );
	array_unshift( $results, sprintf( '%d/%d passed', $pass, count( $results ) ) );
	return $results;
} )();
