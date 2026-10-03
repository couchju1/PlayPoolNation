<?php
/**
 * Leagues and tournaments.
 *
 * Both are My Listing listing types (`league`, `tournament`) related to a venue through
 * My Listing's relations table (venue = parent, league/tournament = child). Tournament
 * dates live in My Listing's indexed events table, including recurring schedules.
 *
 * Venues get derived terms so search filters work on real data:
 *  - pool-play: "leagues" when an active league exists, "tournaments" when one is upcoming.
 *  - league-org: organizations of the venue's active leagues.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Format;

defined( 'ABSPATH' ) || exit;

final class Play {

	public const CRON = 'ppn_daily_sync';

	public static function boot(): void {
		add_action( 'mylisting/admin/save-listing-data', [ __CLASS__, 'on_save' ], 80, 1 );
		add_action( 'mylisting/submission/save-listing-data', [ __CLASS__, 'on_save' ], 80, 1 );
		add_action( 'transition_post_status', [ __CLASS__, 'on_status_change' ], 30, 3 );
		add_action( self::CRON, [ __CLASS__, 'sync_all_venues' ] );
		add_action( 'init', [ __CLASS__, 'schedule' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	private static function relation_field( string $type ): string {
		return Pool_Schema::LEAGUE_TYPE === $type ? Pool_Schema::LEAGUE_VENUE_FIELD : Pool_Schema::TOURNAMENT_VENUE_FIELD;
	}

	public static function on_save( $post_id ): void {
		$post_id = (int) $post_id;
		$type = Venue::listing_type( $post_id );
		if ( ! in_array( $type, [ Pool_Schema::LEAGUE_TYPE, Pool_Schema::TOURNAMENT_TYPE ], true ) ) {
			return;
		}
		if ( Pool_Schema::LEAGUE_TYPE === $type ) {
			$minutes = Format::parse_time( (string) get_post_meta( $post_id, '_league-time', true ) );
			null === $minutes ? delete_post_meta( $post_id, '_ppn_league_minutes' ) : update_post_meta( $post_id, '_ppn_league_minutes', $minutes );
		}

		$venue_id = self::parent_venue( $post_id, self::relation_field( $type ) );
		$previous = (int) get_post_meta( $post_id, '_ppn_venue_id', true );
		if ( $venue_id ) {
			self::copy_location( $post_id, $venue_id );
			update_post_meta( $post_id, '_ppn_venue_id', $venue_id );
			self::sync_venue( $venue_id );
		} else {
			delete_post_meta( $post_id, '_ppn_venue_id' );
		}
		if ( $previous && $previous !== $venue_id ) {
			self::sync_venue( $previous );
		}
	}

	public static function on_status_change( string $new, string $old, \WP_Post $post ): void {
		if ( $new === $old || Pool_Schema::POST_TYPE !== $post->post_type ) {
			return;
		}
		$venue_id = (int) get_post_meta( $post->ID, '_ppn_venue_id', true );
		if ( $venue_id ) {
			self::sync_venue( $venue_id );
		}
	}

	public static function parent_venue( int $child_id, string $field_key ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT parent_listing_id FROM {$wpdb->prefix}mylisting_relations WHERE child_listing_id = %d AND field_key = %s ORDER BY item_order LIMIT 1",
			$child_id,
			$field_key
		) );
	}

	/** @return int[] published child listing IDs */
	public static function children( int $venue_id, string $field_key ): array {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT r.child_listing_id FROM {$wpdb->prefix}mylisting_relations r
			 JOIN {$wpdb->posts} p ON p.ID = r.child_listing_id AND p.post_status = 'publish'
			 WHERE r.parent_listing_id = %d AND r.field_key = %s ORDER BY r.item_order",
			$venue_id,
			$field_key
		) ) );
	}

	/** Leagues at a venue that are not marked inactive, ordered by weekday then time. */
	public static function leagues( int $venue_id ): array {
		$ids = self::children( $venue_id, Pool_Schema::LEAGUE_VENUE_FIELD );
		Venue::prime( $ids );
		$days = array_keys( Pool_Schema::LEAGUE_DAYS );
		$out = [];
		foreach ( $ids as $id ) {
			$status = (string) get_post_meta( $id, '_league-status', true );
			if ( 'inactive' === $status ) {
				continue;
			}
			$day = (string) get_post_meta( $id, '_league-day', true );
			$out[] = [
				'id'      => $id,
				'name'    => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
				'url'     => get_permalink( $id ),
				'orgs'    => wp_get_object_terms( $id, 'league-org', [ 'fields' => 'names' ] ),
				'games'   => wp_get_object_terms( $id, 'game-type', [ 'fields' => 'names' ] ),
				'day'     => Pool_Schema::LEAGUE_DAYS[ $day ] ?? '',
				'time'    => self::league_time_label( $id ),
				'status'  => Pool_Schema::LEAGUE_STATUSES[ $status ] ?? '',
				'season'  => (string) get_post_meta( $id, '_league-season', true ),
				'_sort'   => ( false === ( $i = array_search( $day, $days, true ) ) ? 9 : $i ) * 1440 + (int) get_post_meta( $id, '_ppn_league_minutes', true ),
			];
		}
		usort( $out, static fn( $a, $b ) => $a['_sort'] <=> $b['_sort'] );
		return $out;
	}

	private static function league_time_label( int $id ): string {
		$minutes = get_post_meta( $id, '_ppn_league_minutes', true );
		return '' === $minutes ? trim( (string) get_post_meta( $id, '_league-time', true ) ) : Format::clock( (int) $minutes );
	}

	/**
	 * Next occurrence of a My Listing event row at or after $now, or null.
	 *
	 * @param object $row { start_date, end_date, frequency, repeat_unit, repeat_end }
	 */
	public static function next_occurrence( object $row, \DateTimeImmutable $now ): ?\DateTimeImmutable {
		$tz = $now->getTimezone();
		try {
			$start = new \DateTimeImmutable( $row->start_date, $tz );
		} catch ( \Exception $e ) {
			return null;
		}
		if ( $start >= $now ) {
			return $start;
		}
		$unit = (string) $row->repeat_unit;
		$freq = max( 1, (int) $row->frequency );
		if ( 'NONE' === $unit || ! in_array( $unit, [ 'DAY', 'MONTH' ], true ) ) {
			return null;
		}
		$end = ( $row->repeat_end && '0000-00-00 00:00:00' !== $row->repeat_end ) ? new \DateTimeImmutable( $row->repeat_end, $tz ) : null;
		$step = new \DateInterval( 'DAY' === $unit ? "P{$freq}D" : "P{$freq}M" );
		$next = $start;
		for ( $i = 0; $i < 2000 && $next < $now; $i++ ) {
			$next = $next->add( $step );
		}
		if ( $next < $now || ( $end && $next > $end ) ) {
			return null;
		}
		return $next;
	}

	/** Upcoming tournaments at a venue, soonest first. */
	public static function upcoming_tournaments( int $venue_id, int $limit = 6, ?\DateTimeImmutable $now = null ): array {
		$ids = self::children( $venue_id, Pool_Schema::TOURNAMENT_VENUE_FIELD );
		return self::upcoming_from_ids( $ids, $limit, $now );
	}

	public static function upcoming_from_ids( array $ids, int $limit = 6, ?\DateTimeImmutable $now = null ): array {
		global $wpdb;
		if ( ! $ids ) {
			return [];
		}
		$now = $now ?? new \DateTimeImmutable( 'now', wp_timezone() );
		$in = implode( ',', array_map( 'intval', $ids ) );
		$rows = $wpdb->get_results( "SELECT listing_id, start_date, end_date, frequency, repeat_unit, repeat_end FROM {$wpdb->prefix}mylisting_events WHERE listing_id IN ({$in})" );
		Venue::prime( $ids );
		$out = [];
		foreach ( $rows as $row ) {
			$id = (int) $row->listing_id;
			$status = (string) get_post_meta( $id, '_tournament-status', true );
			if ( in_array( $status, [ 'cancelled', 'completed' ], true ) ) {
				continue;
			}
			$next = self::next_occurrence( $row, $now );
			if ( ! $next || ( isset( $out[ $id ] ) && $out[ $id ]['when'] <= $next ) ) {
				continue;
			}
			$out[ $id ] = [
				'id'          => $id,
				'name'        => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
				'url'         => get_permalink( $id ),
				'when'        => $next,
				'recurring'   => 'NONE' !== $row->repeat_unit,
				'games'       => wp_get_object_terms( $id, 'game-type', [ 'fields' => 'names' ] ),
				'type'        => Events::event_type_label( $id ),
				'entry_fee'   => (string) get_post_meta( $id, '_entry-fee', true ),
				'added_money' => (string) get_post_meta( $id, '_added-money', true ),
				'status'      => Pool_Schema::TOURNAMENT_STATUSES[ $status ] ?? '',
			];
		}
		uasort( $out, static fn( $a, $b ) => $a['when'] <=> $b['when'] );
		return array_slice( array_values( $out ), 0, $limit );
	}

	/** Recompute derived league/tournament terms on a venue. */
	public static function sync_venue( int $venue_id ): void {
		if ( ! Venue::is_venue( $venue_id ) ) {
			return;
		}
		$leagues = self::leagues( $venue_id );
		$orgs = [];
		foreach ( $leagues as $league ) {
			foreach ( wp_get_object_terms( $league['id'], 'league-org', [ 'fields' => 'slugs' ] ) as $slug ) {
				$orgs[] = $slug;
			}
		}
		$derived = [
			'league-org' => array_values( array_unique( $orgs ) ),
			'pool-play'  => array_values( array_filter( [
				$leagues ? 'leagues' : '',
				self::upcoming_tournaments( $venue_id, 1 ) ? 'tournaments' : '',
			] ) ),
		];

		$previous = get_post_meta( $venue_id, '_ppn_derived_terms', true );
		$previous = is_array( $previous ) ? $previous : [];
		foreach ( $derived as $taxonomy => $slugs ) {
			$stale = array_diff( $previous[ $taxonomy ] ?? [], $slugs );
			if ( $stale ) {
				wp_remove_object_terms( $venue_id, array_values( $stale ), $taxonomy );
			}
			if ( $slugs ) {
				wp_add_object_terms( $venue_id, $slugs, $taxonomy );
			}
		}
		update_post_meta( $venue_id, '_ppn_derived_terms', $derived );
	}

	public static function sync_all_venues(): void {
		$ids = get_posts( [
			'post_type'      => Pool_Schema::POST_TYPE,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_case27_listing_type',
			'meta_value'     => Pool_Schema::VENUE_TYPE,
		] );
		foreach ( $ids as $id ) {
			self::sync_venue( (int) $id );
		}
	}

	/** Leagues and tournaments inherit the venue's map location so they appear on maps and in distance search. */
	public static function copy_location( int $child_id, int $venue_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'mylisting_locations';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT address, lat, lng FROM {$table} WHERE listing_id = %d", $venue_id ) );
		if ( ! $rows ) {
			return;
		}
		$wpdb->delete( $table, [ 'listing_id' => $child_id ] );
		foreach ( $rows as $row ) {
			$wpdb->insert( $table, [ 'listing_id' => $child_id, 'address' => $row->address, 'lat' => $row->lat, 'lng' => $row->lng ] );
		}
		update_post_meta( $child_id, '_job_location', $rows[0]->address );
		$regions = wp_get_object_terms( $venue_id, Pool_Schema::TAX_REGION, [ 'fields' => 'ids' ] );
		if ( ! is_wp_error( $regions ) && $regions ) {
			wp_set_object_terms( $child_id, array_map( 'intval', $regions ), Pool_Schema::TAX_REGION );
		}
	}
}
