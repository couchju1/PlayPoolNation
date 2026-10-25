<?php
/**
 * When a venue page has enough facts to be indexed (Venue::is_indexable()).
 * Thin venue pages would compete with their city page in search with less to
 * offer, so they stay noindex, follow and out of sitemap.xml until they have
 * these, and are indexed automatically once they do.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Index_Rule {

	/** How many of the three table facts (count, sizes, brands) must be known, with a recorded source. */
	public const MIN_TABLE_FACTS = 2;

	/**
	 * What a venue still needs before its page is indexed; empty when it is ready.
	 *
	 * @return string[]
	 */
	public static function missing( bool $address, bool $phone_or_website, bool $hours, int $table_facts ): array {
		return array_keys( array_filter( [
			'address'           => ! $address,
			'phone or website'  => ! $phone_or_website,
			'hours'             => ! $hours,
			'table facts'       => $table_facts < self::MIN_TABLE_FACTS,
		] ) );
	}
}
