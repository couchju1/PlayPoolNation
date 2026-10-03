<?php
/**
 * Single source of truth for PlayPoolNation's structured pool data.
 *
 * Venues are My Listing `job_listing` posts of listing type `place`. Filterable
 * attributes are taxonomies (indexed by WordPress, cheap to query); counts and
 * free text are post meta stored by My Listing fields as `_<field-key>`.
 *
 * Term lists here are seeds, not closed lists: admins can add terms (e.g. new
 * table manufacturers) in wp-admin and everything keeps working.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Pool_Schema {

	public const POST_TYPE       = 'job_listing';
	public const VENUE_TYPE      = 'place';
	public const TOURNAMENT_TYPE = 'tournament';
	public const LEAGUE_TYPE     = 'league';

	public const TAX_VENUE_TYPE = 'job_listing_category';
	public const TAX_AMENITY    = 'case27_job_listing_tags';
	public const TAX_REGION     = 'region';

	/**
	 * Custom taxonomies registered through My Listing's custom-taxonomy setting.
	 * `field` is the My Listing term-select field key used in forms and filters.
	 */
	public const TAXONOMIES = [
		'table-size'   => [
			'label' => 'Table Sizes',
			'field' => 'table_sizes',
			'terms' => [ '7-foot' => "7' tables", '8-foot' => "8' tables", '9-foot' => "9' tables", 'snooker' => 'Snooker tables', 'carom' => 'Carom tables' ],
		],
		'table-brand'  => [
			'label' => 'Table Brands',
			'field' => 'table_brands',
			'terms' => [ 'diamond' => 'Diamond', 'valley' => 'Valley', 'brunswick' => 'Brunswick', 'rasson' => 'Rasson', 'predator' => 'Predator', 'olhausen' => 'Olhausen', 'gandy' => 'Gandy', 'other-brand' => 'Other brand' ],
		],
		'pool-pricing' => [
			'label' => 'Pool Pricing',
			'field' => 'pool_pricing',
			'terms' => [ 'coin-op' => 'Coin-op', 'hourly' => 'Hourly', 'per-game' => 'Per game', 'free-pool' => 'Free pool' ],
		],
		'league-org'   => [
			'label' => 'League Organizations',
			'field' => 'league_orgs',
			'terms' => [ 'apa' => 'APA', 'bca' => 'BCA', 'usapl' => 'USAPL', 'tap' => 'TAP', 'vnea' => 'VNEA', 'acs' => 'ACS', 'local-league' => 'Local league' ],
		],
		'pool-play'    => [
			'label' => 'Organized Play',
			'field' => 'pool_play',
			'terms' => [ 'leagues' => 'Leagues', 'tournaments' => 'Tournaments' ],
		],
		'game-type'    => [
			'label' => 'Game Types',
			'field' => 'game_types',
			'terms' => [ '8-ball' => '8-Ball', '9-ball' => '9-Ball', '10-ball' => '10-Ball', 'one-pocket' => 'One Pocket', 'straight-pool' => 'Straight Pool', 'banks' => 'Banks', 'snooker' => 'Snooker', 'other-game' => 'Other' ],
		],
	];

	/** Venue types (existing `job_listing_category` taxonomy). Keys are slugs. */
	public const VENUE_TYPES = [
		'pool-halls'            => 'Pool Hall',
		'billiards-lounge'      => 'Billiards Lounge',
		'bars-with-pool-tables' => 'Bar',
		'sports-bar'            => 'Sports Bar',
		'bowling-center'        => 'Bowling Center',
		'recreation'            => 'Recreation Center',
		'private-clubs'         => 'Private Club',
		'other-venue'           => 'Other',
	];

	/** Amenities (existing tags taxonomy), grouped for display. Keys are slugs. */
	public const AMENITY_GROUPS = [
		'Drinks & food' => [ 'full-bar' => 'Full Bar', 'beer-wine' => 'Beer & Wine', 'food' => 'Food', 'non-alcoholic' => 'Non-Alcoholic Options' ],
		'Ages'          => [ 'all-ages' => 'All Ages', '18-plus' => '18+', '21-plus' => '21+' ],
		'Pool services' => [ 'pro-shop' => 'Pro Shop', 'cue-repair' => 'Cue Repair', 'cue-rental' => 'Cue Rental', 'lessons' => 'Lessons', 'reservations' => 'Reservations' ],
		'Other games'   => [ 'darts' => 'Darts', 'arcade' => 'Arcade' ],
		'Comfort'       => [ 'tvs' => 'TVs', 'outdoor-seating' => 'Outdoor Seating', 'private-rooms' => 'Private Rooms', 'wi-fi' => 'Wi-Fi' ],
		'Access'        => [ 'parking' => 'Parking', 'accessible' => 'Accessible' ],
		'Smoking'       => [ 'smoking-allowed' => 'Smoking Allowed', 'non-smoking' => 'Non-Smoking' ],
	];

	/** Table counts (My Listing number fields). Empty = unknown, 0 = none. */
	public const COUNT_FIELDS = [
		'number-of-tables' => [ 'label' => 'Total tables', 'size' => null ],
		'tables-7ft'       => [ 'label' => "7' tables", 'size' => '7-foot' ],
		'tables-8ft'       => [ 'label' => "8' tables", 'size' => '8-foot' ],
		'tables-9ft'       => [ 'label' => "9' tables", 'size' => '9-foot' ],
		'tables-snooker'   => [ 'label' => 'Snooker tables', 'size' => 'snooker' ],
		'tables-carom'     => [ 'label' => 'Carom tables', 'size' => 'carom' ],
		'tables-other'     => [ 'label' => 'Other tables', 'size' => null ],
	];

	/** Free-text equipment and pricing fields. */
	public const TEXT_FIELDS = [
		'table-models'    => 'Table models',
		'table-cloth'     => 'Cloth',
		'table-balls'     => 'Balls',
		'equipment-notes' => 'Equipment notes',
		'pricing-notes'   => 'Pricing notes',
	];

	/** Prices in USD (number fields). */
	public const PRICE_FIELDS = [
		'hourly-rate' => 'Per hour',
		'game-price'  => 'Per game',
	];

	public const VENUE_STATUSES = [
		'open'                 => 'Open',
		'temporarily-closed'   => 'Temporarily closed',
		'permanently-closed'   => 'Permanently closed',
	];

	public const LEAGUE_DAYS = [ 'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday' ];

	public const LEAGUE_STATUSES = [ 'active' => 'Active', 'forming' => 'Forming', 'off-season' => 'Off-season', 'inactive' => 'Inactive' ];

	public const TOURNAMENT_STATUSES = [ 'scheduled' => 'Scheduled', 'cancelled' => 'Cancelled', 'postponed' => 'Postponed', 'completed' => 'Completed' ];

	/** Relation field keys (My Listing related-listing fields on the child type). */
	public const TOURNAMENT_VENUE_FIELD = 'event-place-relation';
	public const LEAGUE_VENUE_FIELD     = 'league-venue';

	public static function all_amenities(): array {
		return array_merge( ...array_values( self::AMENITY_GROUPS ) );
	}

	/** Taxonomies whose terms carry Yes/No/Unknown on venues. */
	public static function tri_state_taxonomies(): array {
		return [
			'table-size'      => self::TAXONOMIES['table-size']['label'],
			'table-brand'     => self::TAXONOMIES['table-brand']['label'],
			'pool-pricing'    => self::TAXONOMIES['pool-pricing']['label'],
			self::TAX_AMENITY => 'Amenities',
		];
	}
}
