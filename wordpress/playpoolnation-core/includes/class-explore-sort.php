<?php
/**
 * Default order on /places/: nearest first when the visitor shared a location or
 * searched a place, otherwise venues we know the most about first, then by name.
 * "Name (A to Z)" stays available in the Sort by menu.
 *
 * Completeness is stored per venue (`_ppn_completeness`) so My Listing can sort by it
 * with a plain meta clause. It is refreshed on save and by the daily sync.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Explore_Sort {

	public const META = '_ppn_completeness';
	public const DEFAULT_KEY = 'recommended';

	public static function boot(): void {
		add_action( 'save_post_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'on_save' ], 999, 1 );
		add_action( Play::CRON, [ __CLASS__, 'refresh_all' ], 20 );
		add_action( 'mylisting/get-listings/before-query', [ __CLASS__, 'nearest_first' ], 10, 1 );
	}

	/** Sort options for the venue type, best match first (it is the default). */
	public static function options(): array {
		$option = static fn( string $key, string $label, array $clauses, array $extra = [] ) => array_merge( [ 'label' => $label, 'key' => $key, 'ignore_priority' => false, 'is_new' => false, 'clauses' => $clauses ], $extra );
		$clause = static fn( string $orderby, string $order, string $context = 'option', string $type = 'CHAR' ) => [ 'orderby' => $orderby, 'order' => $order, 'context' => $context, 'type' => $type, 'custom_type' => false ];
		return [
			$option( self::DEFAULT_KEY, 'Best match', [ $clause( self::META, 'DESC', 'raw_meta_key', 'NUMERIC' ), $clause( 'title', 'ASC' ) ] ),
			$option( 'nearby', 'Nearby', [ $clause( 'proximity', 'ASC' ) ], [ 'notes' => [ 'has-proximity-clause' ] ] ),
			$option( 'a-z', 'Name (A to Z)', [ $clause( 'title', 'ASC' ) ] ),
			$option( 'top-rated', 'Top rated', [ $clause( 'rating', 'DESC', 'option', 'DECIMAL(10,2)' ) ], [ 'ignore_priority' => true ] ),
			$option( 'latest', 'Newest', [ $clause( 'date', 'DESC' ) ] ),
		];
	}

	/** How much we know about a venue's pool setup; higher sorts first. */
	public static function score( Venue $v ): int {
		$score = 0;
		$score += null !== $v->total_tables() ? 4 : 0;
		$score += $v->size_slugs() ? 3 : 0;
		$score += $v->term_names( 'table-brand' ) ? 3 : 0;
		$score += ( $v->term_slugs( 'pool-pricing' ) || '' !== $v->field( 'hourly-rate' ) || '' !== $v->field( 'game-price' ) ) ? 1 : 0;
		$score += $v->term_slugs( 'pool-play' ) ? 1 : 0;
		$score += '' !== $v->photo() ? 1 : 0;
		$score += $v->hour_ranges() ? 1 : 0;
		return $score;
	}

	public static function refresh( int $id ): void {
		$v = Venue::get( $id );
		if ( $v && Venue::is_venue( $id ) ) {
			Venue::flush_cache( $id );
			update_post_meta( $id, self::META, self::score( $v ) );
		}
	}

	public static function on_save( int $id ): void {
		if ( ! wp_is_post_revision( $id ) && ! wp_is_post_autosave( $id ) ) {
			self::refresh( $id );
		}
	}

	/** Every venue needs the meta: the sort's meta clause leaves out venues without it. */
	public static function refresh_all(): int {
		$ids = get_posts( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_case27_listing_type', 'meta_value' => Pool_Schema::VENUE_TYPE ] );
		Venue::prime( $ids );
		foreach ( $ids as $id ) {
			self::refresh( (int) $id );
		}
		return count( $ids );
	}

	/**
	 * With the default sort, a location search orders by distance. My Listing's location
	 * filter already puts the matching IDs in post__in nearest first.
	 *
	 * @param array $args WP_Query args, by reference.
	 */
	public static function nearest_first( &$args ): void {
		$form = isset( $_REQUEST['form_data'] ) && is_array( $_REQUEST['form_data'] ) ? wp_unslash( $_REQUEST['form_data'] ) : wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		$sort = sanitize_key( (string) ( $form['sort'] ?? '' ) );
		$located = ! empty( $form['lat'] ) && ! empty( $form['lng'] );
		$post_in = (array) ( $args['post__in'] ?? [] );
		if ( $located && in_array( $sort, [ '', self::DEFAULT_KEY ], true ) && $post_in && [ 'none' ] !== $post_in ) {
			$args['orderby'] = 'post__in';
			unset( $args['order'] );
		}
	}
}
