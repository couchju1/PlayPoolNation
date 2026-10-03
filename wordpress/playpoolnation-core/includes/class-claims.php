<?php
/**
 * Claims use My Listing's built-in claim workflow (claim post type, admin approval,
 * listing author reassignment). This class only adds the claim link and keeps
 * approval mandatory; the Owner Verified status is set in Verification.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Claims {

	public static function boot(): void {
		// Never let a claim be approved automatically.
		add_filter( 'pre_option_case27_claim_requires_approval', static fn() => '1' );
	}

	/** URL of the claim form for a venue, or '' when it cannot be claimed. */
	public static function claim_url( int $venue_id ): string {
		if ( ! function_exists( 'mylisting_get_setting' ) || ! mylisting_get_setting( 'claims_enabled' ) ) {
			return '';
		}
		if ( get_post_meta( $venue_id, '_claimed', true ) ) {
			return '';
		}
		if ( class_exists( '\MyListing\Src\Listing' ) ) {
			$listing = \MyListing\Src\Listing::get( $venue_id );
			if ( ! $listing || ! $listing->is_claimable() ) {
				return '';
			}
		}
		$page = (int) mylisting_get_setting( 'claims_page_id' );
		return $page ? add_query_arg( 'listing_id', $venue_id, get_permalink( $page ) ) : '';
	}
}
