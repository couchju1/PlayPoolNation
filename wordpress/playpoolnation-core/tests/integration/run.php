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
use PlayPoolNation\Core\Events;
use PlayPoolNation\Core\Instructors;
use PlayPoolNation\Core\My_Pool;
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

		/* ---------- events: dates ---------- */
		$tz = wp_timezone();
		$now = new \DateTimeImmutable( '2026-10-05 12:00', $tz );
		$base = [ 'date' => '2026-10-09', 'start_time' => '19:00', 'end_time' => '', 'repeat' => 'none', 'until' => '' ];
		$row = Events::date_row( $base, $now );
		$check( 'one-off date row', is_array( $row ) && '2026-10-09 19:00:00' === $row['start_date'] && 'NONE' === $row['repeat_unit'], wp_json_encode( $row ) );
		$row = Events::date_row( array_merge( $base, [ 'end_time' => '01:00' ] ), $now );
		$check( 'end time after midnight rolls to next day', is_array( $row ) && '2026-10-10 01:00:00' === $row['end_date'] );
		$row = Events::date_row( array_merge( $base, [ 'repeat' => 'weekly', 'until' => '2026-12-31' ] ), $now );
		$check( 'weekly repeat stored as 7 days until last date', is_array( $row ) && 7 === $row['frequency'] && 'DAY' === $row['repeat_unit'] && '2026-12-31 23:59:59' === $row['repeat_end'] );
		$check( 'monthly repeat', is_array( $r2 = Events::date_row( array_merge( $base, [ 'repeat' => 'monthly', 'until' => '2027-03-01' ] ), $now ) ) && 'MONTH' === $r2['repeat_unit'] && 1 === $r2['frequency'] );
		$check( 'past date rejected', 'date' === Events::date_row( array_merge( $base, [ 'date' => '2026-10-01' ] ), $now ) );
		$check( 'impossible date rejected', 'date' === Events::date_row( array_merge( $base, [ 'date' => '2026-02-30' ] ), $now ) );
		$check( 'repeat without last date rejected', 'until' === Events::date_row( array_merge( $base, [ 'repeat' => 'weekly' ] ), $now ) );

		/* ---------- events: posting ---------- */
		$check( 'venue search finds venue', in_array( $v, wp_list_pluck( Events::search_venues( 'PPN Test Hall' ), 'id' ), true ) );
		$check( 'anonymous cannot publish', ! Events::can_publish( 0, $v ) );
		$check( 'admin can publish', Events::can_publish( (int) $admin, $v ) );
		$owner_id = wp_insert_user( [ 'user_login' => 'ppn_test_owner_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'user_email' => 'ppn-owner-' . wp_generate_password( 6, false ) . '@example.invalid', 'role' => 'subscriber' ] );
		$test_users = is_wp_error( $owner_id ) ? [] : [ (int) $owner_id ];
		$check( 'unclaimed owner cannot publish', ! is_wp_error( $owner_id ) && ! Events::can_publish( (int) $owner_id, $v ) );
		wp_update_post( [ 'ID' => $v, 'post_author' => (int) $owner_id ] ); // $v is claimed above.
		$check( 'owner of claimed venue can publish', Events::can_publish( (int) $owner_id, $v ) );

		$future_date = wp_date( 'Y-m-d', time() + 5 * DAY_IN_SECONDS );
		$form = [ 'name' => 'PPN Test Friday 8-Ball', 'event_type' => 'tournament', 'venue_id' => $v, 'games' => [ '8-ball' ], 'table_size' => '7-foot', 'entry_fee' => '20', 'added_money' => '100', 'eligibility' => 'Open', 'registration' => 'At the bar', 'link' => '', 'description' => 'Race to 3', 'relation' => 'player', 'email' => '' ];
		$dates = Events::date_row( [ 'date' => $future_date, 'start_time' => '19:00', 'end_time' => '', 'repeat' => 'weekly', 'until' => wp_date( 'Y-m-d', time() + 60 * DAY_IN_SECONDS ) ] );
		$pending_event = Events::create( $form, $dates, 'pending', 0 );
		$created[] = $pending_event;
		$check( 'community event saved pending', $pending_event && 'pending' === get_post_status( $pending_event ) );
		$check( 'pending event linked to venue', $v === (int) get_post_meta( $pending_event, '_ppn_venue_id', true ) );
		$check( 'pending event not shown on venue', ! in_array( $pending_event, wp_list_pluck( Play::upcoming_tournaments( $v, 20 ), 'id' ), true ) );

		$clinic = Events::create( array_merge( $form, [ 'name' => 'PPN Test Break Clinic', 'event_type' => 'clinic', 'games' => [] ] ), Events::date_row( [ 'date' => $future_date, 'start_time' => '18:00', 'end_time' => '20:00', 'repeat' => 'none', 'until' => '' ] ), 'publish', (int) $owner_id );
		$created[] = $clinic;
		$up = Play::upcoming_tournaments( $v, 20 );
		$mine = array_values( array_filter( $up, static fn( $t ) => $t['id'] === $clinic ) );
		$check( 'owner event published and listed on venue', $mine && 'Clinic or lesson' === $mine[0]['type'] );
		$check( 'event stores ML date row', 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mylisting_events WHERE listing_id = %d AND field_key = 'event-date'", $clinic ) ) );
		$check( 'event copies venue map location', (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mylisting_locations WHERE listing_id = %d", $clinic ) ) );
		$check( 'event details show type', str_contains( Display::tournament_details( [ 'id' => $clinic ] ), 'Clinic or lesson' ) );
		$check( 'upcoming event not ended', ! Events::is_ended( $clinic ) );
		$check( 'past one-off event ended', Events::is_ended( $past ) );
		$check( 'running weekly series not ended', ! Events::is_ended( $weekly ) );
		$check( 'event description is factual', str_starts_with( Seo_Meta::event_description( Venue::get( $clinic ) ), 'Clinic or lesson at PPN Test Hall' ), Seo_Meta::event_description( Venue::get( $clinic ) ) );
		$check( 'trust block links to post an event', str_contains( Display::trust( [ 'id' => $v ] ), 'venue=' . $v ) );

		/* ---------- instructors ---------- */
		$check( 'city/state from "City, ST"', [ 'city' => 'Sioux Falls', 'state' => 'SD' ] === Instructors::city_state( 'Sioux Falls, SD' ) );
		$check( 'city/state from state name', [ 'city' => 'Sioux Falls', 'state' => 'SD' ] === Instructors::city_state( 'Sioux Falls, South Dakota, USA' ) );
		$check( 'city/state from full address', [ 'city' => 'Testville', 'state' => 'SD' ] === Instructors::city_state( '1 Test Way, Testville, SD 57000' ) );
		$check( 'no guess from a bare city', null === Instructors::city_state( 'Downtown' ) );
		$ins = $make( Pool_Schema::INSTRUCTOR_TYPE, 'PPN Test Coach' );
		$add_location( $ins, '12 Home St, Testville, SD 57000', 44.01, -97.01 );
		$check( 'instructor location reduced to city', 'Testville, SD' === Instructors::coarsen_location( $ins ) && 'Testville, SD' === get_post_meta( $ins, '_job_location', true ) );
		$check( 'home street not kept', ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mylisting_locations WHERE listing_id = %d AND address LIKE %s", $ins, '%Home St%' ) ) );
		$check( 'instructor gets no region terms', ! wp_get_object_terms( $ins, Pool_Schema::TAX_REGION, [ 'fields' => 'ids' ] ) );
		wp_set_object_terms( $ins, [ 'pbia-advanced' ], 'instructor-credential' );
		wp_set_object_terms( $ins, [ 'beginners', 'position-play' ], 'lesson-focus' );
		wp_set_object_terms( $ins, [ 'one-on-one' ], 'lesson-format' );
		update_post_meta( $ins, '_lesson-rate', '60' );
		Venue::flush_cache( $ins );
		$creds = Instructors::credentials( $ins );
		$check( 'credential self-reported until checked', 1 === count( $creds ) && '' === $creds[0]['verified'] );
		$GLOBALS['post'] = get_post( $ins );
		$details = Instructors::details( [ 'id' => $ins ] );
		$check( 'unverified credential has no badge', str_contains( $details, 'PBIA Advanced Instructor' ) && ! str_contains( $details, 'ppn-badge' ) );
		$check( 'person schema omits unverified credential', ! isset( Schema_Org::instructor( $ins )['hasCredential'] ) );
		Instructors::set_verified( $ins, [ 'pbia-advanced', 'pbia-master' ] );
		$check( 'only listed credentials can be verified', [ 'pbia-advanced' ] === array_keys( (array) get_post_meta( $ins, '_ppn_credentials_verified', true ) ) );
		$check( 'verified credential shows badge', str_contains( Instructors::details( [ 'id' => $ins ] ), 'ppn-badge' ) );
		$check( 'person schema has verified credential', 'PBIA Advanced Instructor' === ( Schema_Org::instructor( $ins )['hasCredential'][0]['name'] ?? '' ) );
		$check( 'instructor description', str_contains( Seo_Meta::instructor_description( Venue::get( $ins ) ), 'teaches pool in Testville, SD' ) && str_contains( Seo_Meta::instructor_description( Venue::get( $ins ) ), '$60 an hour' ) );
		$check( 'no instructors section before link', Display::EMPTY === Instructors::at_venue( [ 'id' => $v ] ) );
		$wpdb->insert( $wpdb->prefix . 'mylisting_relations', [ 'parent_listing_id' => $v, 'child_listing_id' => $ins, 'field_key' => Pool_Schema::INSTRUCTOR_VENUE_FIELD, 'item_order' => 0 ] );
		$check( 'venue lists instructor who teaches there', str_contains( Instructors::at_venue( [ 'id' => $v ] ), 'PPN Test Coach' ) );

		/* ---------- my pool ---------- */
		$uid = (int) $owner_id;
		$check( 'area at 0,0 rejected', ! My_Pool::set_home( $uid, 'Nowhere', 0.0, 0.0, 25 ) );
		$check( 'area saved with allowed radius', My_Pool::set_home( $uid, 'Testville, SD', 44.01, -97.0, 37 ) && 25 === My_Pool::home( $uid )['radius'] );
		$near = My_Pool::nearby( 44.01, -97.0, 10, Pool_Schema::VENUE_TYPE, 50 );
		$check( 'nearby finds venue with distance', isset( $near[ $v ] ) && $near[ $v ] > 0.5 && $near[ $v ] < 1.0, wp_json_encode( $near[ $v ] ?? null ) );
		$check( 'nearby respects radius', ! isset( My_Pool::nearby( 46.0, -97.0, 10, Pool_Schema::VENUE_TYPE, 50 )[ $v ] ) );
		$check( 'nearby events use copied location', isset( My_Pool::nearby( 44.01, -97.0, 10, Pool_Schema::TOURNAMENT_TYPE, 50 )[ $clinic ] ) );
		update_user_meta( $uid, '_case27_user_bookmarks', [ $v, $clinic, 999999999 ] );
		$check( 'saved venues only', [ $v ] === My_Pool::saved( $uid, Pool_Schema::VENUE_TYPE ) );
		$check( 'events at saved places', in_array( $clinic, wp_list_pluck( My_Pool::saved_events( $uid ), 'id' ), true ) );
		$check( 'pending events stay off My Pool', ! in_array( $pending_event, wp_list_pluck( My_Pool::saved_events( $uid ), 'id' ), true ) );
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
		$check( 'players land on My Pool after sign-in', My_Pool::page_url() === My_Pool::after_login( $account, get_userdata( $uid ) ) );
		$check( 'explicit redirect kept', home_url( '/events/' ) === My_Pool::after_login( home_url( '/events/' ), get_userdata( $uid ) ) );
		$check( 'staff keep account dashboard', $account === My_Pool::after_login( $account, get_userdata( (int) $admin ) ) );
		wp_set_current_user( $uid );
		$page = My_Pool::render();
		wp_set_current_user( $admin );
		$check( 'my pool shows saved place', str_contains( $page, 'Your saved places' ) && str_contains( $page, 'PPN Test Hall' ) && str_contains( $page, 'ppn_unsave' ) );
		$check( 'my pool shows events at saved places', str_contains( $page, 'PPN Test Break Clinic' ) );
		$check( 'my pool shows area', str_contains( $page, 'Testville, SD' ) && str_contains( $page, 'Places near you' ) );
		wp_set_current_user( 0 );
		$out = My_Pool::render();
		wp_set_current_user( $admin );
		$check( 'signed-out view invites sign in', str_contains( $out, 'Your pool, in one place' ) && ! str_contains( $out, 'PPN Test Hall' ) );

		/* ---------- sign in / header link ---------- */
		$main_menu = wp_get_nav_menu_object( 'Main Menu' );
		if ( $main_menu ) {
			wp_set_current_user( 0 );
			$items = My_Pool::menu_account_item( [], (object) [ 'menu' => $main_menu ] );
			$check( 'visitors see Sign in in the menu', 'Sign in' === ( end( $items )->title ?? '' ) && str_contains( end( $items )->url, '/sign-in' ) );
			wp_set_current_user( $uid );
			$items = My_Pool::menu_account_item( [], (object) [ 'menu' => (int) $main_menu->term_id ] );
			$check( 'members see My Pool in the menu', 'My Pool' === ( end( $items )->title ?? '' ) );
			wp_set_current_user( $admin );
			$check( 'other menus untouched', [] === My_Pool::menu_account_item( [], (object) [ 'menu' => 'Woocommerce menu' ] ) );
		}
		wp_set_current_user( 0 );
		$join = My_Pool::sign_in( [ 'tab' => 'register' ] );
		wp_set_current_user( $admin );
		$check( 'join page opens on register', str_contains( $join, 'data-ppn-tab="register"' ) );

		/* ---------- venue-only counts ---------- */
		Locations::sync( $v );
		Play::copy_location( $clinic, $v ); // Events copy their venue's regions.
		delete_transient( Locations::COUNT_CACHE );
		$state_term = get_term_by( 'name', 'South Dakota', Pool_Schema::TAX_REGION );
		$venues_only = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_status = 'publish'
			 JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_case27_listing_type' AND m.meta_value = 'place'
			 WHERE tt.term_id = %d", $state_term ? $state_term->term_id : 0 ) );
		$check( 'region place count ignores events', $state_term && $venues_only === Locations::venue_count( (int) $state_term->term_id ), $venues_only . ' vs ' . ( $state_term ? Locations::venue_count( (int) $state_term->term_id ) : -1 ) );
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
		foreach ( $test_users ?? [] as $uid ) {
			if ( get_userdata( $uid ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $uid );
			}
		}
		wp_cache_flush();
		PlayPoolNation\Core\Stats::flush();
	}
	$pass = count( array_filter( $results, static fn( $r ) => str_starts_with( $r, 'PASS' ) ) );
	array_unshift( $results, sprintf( '%d/%d passed', $pass, count( $results ) ) );
	return $results;
} )();
