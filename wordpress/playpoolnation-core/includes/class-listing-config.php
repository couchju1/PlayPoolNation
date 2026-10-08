<?php
/**
 * My Listing listing-type configuration (fields, search filters, page layout,
 * preview cards, settings) for venues, tournaments and leagues.
 *
 * My Listing stores each part as a serialized array in post meta on the
 * `case27_listing_type` post. Every change here is idempotent, and the previous
 * value is kept in `_ppn_backup_<meta>` the first time it is replaced.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Listing_Config {

	public const META = [
		'fields'   => 'case27_listing_type_fields',
		'single'   => 'case27_listing_type_single_page_options',
		'result'   => 'case27_listing_type_result_template',
		'search'   => 'case27_listing_type_search_page',
		'settings' => 'case27_listing_type_settings_page',
	];

	/* ------------------------------------------------------------ storage */

	public static function type_id( string $slug ): int {
		$post = get_page_by_path( $slug, OBJECT, 'case27_listing_type' );
		return $post ? (int) $post->ID : 0;
	}

	public static function get( int $type_id, string $part ): array {
		$raw = get_post_meta( $type_id, self::META[ $part ], true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$value = is_string( $raw ) && '' !== $raw ? @unserialize( $raw, [ 'allowed_classes' => false ] ) : false; // phpcs:ignore
		if ( false === $value && is_string( $raw ) ) {
			$value = json_decode( $raw, true );
		}
		return is_array( $value ) ? $value : [];
	}

	public static function set( int $type_id, string $part, array $value ): void {
		$key = self::META[ $part ];
		if ( '' === (string) get_post_meta( $type_id, '_ppn_backup_' . $key, true ) ) {
			update_post_meta( $type_id, '_ppn_backup_' . $key, get_post_meta( $type_id, $key, true ) );
		}
		// My Listing stores these as serialized strings; keep the same format.
		update_post_meta( $type_id, $key, wp_slash( serialize( $value ) ) ); // phpcs:ignore
	}

	/* ------------------------------------------------------- field builders */

	private static function base( string $type, string $slug, string $label, int $priority, array $extra = [] ): array {
		return array_merge( [
			'type'                => $type,
			'slug'                => $slug,
			'default'             => '',
			'priority'            => $priority,
			'is_custom'           => true,
			'label'               => $label,
			'default_label'       => 'Custom Field',
			'placeholder'         => '',
			'description'         => '',
			'required'            => false,
			'show_in_admin'       => true,
			'show_in_submit_form' => true,
			'show_in_compare'     => true,
			'conditional_logic'   => false,
			'conditions'          => [ [ [ 'key' => '__listing_package', 'compare' => '==', 'value' => '' ] ] ],
		], $extra );
	}

	public static function heading( string $slug, string $label, int $priority ): array {
		return self::base( 'form-heading', $slug, $label, $priority, [ 'show_in_compare' => false ] );
	}

	public static function number( string $slug, string $label, int $priority, string $description = '', $step = 1 ): array {
		return self::base( 'number', $slug, $label, $priority, [ 'description' => $description, 'min' => '0', 'max' => '', 'step' => $step, 'content_lock' => false ] );
	}

	public static function text( string $slug, string $label, int $priority, string $placeholder = '', string $type = 'text' ): array {
		return self::base( $type, $slug, $label, $priority, [ 'placeholder' => $placeholder, 'content_lock' => false, 'minlength' => '', 'maxlength' => '' ] );
	}

	public static function select( string $slug, string $label, int $priority, array $options, bool $required = false ): array {
		return self::base( 'select', $slug, $label, $priority, [ 'options' => $options, 'required' => $required ] );
	}

	public static function terms( string $slug, string $label, string $taxonomy, int $priority, bool $in_form = true, bool $in_admin = false ): array {
		return self::base( 'term-select', $slug, $label, $priority, [
			'is_custom'             => false,
			'default_label'         => $label,
			'taxonomy'              => $taxonomy,
			'terms-template'        => 'multiselect',
			'create_tag'            => false,
			'selection_limit'       => '',
			'enable_package_limits' => false,
			'package_limits'        => [],
			'show_in_submit_form'   => $in_form,
			'show_in_admin'         => $in_admin,
		] );
	}

	public static function relation( string $slug, string $label, int $priority, bool $required = true, string $relation_type = 'belongs_to_one', int $limit = 1 ): array {
		return self::base( 'related-listing', $slug, $label, $priority, [
			'listing_type'          => [ Pool_Schema::VENUE_TYPE ],
			'relation_type'         => $relation_type,
			'author_restriction'    => 'any',
			'status_restriction'    => [],
			'selection_limit'       => $limit,
			'enable_package_limits' => false,
			'package_limits'        => [],
			'required'              => $required,
		] );
	}

	/* --------------------------------------------------------- facets */

	private static function facet( string $type, string $label, array $extra = [] ): array {
		return array_merge( [ 'type' => $type, 'label' => $label, 'default_label' => '', 'is_primary' => false, 'options' => [] ], $extra );
	}

	private static function checkboxes( string $field, string $label, string $behavior = 'any' ): array {
		return self::facet( 'checkboxes', $label, [ 'show_field' => $field, 'form' => 'advanced', 'count' => '', 'order_by' => 'count', 'order' => 'DESC', 'hide_empty' => 1, 'multiselect' => 1, 'behavior' => $behavior ] );
	}

	private static function location_facets( string $label = 'Where do you want to play?' ): array {
		return [
			self::facet( 'location', $label ),
			self::facet( 'proximity', 'Distance', [ 'units' => 'imperial', 'step' => 1, 'min' => 1, 'max' => '100', 'default' => '25' ] ),
		];
	}

	/* --------------------------------------------------------- venues */

	public static function venue_fields( array $fields ): array {
		foreach ( [ 'table-type', 'league-nights', 'tournaments-hosted', 'food-drink', 'select_products', 'event-place-relation' ] as $old ) {
			unset( $fields[ $old ] );
		}
		if ( isset( $fields['job_category'] ) ) {
			$fields['job_category']['label'] = 'Venue type';
			$fields['job_category']['placeholder'] = 'Choose a venue type';
		}
		if ( isset( $fields['job_tags'] ) ) {
			$fields['job_tags']['label'] = 'Amenities';
			$fields['job_tags']['placeholder'] = 'Choose amenities';
		}
		if ( isset( $fields['price_range'] ) ) {
			$fields['price_range']['label'] = 'Price level';
		}

		$p = 40;
		$add = [
			self::heading( 'pool-tables-heading', 'Pool tables', $p++ ),
			self::number( 'number-of-tables', 'Total pool tables', $p++, 'Leave blank if you are not sure.' ),
			self::number( 'tables-7ft', "7' tables", $p++, 'Bar-box size. Leave blank if unknown, 0 if none.' ),
			self::number( 'tables-8ft', "8' tables", $p++, 'Leave blank if unknown, 0 if none.' ),
			self::number( 'tables-9ft', "9' tables", $p++, 'Regulation size. Leave blank if unknown, 0 if none.' ),
			self::number( 'tables-snooker', 'Snooker tables', $p++, 'Leave blank if unknown, 0 if none.' ),
			self::number( 'tables-carom', 'Carom tables', $p++, 'Leave blank if unknown, 0 if none.' ),
			self::number( 'tables-other', 'Other tables', $p++, 'Bumper pool and other tables.' ),
			self::terms( 'table_sizes', 'Table sizes', 'table-size', $p++ ),
			self::terms( 'table_brands', 'Table brands', 'table-brand', $p++ ),
			self::text( 'table-models', 'Table models', $p++, 'e.g. Diamond Pro-Am' ),
			self::text( 'table-cloth', 'Cloth', $p++, 'e.g. Simonis 860' ),
			self::text( 'table-balls', 'Balls', $p++, 'e.g. Aramith Tournament' ),
			self::text( 'equipment-notes', 'Equipment notes', $p++, '', 'textarea' ),
			self::heading( 'pool-pricing-heading', 'Pool pricing', $p++ ),
			self::terms( 'pool_pricing', 'How players pay', 'pool-pricing', $p++ ),
			self::number( 'hourly-rate', 'Price per hour (USD)', $p++, '', 0.01 ),
			self::number( 'game-price', 'Price per game (USD)', $p++, '', 0.01 ),
			self::text( 'pricing-notes', 'Pricing notes', $p++, 'e.g. Half price before 6 PM' ),
			self::select( 'venue-status', 'Venue status', $p++, [ '' => 'Open' ] + array_diff_key( Pool_Schema::VENUE_STATUSES, [ 'open' => 1 ] ) ),
			// Derived from leagues/tournaments; present so search filters can use them.
			self::terms( 'pool_play', 'Organized play', 'pool-play', $p++, false, false ),
			self::terms( 'league_orgs', 'League organizations', 'league-org', $p++, false, false ),
			// Maintained by the plugin for preview cards.
			array_merge( self::text( 'card-tables', 'Card: tables', $p++ ), [ 'show_in_admin' => false, 'show_in_submit_form' => false, 'show_in_compare' => false ] ),
			array_merge( self::text( 'card-equipment', 'Card: equipment', $p++ ), [ 'show_in_admin' => false, 'show_in_submit_form' => false, 'show_in_compare' => false ] ),
		];
		foreach ( $add as $field ) {
			$existing = $fields[ $field['slug'] ] ?? [];
			// Keep any admin-made tweaks to existing fields, but enforce structural keys.
			$fields[ $field['slug'] ] = array_merge( $existing, $field );
		}
		return $fields;
	}

	public static function venue_search( array $search ): array {
		$search['advanced']['facets'] = array_merge(
			self::location_facets(),
			[
				self::facet( 'wp-search', 'Venue name' ),
				self::facet( 'open-now', 'Open now' ),
				self::checkboxes( 'job_category', 'Type of place' ),
				self::checkboxes( 'table_sizes', 'Table sizes' ),
				self::checkboxes( 'table_brands', 'Table brand' ),
				self::checkboxes( 'pool_pricing', 'How you pay' ),
				self::checkboxes( 'pool_play', 'Organized play' ),
				self::checkboxes( 'league_orgs', 'Leagues' ),
				self::checkboxes( 'job_tags', 'Amenities', 'all' ),
				self::facet( 'order', 'Sort by' ),
			]
		);
		$search['basic']['facets'] = [ self::facet( 'location', 'Where do you want to play?' ), self::facet( 'wp-search', 'Venue name (optional)' ) ];
		$search['explore_tabs'] = [ [ 'type' => 'search-form', 'label' => 'Filters', 'icon' => 'mi filter_list', 'orderby' => '', 'order' => '', 'hide_empty' => false ] ];
		$search['order'] = [ 'options' => Explore_Sort::options(), 'default' => Explore_Sort::DEFAULT_KEY ];
		return $search;
	}

	public static function venue_single( array $single ): array {
		$block = static fn( string $type, string $title, string $icon, array $extra = [] ) => array_merge( [ 'type' => $type, 'title' => $title, 'icon' => $icon, 'class' => '', 'id' => '' ], $extra );
		$raw = static fn( string $title, string $shortcode, string $icon, string $class ) => $block( 'raw', $title, $icon, [ 'content' => $shortcode, 'class' => $class, 'conditional_logic' => false, 'conditions' => [] ] );

		$single['menu_items'] = [
			[
				'page'       => 'main',
				'label'      => 'Overview',
				'label_l10n' => [ 'locale' => 'en_US' ],
				'slug'       => '',
				'template'   => 'two-columns',
				'layout'     => [
					$raw( '', '[ppn_venue_summary]', '', 'ppn-block-summary' ),
					$raw( 'Pool tables', '[ppn_pool_tables]', 'mi grid_on', 'ppn-block-tables' ),
					$block( 'text', 'About', 'mi view_headline', [ 'show_field' => 'job_description' ] ),
					$raw( 'Amenities', '[ppn_amenities]', 'mi local_bar', 'ppn-block-amenities' ),
					$raw( 'Leagues', '[ppn_leagues]', 'mi groups', 'ppn-block-leagues' ),
					$raw( 'Upcoming events', '[ppn_tournaments]', 'mi emoji_events', 'ppn-block-tournaments' ),
					$raw( 'Pool instructors', '[ppn_instructors_here]', 'mi school', 'ppn-block-instructors' ),
					$block( 'gallery', 'Photos', 'mi insert_photo', [ 'gallery_type' => 'carousel', 'show_field' => 'job_gallery' ] ),
				],
				'sidebar'    => [
					$block( 'work_hours', 'Hours', 'mi access_time', [ 'show_field' => 'work_hours' ] ),
					$block( 'location', 'Location', 'mi map', [ 'display_type' => 'interactive', 'scale_image' => false, 'map_skin' => 'skin3', 'map_zoom' => 14, 'show_field' => 'job_location' ] ),
					$raw( 'Listing details', '[ppn_trust]', 'mi verified', 'ppn-block-trust' ),
				],
			],
			[
				'page'       => 'comments',
				'label'      => 'Reviews',
				'label_l10n' => [ 'locale' => 'en_US' ],
				'slug'       => 'reviews',
				'layout'     => [],
			],
		];
		$single['quick_actions'] = array_map(
			static fn( $a ) => array_merge( [ 'icon' => '', 'class' => '', 'id' => '', 'label_l10n' => [ 'locale' => 'en_US' ] ], $a ),
			[
				[ 'action' => 'get-directions', 'label' => 'Directions', 'icon' => 'icon-location-pin-add-2' ],
				[ 'action' => 'call-now', 'label' => 'Call', 'icon' => 'icon-phone-outgoing' ],
				[ 'action' => 'visit-website', 'label' => 'Website', 'icon' => 'icon-globe' ],
				[ 'action' => 'bookmark', 'label' => 'Save', 'icon' => 'mi favorite_border' ],
				[ 'action' => 'share', 'label' => 'Share', 'icon' => 'mi share' ],
				[ 'action' => 'leave-review', 'label' => 'Write a review', 'icon' => 'mi rate_review' ],
				[ 'action' => 'claim-listing', 'label' => 'Claim this venue', 'icon' => 'mi verified_user' ],
			]
		);
		$single['cover_details'] = [];
		$single['cover_actions'] = [];
		$single['buttons'] = [
			[ 'action' => 'display-rating', 'custom_field' => '', 'label' => 'Rating', 'label_l10n' => [ 'locale' => 'en_US' ], 'style' => 'secondary', 'icon' => '' ],
			[ 'action' => 'bookmark', 'custom_field' => '', 'label' => 'Save', 'label_l10n' => [ 'locale' => 'en_US' ], 'style' => 'outlined', 'icon' => 'mi favorite_border' ],
			[ 'action' => 'share', 'custom_field' => '', 'label' => '', 'label_l10n' => [ 'locale' => 'en_US' ], 'style' => 'outlined', 'icon' => 'mi share' ],
		];
		$single['similar_listings'] = array_merge( (array) ( $single['similar_listings'] ?? [] ), [ 'enabled' => true, 'match_by_type' => true, 'match_by_category' => false, 'match_by_tags' => false, 'match_by_region' => false, 'listing_count' => 3, 'orderby' => 'proximity', 'max_proximity' => 25 ] );
		return $single;
	}

	public static function venue_result( array $result ): array {
		$result['template'] = 'alternate';
		$result['buttons'] = [ [ 'label' => '[[:reviews-stars]]' ], [ 'label' => '[[work_hours]]' ] ];
		$result['info_fields'] = [
			[ 'label' => '[[card-tables]]', 'icon' => 'mi grid_on' ],
			[ 'label' => '[[card-equipment]]', 'icon' => 'mi straighten' ],
			[ 'label' => '[[location.short]]', 'icon' => 'icon-location-pin-add-2' ],
		];
		return $result;
	}

	public static function venue_settings( array $settings ): array {
		$settings['singular_name'] = 'Venue';
		$settings['plural_name'] = 'Venues';
		$settings['permalink'] = 'place';
		$settings['packages']['enabled'] = false;
		$settings['seo']['markup'] = []; // Replaced by Schema_Org.
		$settings['claim_form']['used'] = self::claim_fields();
		return $settings;
	}

	/** Extra questions on My Listing's claim form (account email is collected by the account step). */
	public static function claim_fields(): array {
		$p = 1;
		return [
			array_merge( self::text( 'claimant_name', 'Your full name', $p++ ), [ 'required' => true ] ),
			self::select( 'claimant_role', 'Your role at the venue', $p++, [ 'owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Authorized staff' ], true ),
			self::text( 'claimant_phone', 'Phone number', $p++, 'So we can confirm with the venue' ),
			array_merge( self::text( 'claimant_verification', 'How can we confirm you manage this venue?', $p++, 'e.g. email from the venue domain, or call the venue and ask for you', 'textarea' ), [ 'required' => true ] ),
			self::text( 'claimant_message', 'Anything else?', $p++, '', 'textarea' ),
		];
	}

	/* ----------------------------------------------------- tournaments */

	public static function tournament_fields( array $fields ): array {
		$keep = [ 'job_title', 'job_description', 'job_cover', 'job_gallery', 'job_location', 'event-date', Pool_Schema::TOURNAMENT_VENUE_FIELD ];
		$fields = array_intersect_key( $fields, array_flip( $keep ) );
		if ( isset( $fields['job_title'] ) ) {
			$fields['job_title']['label'] = 'Event name';
		}
		if ( isset( $fields['job_description'] ) ) {
			$fields['job_description']['label'] = 'Description';
			$fields['job_description']['required'] = false;
		}
		if ( isset( $fields['job_location'] ) ) {
			// Location is copied from the venue automatically.
			$fields['job_location']['show_in_submit_form'] = false;
		}
		if ( isset( $fields['event-date'] ) ) {
			$fields['event-date']['label'] = 'Date and start time';
			$fields['event-date']['required'] = true;
			$fields['event-date']['allow_recurrence'] = true;
			$fields['event-date']['enable_timepicker'] = true;
		}
		$p = 20;
		$add = [
			self::relation( Pool_Schema::TOURNAMENT_VENUE_FIELD, 'Venue', $p++ ),
			array_merge( self::terms( 'event_types', 'Type of event', Pool_Schema::TAX_EVENT_TYPE, $p++, true, true ), [ 'terms-template' => 'single-select', 'required' => true ] ),
			self::terms( 'game_types', 'Game', 'game-type', $p++, true, true ),
			self::select( 'tournament-table-size', 'Table size', $p++, [ '' => 'Not specified', '7-foot' => "7' tables", '8-foot' => "8' tables", '9-foot' => "9' tables", 'mixed' => 'Mixed sizes' ] ),
			self::number( 'entry-fee', 'Entry fee (USD)', $p++, '', 0.01 ),
			self::number( 'added-money', 'Added money (USD)', $p++, '', 0.01 ),
			self::text( 'skill-restrictions', 'Eligibility / skill restrictions', $p++, 'e.g. Open, or APA 5 and under' ),
			self::text( 'registration-info', 'How to register', $p++, '', 'textarea' ),
			array_merge( self::text( 'job_website', 'Registration link', $p++, 'https://', 'url' ), [ 'is_custom' => false ] ),
			self::select( 'tournament-status', 'Status', $p++, Pool_Schema::TOURNAMENT_STATUSES ),
		];
		foreach ( $add as $field ) {
			$fields[ $field['slug'] ] = array_merge( $fields[ $field['slug'] ] ?? [], $field );
		}
		return $fields;
	}

	public static function tournament_search( array $search ): array {
		$search['advanced']['facets'] = array_merge(
			[ self::facet( 'recurring-date', 'When', [ 'show_field' => 'event-date', 'datepicker' => true, 'timepicker' => false, 'ranges' => [ [ 'key' => 'all', 'label' => 'All upcoming' ], [ 'key' => 'this-week', 'label' => 'This week' ], [ 'key' => 'this-weekend', 'label' => 'This weekend' ], [ 'key' => 'next-week', 'label' => 'Next week' ] ] ] ) ],
			self::location_facets( 'Where do you want to play?' ),
			[ self::checkboxes( 'event_types', 'Type of event' ), self::checkboxes( 'game_types', 'Game' ), self::facet( 'wp-search', 'Event name' ), self::facet( 'order', 'Sort by' ) ]
		);
		$search['basic']['facets'] = [ self::facet( 'location', 'Where do you want to play?' ) ];
		$search['order']['default'] = $search['order']['options'][0]['key'] ?? 'order-by-date';
		$search['explore_tabs'] = [ [ 'type' => 'search-form', 'label' => 'Filters', 'icon' => 'mi filter_list', 'orderby' => '', 'order' => '', 'hide_empty' => false ] ];
		return $search;
	}

	public static function tournament_single( array $single ): array {
		$single['menu_items'] = [ [
			'page'       => 'main',
			'label'      => 'Details',
			'label_l10n' => [ 'locale' => 'en_US' ],
			'slug'       => '',
			'template'   => 'two-columns',
			'layout'     => [
				[ 'type' => 'raw', 'title' => 'Event details', 'icon' => 'mi emoji_events', 'class' => 'ppn-block-details', 'id' => '', 'content' => '[ppn_tournament_details]', 'conditional_logic' => false, 'conditions' => [] ],
				[ 'type' => 'text', 'title' => 'About', 'icon' => 'mi view_headline', 'class' => '', 'id' => '', 'show_field' => 'job_description' ],
				[ 'type' => 'location', 'title' => 'Location', 'icon' => 'mi map', 'class' => '', 'id' => '', 'display_type' => 'interactive', 'scale_image' => false, 'map_skin' => 'skin3', 'map_zoom' => 14, 'show_field' => 'job_location' ],
			],
			'sidebar'    => [
				[ 'type' => 'upcoming_dates', 'title' => 'Upcoming dates', 'icon' => 'mi event', 'class' => '', 'id' => '', 'show_field' => 'event-date', 'count' => 5, 'past_count' => 0, 'show_add_to_gcal' => true, 'show_add_to_ical' => true ],
				[ 'type' => 'related_listing', 'title' => 'Venue', 'icon' => 'mi place', 'class' => '', 'id' => '', 'show_field' => Pool_Schema::TOURNAMENT_VENUE_FIELD ],
			],
		] ];
		$single['quick_actions'] = [
			[ 'action' => 'visit-website', 'label' => 'Register / info', 'icon' => 'icon-globe', 'class' => '', 'id' => '', 'label_l10n' => [ 'locale' => 'en_US' ] ],
			[ 'action' => 'get-directions', 'label' => 'Directions', 'icon' => 'icon-location-pin-add-2', 'class' => '', 'id' => '', 'label_l10n' => [ 'locale' => 'en_US' ] ],
			[ 'action' => 'bookmark', 'label' => 'Save', 'icon' => 'mi favorite_border', 'class' => '', 'id' => '', 'label_l10n' => [ 'locale' => 'en_US' ] ],
			[ 'action' => 'share', 'label' => 'Share', 'icon' => 'mi share', 'class' => '', 'id' => '', 'label_l10n' => [ 'locale' => 'en_US' ] ],
		];
		$single['cover_details'] = [];
		$single['buttons'] = [];
		return $single;
	}

	public static function play_settings( array $settings, string $singular, string $plural, string $permalink, string $icon ): array {
		$settings['singular_name'] = $singular;
		$settings['plural_name'] = $plural;
		$settings['permalink'] = $permalink;
		$settings['icon_type'] = 'icon';
		$settings['icon'] = $icon;
		$settings['packages']['enabled'] = false;
		$settings['packages']['used'] = [];
		$settings['reviews']['ratings']['enabled'] = false;
		$settings['seo']['markup'] = [];
		$settings['claim_form']['used'] = [];
		return $settings;
	}

	/* --------------------------------------------------------- leagues */

	public static function league_fields( array $fields ): array {
		$fields = array_intersect_key( $fields, array_flip( [ 'job_title', 'job_description', 'job_location', 'job_cover' ] ) );
		if ( isset( $fields['job_title'] ) ) {
			$fields['job_title']['label'] = 'League name';
		}
		if ( isset( $fields['job_description'] ) ) {
			$fields['job_description']['label'] = 'Notes';
			$fields['job_description']['required'] = false;
		}
		if ( isset( $fields['job_location'] ) ) {
			$fields['job_location']['show_in_submit_form'] = false;
		}
		$p = 20;
		$add = [
			self::relation( Pool_Schema::LEAGUE_VENUE_FIELD, 'Venue', $p++ ),
			self::terms( 'league_orgs', 'Organization', 'league-org', $p++, true, true ),
			self::terms( 'game_types', 'Game', 'game-type', $p++, true, true ),
			self::select( 'league-day', 'Day', $p++, Pool_Schema::LEAGUE_DAYS ),
			self::text( 'league-time', 'Start time', $p++, 'e.g. 7:00 PM' ),
			self::text( 'league-season', 'Season', $p++, 'e.g. Fall 2026' ),
			self::select( 'league-status', 'Status', $p++, Pool_Schema::LEAGUE_STATUSES ),
			array_merge( self::text( 'job_website', 'Sign-up link', $p++, 'https://', 'url' ), [ 'is_custom' => false ] ),
		];
		foreach ( $add as $field ) {
			$fields[ $field['slug'] ] = array_merge( $fields[ $field['slug'] ] ?? [], $field );
		}
		return $fields;
	}

	public static function league_search( array $search ): array {
		$search['advanced']['facets'] = array_merge(
			self::location_facets( 'Where do you want to play?' ),
			[
				self::checkboxes( 'league_orgs', 'Organization' ),
				self::checkboxes( 'game_types', 'Game' ),
				self::facet( 'dropdown', 'Day', [ 'show_field' => 'league-day', 'order_by' => 'name', 'order' => 'ASC', 'hide_empty' => 1, 'multiselect' => false, 'behavior' => 'any' ] ),
				self::facet( 'wp-search', 'League name' ),
				self::facet( 'order', 'Sort by' ),
			]
		);
		$search['basic']['facets'] = [ self::facet( 'location', 'Where do you want to play?' ) ];
		$search['order'] = [
			'options' => [
				[ 'label' => 'Nearby', 'key' => 'nearby', 'ignore_priority' => false, 'is_new' => false, 'clauses' => [ [ 'orderby' => 'proximity', 'order' => 'ASC', 'context' => 'option', 'type' => 'CHAR', 'custom_type' => false ] ], 'notes' => [ 'has-proximity-clause' ] ],
				[ 'label' => 'Name (A to Z)', 'key' => 'a-z', 'ignore_priority' => false, 'is_new' => false, 'clauses' => [ [ 'orderby' => 'title', 'order' => 'ASC', 'context' => 'option', 'type' => 'CHAR', 'custom_type' => false ] ] ],
			],
			'default' => 'a-z',
		];
		$search['explore_tabs'] = [ [ 'type' => 'search-form', 'label' => 'Filters', 'icon' => 'mi filter_list', 'orderby' => '', 'order' => '', 'hide_empty' => false ] ];
		return $search;
	}

	public static function league_single( array $single ): array {
		$single['menu_items'] = [ [
			'page'       => 'main',
			'label'      => 'Details',
			'label_l10n' => [ 'locale' => 'en_US' ],
			'slug'       => '',
			'template'   => 'two-columns',
			'layout'     => [
				[ 'type' => 'raw', 'title' => 'League details', 'icon' => 'mi groups', 'class' => 'ppn-block-details', 'id' => '', 'content' => '[ppn_league_details]', 'conditional_logic' => false, 'conditions' => [] ],
				[ 'type' => 'text', 'title' => 'Notes', 'icon' => 'mi view_headline', 'class' => '', 'id' => '', 'show_field' => 'job_description' ],
			],
			'sidebar'    => [
				[ 'type' => 'related_listing', 'title' => 'Plays at', 'icon' => 'mi place', 'class' => '', 'id' => '', 'show_field' => Pool_Schema::LEAGUE_VENUE_FIELD ],
				[ 'type' => 'location', 'title' => 'Location', 'icon' => 'mi map', 'class' => '', 'id' => '', 'display_type' => 'interactive', 'scale_image' => false, 'map_skin' => 'skin3', 'map_zoom' => 14, 'show_field' => 'job_location' ],
			],
		] ];
		$single['quick_actions'] = [
			[ 'action' => 'visit-website', 'label' => 'Sign up', 'icon' => 'icon-globe', 'class' => '', 'id' => '', 'label_l10n' => [ 'locale' => 'en_US' ] ],
			[ 'action' => 'bookmark', 'label' => 'Save', 'icon' => 'mi favorite_border', 'class' => '', 'id' => '', 'label_l10n' => [ 'locale' => 'en_US' ] ],
			[ 'action' => 'share', 'label' => 'Share', 'icon' => 'mi share', 'class' => '', 'id' => '', 'label_l10n' => [ 'locale' => 'en_US' ] ],
		];
		$single['cover_details'] = [];
		$single['buttons'] = [];
		return $single;
	}

	public static function play_result( array $result, string $when_field ): array {
		$result['template'] = 'default';
		$result['buttons'] = [];
		$result['info_fields'] = [
			[ 'label' => $when_field, 'icon' => 'mi event' ],
			[ 'label' => '[[location.short]]', 'icon' => 'icon-location-pin-add-2' ],
		];
		$result['background'] = [ 'type' => 'image' ];
		return $result;
	}

	/* ------------------------------------------------------ instructors */

	public static function instructor_fields( array $fields ): array {
		$keep = [ 'job_title', 'job_tagline', 'job_description', 'job_logo', 'contact-information', 'job_email', 'job_phone', 'job_website', 'social-networks', 'links', 'location', 'job_location' ];
		$fields = array_intersect_key( $fields, array_flip( $keep ) );
		$labels = [
			'job_title'       => [ 'Your name', true ],
			'job_tagline'     => [ 'Headline (for example: Teaching league players for 15 years)', false ],
			'job_description' => [ 'About your teaching', false ],
			'job_logo'        => [ 'Photo', false ],
			'job_email'       => [ 'Email for lesson requests', false ],
			'job_phone'       => [ 'Phone', false ],
			'job_website'     => [ 'Booking or website link', false ],
			'location'        => [ 'Where you teach', false ],
			'job_location'    => [ 'City and state', true ],
		];
		foreach ( $labels as $key => [ $label, $required ] ) {
			if ( isset( $fields[ $key ] ) ) {
				$fields[ $key ]['label'] = $label;
				$fields[ $key ]['required'] = $required;
			}
		}
		if ( isset( $fields['job_location'] ) ) {
			$fields['job_location']['description'] = 'Only your city and state are shown. Please do not enter a home address.';
		}
		$p = 40;
		$add = [
			self::heading( 'lessons-heading', 'Lessons', $p++ ),
			self::number( 'years-teaching', 'Years teaching', $p++ ),
			self::terms( 'instructor_credentials', 'Certifications', 'instructor-credential', $p++, true, true ),
			self::terms( 'lesson_focus', 'What you teach', 'lesson-focus', $p++, true, true ),
			self::terms( 'game_types', 'Games', 'game-type', $p++, true, true ),
			self::terms( 'lesson_formats', 'Lesson formats', 'lesson-format', $p++, true, true ),
			self::number( 'lesson-rate', 'Lesson rate per hour (USD)', $p++, 'Leave blank if you prefer to quote', 0.01 ),
			self::text( 'rate-notes', 'Rates and packages', $p++, 'For example: $60 per hour, 5 lessons for $250', 'textarea' ),
			self::text( 'service-area', 'Areas you travel to', $p++, 'For example: Sioux Falls and Brandon' ),
			self::relation( Pool_Schema::INSTRUCTOR_VENUE_FIELD, 'Venues where you teach', $p++, false, 'belongs_to_many', 5 ),
		];
		foreach ( $add as $field ) {
			$fields[ $field['slug'] ] = array_merge( $fields[ $field['slug'] ] ?? [], $field );
		}
		return $fields;
	}

	public static function instructor_search( array $search ): array {
		$search['advanced']['facets'] = array_merge(
			self::location_facets( 'Where do you want lessons?' ),
			[
				self::checkboxes( 'lesson_formats', 'Lesson format' ),
				self::checkboxes( 'lesson_focus', 'What you want to work on' ),
				self::checkboxes( 'instructor_credentials', 'Certification' ),
				self::checkboxes( 'game_types', 'Game' ),
				self::facet( 'wp-search', 'Instructor name' ),
				self::facet( 'order', 'Sort by' ),
			]
		);
		$search['basic']['facets'] = [ self::facet( 'location', 'Where do you want lessons?' ) ];
		$search['order'] = [
			'options' => [
				[ 'label' => 'Nearby', 'key' => 'nearby', 'ignore_priority' => false, 'is_new' => false, 'clauses' => [ [ 'orderby' => 'proximity', 'order' => 'ASC', 'context' => 'option', 'type' => 'CHAR', 'custom_type' => false ] ], 'notes' => [ 'has-proximity-clause' ] ],
				[ 'label' => 'Name (A to Z)', 'key' => 'a-z', 'ignore_priority' => false, 'is_new' => false, 'clauses' => [ [ 'orderby' => 'title', 'order' => 'ASC', 'context' => 'option', 'type' => 'CHAR', 'custom_type' => false ] ] ],
			],
			'default' => 'a-z',
		];
		$search['explore_tabs'] = [ [ 'type' => 'search-form', 'label' => 'Filters', 'icon' => 'mi filter_list', 'orderby' => '', 'order' => '', 'hide_empty' => false ] ];
		return $search;
	}

	public static function instructor_single( array $single ): array {
		$single['menu_items'] = [
			[
				'page'       => 'main',
				'label'      => 'Profile',
				'label_l10n' => [ 'locale' => 'en_US' ],
				'slug'       => '',
				'template'   => 'two-columns',
				'layout'     => [
					[ 'type' => 'raw', 'title' => 'Lessons', 'icon' => 'mi school', 'class' => 'ppn-block-details', 'id' => '', 'content' => '[ppn_instructor_details]', 'conditional_logic' => false, 'conditions' => [] ],
					[ 'type' => 'text', 'title' => 'About', 'icon' => 'mi view_headline', 'class' => '', 'id' => '', 'show_field' => 'job_description' ],
				],
				'sidebar'    => [
					[ 'type' => 'related_listing', 'title' => 'Teaches at', 'icon' => 'mi place', 'class' => '', 'id' => '', 'show_field' => Pool_Schema::INSTRUCTOR_VENUE_FIELD ],
					[ 'type' => 'raw', 'title' => 'Profile details', 'icon' => 'mi verified', 'class' => 'ppn-block-trust', 'id' => '', 'content' => '[ppn_instructor_trust]', 'conditional_logic' => false, 'conditions' => [] ],
				],
			],
			[
				'page'       => 'comments',
				'label'      => 'Reviews',
				'label_l10n' => [ 'locale' => 'en_US' ],
				'slug'       => 'reviews',
				'layout'     => [],
			],
		];
		$single['quick_actions'] = array_map(
			static fn( $a ) => array_merge( [ 'class' => '', 'id' => '', 'label_l10n' => [ 'locale' => 'en_US' ] ], $a ),
			[
				[ 'action' => 'visit-website', 'label' => 'Book a lesson', 'icon' => 'icon-globe' ],
				[ 'action' => 'call-now', 'label' => 'Call', 'icon' => 'icon-phone-outgoing' ],
				[ 'action' => 'bookmark', 'label' => 'Save', 'icon' => 'mi favorite_border' ],
				[ 'action' => 'share', 'label' => 'Share', 'icon' => 'mi share' ],
				[ 'action' => 'leave-review', 'label' => 'Write a review', 'icon' => 'mi rate_review' ],
			]
		);
		$single['cover_details'] = [];
		$single['buttons'] = [];
		return $single;
	}

	public static function instructor_settings( array $settings ): array {
		$settings = self::play_settings( $settings, 'Instructor', 'Instructors', 'instructor', 'mi school' );
		$settings['reviews'] = [
			'multiple' => false,
			'ratings'  => [
				'enabled'    => true,
				'categories' => [
					[ 'id' => 'rating', 'label' => 'Overall', 'label_l10n' => [], 'is_new' => false ],
					[ 'id' => 'teaching', 'label' => 'Teaching', 'label_l10n' => [], 'is_new' => false ],
					[ 'id' => 'value', 'label' => 'Value', 'label_l10n' => [], 'is_new' => false ],
				],
				'mode'       => 10,
			],
			'gallery'  => [ 'enabled' => false ],
			'author'   => [ 'enabled' => true ],
			'show_in_compare' => false,
		];
		return $settings;
	}
}
