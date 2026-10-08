<?php
/**
 * Read model for a venue. Batch-primes meta, terms, locations and hours so
 * lists of venues do not cause per-venue queries.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Address;
use PlayPoolNation\Core\Helpers\Format;
use PlayPoolNation\Core\Helpers\Tri_State;

defined( 'ABSPATH' ) || exit;

final class Venue {

	/** @var array<int,array> */
	private static array $locations = [];
	/** @var array<int,array> */
	private static array $hours = [];

	private function __construct( public readonly \WP_Post $post ) {}

	public static function get( int $id ): ?self {
		$post = get_post( $id );
		if ( ! $post || Pool_Schema::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return new self( $post );
	}

	public static function listing_type( int $id ): string {
		return (string) get_post_meta( $id, '_case27_listing_type', true );
	}

	public static function is_venue( int $id ): bool {
		return Pool_Schema::VENUE_TYPE === self::listing_type( $id );
	}

	/**
	 * Load meta, terms, locations and hours for many listings in a handful of queries.
	 *
	 * @param int[] $ids
	 */
	public static function prime( array $ids ): void {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids ) {
			return;
		}
		update_meta_cache( 'post', $ids );
		update_object_term_cache( $ids, Pool_Schema::POST_TYPE );

		$missing = array_diff( $ids, array_keys( self::$locations ) );
		if ( $missing ) {
			$in = implode( ',', array_map( 'intval', $missing ) );
			foreach ( $missing as $id ) {
				self::$locations[ $id ] = [];
				self::$hours[ $id ] = [];
			}
			// IDs are integers cast above, so interpolation is safe here.
			foreach ( $wpdb->get_results( "SELECT listing_id, address, lat, lng FROM {$wpdb->prefix}mylisting_locations WHERE listing_id IN ({$in}) ORDER BY id" ) as $row ) {
				self::$locations[ (int) $row->listing_id ][] = $row;
			}
			foreach ( $wpdb->get_results( "SELECT listing_id, start, end, timezone FROM {$wpdb->prefix}mylisting_workhours WHERE listing_id IN ({$in})" ) as $row ) {
				self::$hours[ (int) $row->listing_id ][] = $row;
			}
		}
	}

	public static function flush_cache( int $id ): void {
		unset( self::$locations[ $id ], self::$hours[ $id ] );
	}

	public function id(): int {
		return (int) $this->post->ID;
	}

	public function name(): string {
		return html_entity_decode( get_the_title( $this->post ), ENT_QUOTES );
	}

	public function url(): string {
		return (string) get_permalink( $this->post );
	}

	public function meta( string $key ): string {
		$value = get_post_meta( $this->id(), $key, true );
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/** Value of a My Listing field (stored as `_<key>`). */
	public function field( string $key ): string {
		return $this->meta( '_' . $key );
	}

	public function phone(): string {
		return $this->meta( '_job_phone' );
	}

	public function website(): string {
		return $this->meta( '_job_website' );
	}

	public function location(): ?object {
		self::prime( [ $this->id() ] );
		return self::$locations[ $this->id() ][0] ?? null;
	}

	public function address(): string {
		$loc = $this->location();
		return $loc ? (string) $loc->address : $this->meta( '_job_location' );
	}

	/** @return array{street:string,city:string,state:string,postal_code:string,country:string} */
	public function address_parts(): array {
		$stored = [
			'street'      => $this->meta( '_ppn_street' ),
			'city'        => $this->meta( '_ppn_city' ),
			'state'       => $this->meta( '_ppn_state' ),
			'postal_code' => $this->meta( '_ppn_postal_code' ),
			'country'     => $this->meta( '_ppn_country' ),
		];
		if ( '' !== $stored['city'] || '' !== $stored['state'] ) {
			return $stored;
		}
		return Address::parse( $this->address() );
	}

	public function city_state(): string {
		$a = $this->address_parts();
		return implode( ', ', array_filter( [ $a['city'], $a['state'] ] ) );
	}

	/** @return string[] term slugs */
	public function term_slugs( string $taxonomy ): array {
		$terms = get_the_terms( $this->post, $taxonomy );
		return ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'slug' ) : [];
	}

	/** @return \WP_Term[] */
	public function terms( string $taxonomy ): array {
		$terms = get_the_terms( $this->post, $taxonomy );
		return ( $terms && ! is_wp_error( $terms ) ) ? $terms : [];
	}

	public function term_names( string $taxonomy ): array {
		return array_map( static fn( $t ) => html_entity_decode( $t->name, ENT_QUOTES ), $this->terms( $taxonomy ) );
	}

	/** @return array<string,string[]> taxonomy => slugs known to be absent */
	public function known_no(): array {
		$value = get_post_meta( $this->id(), '_ppn_known_no', true );
		return is_array( $value ) ? $value : [];
	}

	public function state_of( string $taxonomy, string $slug ): string {
		$no = $this->known_no()[ $taxonomy ] ?? [];
		return Tri_State::from_term( in_array( $slug, $this->term_slugs( $taxonomy ), true ), in_array( $slug, $no, true ) );
	}

	public function count( string $field ): ?int {
		return Tri_State::count( get_post_meta( $this->id(), '_' . $field, true ) );
	}

	/** Total tables: the explicit total, else the sum of known per-size counts. */
	public function total_tables(): ?int {
		$total = $this->count( 'number-of-tables' );
		if ( null !== $total ) {
			return $total;
		}
		$sum = null;
		foreach ( Pool_Schema::COUNT_FIELDS as $key => $def ) {
			if ( 'number-of-tables' === $key ) {
				continue;
			}
			$c = $this->count( $key );
			if ( null !== $c ) {
				$sum = (int) $sum + $c;
			}
		}
		return $sum ?: null;
	}

	public function size_slugs(): array {
		return $this->term_slugs( 'table-size' );
	}

	public function primary_type(): string {
		$names = $this->term_names( Pool_Schema::TAX_VENUE_TYPE );
		return $names[0] ?? '';
	}

	public function status(): string {
		$s = $this->field( 'venue-status' );
		return isset( Pool_Schema::VENUE_STATUSES[ $s ] ) ? $s : '';
	}

	/** @return array<int,array{0:int,1:int}> */
	public function hour_ranges(): array {
		self::prime( [ $this->id() ] );
		return array_map( static fn( $r ) => [ (int) $r->start, (int) $r->end ], self::$hours[ $this->id() ] ?? [] );
	}

	public function timezone(): \DateTimeZone {
		self::prime( [ $this->id() ] );
		$tz = self::$hours[ $this->id() ][0]->timezone ?? '';
		try {
			return new \DateTimeZone( $tz ?: wp_timezone_string() );
		} catch ( \Exception $e ) {
			return wp_timezone();
		}
	}

	/** @return array{state:string,label:string} */
	public function open_status( ?\DateTimeImmutable $now = null ): array {
		$now = ( $now ?? new \DateTimeImmutable( 'now' ) )->setTimezone( $this->timezone() );
		return Format::listing_open_status( $this->hour_ranges(), $this->status(), $now );
	}

	/**
	 * What the browser needs to work out open/closed itself, so cached pages stay right.
	 *
	 * @return array{tz:string,r:array<int,array{0:int,1:int}>,s:string}
	 */
	public function hours_data(): array {
		return [ 'tz' => $this->timezone()->getName(), 'r' => $this->hour_ranges(), 's' => $this->status() ?: 'open' ];
	}

	public function is_claimed(): bool {
		return (bool) get_post_meta( $this->id(), '_claimed', true );
	}

	/** Real uploaded cover/gallery photo URL, never the branded placeholder. */
	public function photo( string $size = 'large' ): string {
		foreach ( [ '_job_cover', '_job_gallery' ] as $key ) {
			$value = get_post_meta( $this->id(), $key, true );
			$first = is_array( $value ) ? reset( $value ) : $value;
			if ( ! $first ) {
				continue;
			}
			if ( is_numeric( $first ) ) {
				$src = wp_get_attachment_image_url( (int) $first, $size );
				if ( $src ) {
					return $src;
				}
			} elseif ( is_string( $first ) && filter_var( $first, FILTER_VALIDATE_URL ) ) {
				return $first;
			}
		}
		return '';
	}

	/** Legitimate review data from My Listing reviews; null when there are none. */
	public function rating(): ?array {
		$count = (int) get_comments( [ 'post_id' => $this->id(), 'status' => 'approve', 'type' => 'comment', 'count' => true, 'parent' => 0 ] );
		$avg = (float) get_post_meta( $this->id(), '_case27_average_rating', true );
		if ( $count < 1 || $avg <= 0 ) {
			return null;
		}
		// My Listing stores averages on a 10-point scale; present on 5 stars.
		$mode = 10;
		return [ 'value' => round( $avg / ( $mode / 5 ), 1 ), 'best' => 5, 'count' => $count ];
	}
}
