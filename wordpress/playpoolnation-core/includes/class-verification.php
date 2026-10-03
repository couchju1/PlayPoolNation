<?php
/**
 * Venue verification status.
 *
 * Meta: _ppn_verification_status (unverified|community|owner|admin),
 *       _ppn_verified_at (Y-m-d), _ppn_verification_source (short text).
 *
 * A badge is only shown for a non-"unverified" status that has a date.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Verification {

	public const STATUSES = [
		'unverified' => 'Unverified',
		'community'  => 'Community Verified',
		'owner'      => 'Owner Verified',
		'admin'      => 'Admin Verified',
	];

	private const ORDER = [ 'unverified' => 0, 'community' => 1, 'owner' => 2, 'admin' => 3 ];

	public static function boot(): void {
		// My Listing marks a listing `_claimed` = 1 when an admin approves a claim.
		add_action( 'added_post_meta', [ __CLASS__, 'on_claimed_meta' ], 10, 4 );
		add_action( 'updated_post_meta', [ __CLASS__, 'on_claimed_meta' ], 10, 4 );
	}

	/** @param mixed $value */
	public static function on_claimed_meta( $meta_id, $post_id, $key, $value ): void {
		if ( '_claimed' !== $key || ! (int) $value || Pool_Schema::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		self::raise( (int) $post_id, 'owner', 'Claim approved' );
	}

	/** @return array{status:string,label:string,date:string,source:string} */
	public static function get( int $post_id ): array {
		$status = (string) get_post_meta( $post_id, '_ppn_verification_status', true );
		if ( ! isset( self::STATUSES[ $status ] ) ) {
			$status = 'unverified';
		}
		return [
			'status' => $status,
			'label'  => self::STATUSES[ $status ],
			'date'   => (string) get_post_meta( $post_id, '_ppn_verified_at', true ),
			'source' => (string) get_post_meta( $post_id, '_ppn_verification_source', true ),
		];
	}

	public static function set( int $post_id, string $status, string $source = '', ?string $date = null ): void {
		if ( ! isset( self::STATUSES[ $status ] ) ) {
			return;
		}
		update_post_meta( $post_id, '_ppn_verification_status', $status );
		if ( 'unverified' === $status ) {
			delete_post_meta( $post_id, '_ppn_verified_at' );
			delete_post_meta( $post_id, '_ppn_verification_source' );
			return;
		}
		$date = $date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : current_time( 'Y-m-d' );
		update_post_meta( $post_id, '_ppn_verified_at', $date );
		update_post_meta( $post_id, '_ppn_verification_source', sanitize_text_field( $source ) );
	}

	/** Set a status only if it is at least as strong as the current one (dates refresh). */
	public static function raise( int $post_id, string $status, string $source ): void {
		$current = self::get( $post_id )['status'];
		if ( ( self::ORDER[ $status ] ?? 0 ) >= ( self::ORDER[ $current ] ?? 0 ) ) {
			self::set( $post_id, $status, $source );
		}
	}

	public static function has_badge( array $v ): bool {
		return 'unverified' !== $v['status'] && '' !== $v['date'];
	}
}
