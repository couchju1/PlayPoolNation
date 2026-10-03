<?php
/**
 * Versioned, idempotent migrations.
 *
 * Each migration runs once and is recorded in the `ppn_core_migrations` option with a
 * timestamp. Migrations never delete venue data; replaced configuration is backed up
 * (see Listing_Config::set) and obsolete pages are noindexed rather than removed.
 *
 * Run pending migrations: automatically on admin_init, or `wp ppn migrate`.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Install {

	public const OPTION = 'ppn_core_migrations';
	private const LOCK = 'ppn_core_migrating';
	private const DEFER = 'defer';

	public static function boot(): void {
		add_action( 'admin_init', [ __CLASS__, 'maybe_run' ] );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'ppn migrate', static function () {
				\WP_CLI::log( print_r( self::run(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			} );
		}
	}

	/** @return array<string,callable> */
	public static function migrations(): array {
		return [
			'2026_10_01_tables'           => [ __CLASS__, 'm_tables' ],
			'2026_10_02_taxonomies'       => [ __CLASS__, 'm_taxonomies' ],
			'2026_10_03_terms'            => [ __CLASS__, 'm_terms' ],
			'2026_10_04_listing_types'    => [ __CLASS__, 'm_listing_types' ],
			'2026_10_05_backfill'         => [ __CLASS__, 'm_backfill' ],
			'2026_10_06_urls'             => [ __CLASS__, 'm_urls' ],
			'2026_10_07_seo'              => [ __CLASS__, 'm_seo' ],
			'2026_10_08_pages_nav'        => [ __CLASS__, 'm_pages_nav' ],
			'2026_10_09_retire_legacy'    => [ __CLASS__, 'm_retire_legacy' ],
			'2026_10_10_rewrites'         => [ __CLASS__, 'm_rewrites' ],
			'2026_10_11_city_rule'        => [ __CLASS__, 'm_rewrites' ],
			'2026_10_12_more_taxonomies'  => [ __CLASS__, 'm_taxonomies' ],
			'2026_10_13_more_terms'       => [ __CLASS__, 'm_terms' ],
			'2026_10_14_events_instructors' => [ __CLASS__, 'm_events_instructors' ],
			'2026_10_15_type_permalinks'  => [ __CLASS__, 'm_rewrites' ],
			'2026_10_16_my_pool'          => [ __CLASS__, 'm_my_pool' ],
			'2026_10_17_sign_in_pages'    => [ __CLASS__, 'm_sign_in_pages' ],
			'2026_10_18_promotions'       => [ __CLASS__, 'm_promotions' ],
		];
	}

	public static function done(): array {
		$done = get_option( self::OPTION, [] );
		return is_array( $done ) ? $done : [];
	}

	public static function pending(): array {
		return array_diff_key( self::migrations(), self::done() );
	}

	public static function maybe_run(): void {
		if ( self::pending() && current_user_can( 'manage_options' ) ) {
			self::run();
		}
	}

	/** @return array<string,string> migration => result */
	public static function run(): array {
		if ( get_transient( self::LOCK ) ) {
			return [ 'locked' => 'Another migration run is in progress.' ];
		}
		set_transient( self::LOCK, 1, 5 * MINUTE_IN_SECONDS );
		$results = [];
		$done = self::done();
		try {
			foreach ( self::pending() as $key => $callback ) {
				$result = call_user_func( $callback );
				if ( self::DEFER === $result ) {
					$results[ $key ] = 'deferred to next request';
					break; // Later migrations may depend on this one.
				}
				$done[ $key ] = gmdate( 'c' ) . ( is_string( $result ) && $result ? ' ' . $result : '' );
				update_option( self::OPTION, $done, false );
				$results[ $key ] = is_string( $result ) && $result ? $result : 'ok';
			}
		} finally {
			delete_transient( self::LOCK );
		}
		return $results;
	}

	/* ------------------------------------------------------- migrations */

	public static function m_tables(): string {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $wpdb->prefix . Importer::LOG_TABLE;
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id varchar(40) NOT NULL,
			row_num int(10) unsigned NOT NULL DEFAULT 0,
			action varchar(20) NOT NULL,
			venue_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message varchar(500) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY venue_id (venue_id)
		) {$charset};" );
		return 'import log table';
	}

	/** Register pool taxonomies through My Listing's custom taxonomy setting. */
	public static function m_taxonomies(): string {
		$current = get_option( 'job_manager_custom_taxonomy', [] );
		$current = is_array( $current ) ? $current : [];
		$kept = [];
		$dropped = [];
		foreach ( $current as $tax ) {
			$slug = (string) ( $tax['slug'] ?? '' );
			// Theme demo taxonomies (car brand, vacancy type...) are dropped only when they hold no terms.
			$count = taxonomy_exists( $slug ) ? (int) wp_count_terms( [ 'taxonomy' => $slug, 'hide_empty' => false ] ) : 0;
			if ( isset( Pool_Schema::TAXONOMIES[ $slug ] ) || $count > 0 ) {
				$kept[ $slug ] = $tax;
			} else {
				$dropped[] = $slug;
			}
		}
		foreach ( Pool_Schema::TAXONOMIES as $slug => $def ) {
			$kept[ $slug ] = [ 'label' => $def['label'], 'slug' => $slug ];
		}
		update_option( 'job_manager_custom_taxonomy', array_values( $kept ) );

		$permalinks = (array) get_option( 'mylisting_permalinks', [] );
		foreach ( $dropped as $slug ) {
			unset( $permalinks[ $slug . '_base' ] );
		}
		foreach ( array_keys( Pool_Schema::TAXONOMIES ) as $slug ) {
			$permalinks[ $slug . '_base' ] = $permalinks[ $slug . '_base' ] ?? $slug;
		}
		update_option( 'mylisting_permalinks', $permalinks );
		return 'dropped empty demo taxonomies: ' . implode( ', ', $dropped );
	}

	/** @return string */
	public static function m_terms() {
		foreach ( array_keys( Pool_Schema::TAXONOMIES ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				return self::DEFER; // My Listing registers them on the next request.
			}
		}
		$log = [];
		foreach ( Pool_Schema::TAXONOMIES as $taxonomy => $def ) {
			foreach ( $def['terms'] as $slug => $name ) {
				if ( ! get_term_by( 'slug', $slug, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );
				}
			}
		}

		// Venue types: rename existing terms by slug (URLs keep working), add missing ones.
		foreach ( Pool_Schema::VENUE_TYPES as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, Pool_Schema::TAX_VENUE_TYPE );
			$term ? wp_update_term( (int) $term->term_id, Pool_Schema::TAX_VENUE_TYPE, [ 'name' => $name ] ) : wp_insert_term( $name, Pool_Schema::TAX_VENUE_TYPE, [ 'slug' => $slug ] );
		}
		// Theme demo categories that are not venue types; removed only when unused.
		foreach ( [ 'breweries-taprooms', 'comedy-night', 'entertainment', 'fundraisers', 'hotels-resorts', 'karaoke', 'live-music', 'restaurants', 'tournaments' ] as $slug ) {
			$term = get_term_by( 'slug', $slug, Pool_Schema::TAX_VENUE_TYPE );
			if ( $term && 0 === (int) $term->count ) {
				wp_delete_term( (int) $term->term_id, Pool_Schema::TAX_VENUE_TYPE );
				$log[] = "removed unused category {$slug}";
			}
		}

		// Amenities: map earlier placeholder tags onto the standard list (all unused).
		$renames = [ 'kitchen' => [ 'food', 'Food' ], 'free-parking' => [ 'parking', 'Parking' ], 'smoke-free' => [ 'non-smoking', 'Non-Smoking' ] ];
		foreach ( $renames as $old => [ $slug, $name ] ) {
			$term = get_term_by( 'slug', $old, Pool_Schema::TAX_AMENITY );
			if ( $term && ! get_term_by( 'slug', $slug, Pool_Schema::TAX_AMENITY ) ) {
				wp_update_term( (int) $term->term_id, Pool_Schema::TAX_AMENITY, [ 'name' => $name, 'slug' => $slug ] );
			}
		}
		foreach ( [ 'leagues', 'tournaments', 'open-late', 'free-pool-night' ] as $old ) {
			$term = get_term_by( 'slug', $old, Pool_Schema::TAX_AMENITY );
			if ( $term && 0 === (int) $term->count ) {
				wp_delete_term( (int) $term->term_id, Pool_Schema::TAX_AMENITY ); // Now modeled as organized play / pricing.
			}
		}
		foreach ( Pool_Schema::all_amenities() as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, Pool_Schema::TAX_AMENITY );
			$term ? wp_update_term( (int) $term->term_id, Pool_Schema::TAX_AMENITY, [ 'name' => $name ] ) : wp_insert_term( $name, Pool_Schema::TAX_AMENITY, [ 'slug' => $slug ] );
		}
		return implode( '; ', $log );
	}

	public static function m_listing_types(): string {
		$venue = Listing_Config::type_id( Pool_Schema::VENUE_TYPE );
		if ( ! $venue ) {
			return 'venue type not found';
		}
		Listing_Config::set( $venue, 'fields', Listing_Config::venue_fields( Listing_Config::get( $venue, 'fields' ) ) );
		Listing_Config::set( $venue, 'search', Listing_Config::venue_search( Listing_Config::get( $venue, 'search' ) ) );
		Listing_Config::set( $venue, 'single', Listing_Config::venue_single( Listing_Config::get( $venue, 'single' ) ) );
		Listing_Config::set( $venue, 'result', Listing_Config::venue_result( Listing_Config::get( $venue, 'result' ) ) );
		Listing_Config::set( $venue, 'settings', Listing_Config::venue_settings( Listing_Config::get( $venue, 'settings' ) ) );

		// The theme's unused "Event" type becomes Tournaments (no event listings exist).
		$tournament = Listing_Config::type_id( Pool_Schema::TOURNAMENT_TYPE ) ?: Listing_Config::type_id( 'event' );
		if ( $tournament ) {
			wp_update_post( [ 'ID' => $tournament, 'post_title' => 'Tournament', 'post_name' => Pool_Schema::TOURNAMENT_TYPE ] );
			Listing_Config::set( $tournament, 'fields', Listing_Config::tournament_fields( Listing_Config::get( $tournament, 'fields' ) ) );
			Listing_Config::set( $tournament, 'search', Listing_Config::tournament_search( Listing_Config::get( $tournament, 'search' ) ) );
			Listing_Config::set( $tournament, 'single', Listing_Config::tournament_single( Listing_Config::get( $tournament, 'single' ) ) );
			Listing_Config::set( $tournament, 'result', Listing_Config::play_result( Listing_Config::get( $tournament, 'result' ), '[[event-date]]' ) );
			Listing_Config::set( $tournament, 'settings', Listing_Config::play_settings( Listing_Config::get( $tournament, 'settings' ), 'Tournament', 'Tournaments', 'tournament', 'mi emoji_events' ) );
		}

		// Leagues: new type based on the tournament configuration shape.
		$league = Listing_Config::type_id( Pool_Schema::LEAGUE_TYPE );
		if ( ! $league && $tournament ) {
			$league = (int) wp_insert_post( [ 'post_type' => 'case27_listing_type', 'post_status' => 'publish', 'post_title' => 'League', 'post_name' => Pool_Schema::LEAGUE_TYPE ] );
			foreach ( array_keys( Listing_Config::META ) as $part ) {
				Listing_Config::set( $league, $part, Listing_Config::get( $tournament, $part ) );
			}
		}
		if ( $league ) {
			Listing_Config::set( $league, 'fields', Listing_Config::league_fields( Listing_Config::get( $league, 'fields' ) ) );
			Listing_Config::set( $league, 'search', Listing_Config::league_search( Listing_Config::get( $league, 'search' ) ) );
			Listing_Config::set( $league, 'single', Listing_Config::league_single( Listing_Config::get( $league, 'single' ) ) );
			Listing_Config::set( $league, 'result', Listing_Config::play_result( Listing_Config::get( $league, 'result' ), '[[league-day]] [[league-time]]' ) );
			Listing_Config::set( $league, 'settings', Listing_Config::play_settings( Listing_Config::get( $league, 'settings' ), 'League', 'Leagues', 'league', 'mi groups' ) );
		}
		return "venue {$venue}, tournament {$tournament}, league {$league}";
	}

	public static function m_backfill(): string {
		$ids = get_posts( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => [ 'publish', 'pending' ], 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_case27_listing_type', 'meta_value' => Pool_Schema::VENUE_TYPE ] );
		Venue::prime( $ids );
		foreach ( $ids as $id ) {
			$id = (int) $id;
			$place = (string) get_post_meta( $id, '_ppn_place_id', true );
			if ( $place ) {
				Provenance::set_external_id( $id, 'google_places', $place );
				Provenance::set_origin( $id, 'google_places', (string) get_post_meta( $id, '_ppn_google_url', true ) );
				$checked = (string) get_post_meta( $id, '_ppn_verified', true );
				if ( $checked && ! get_post_meta( $id, '_ppn_last_checked', true ) ) {
					Provenance::touch_checked( $id, $checked );
				}
			}
			Locations::sync( $id );
			Pool_Data::sync_size_terms( $id );
		}
		return count( $ids ) . ' venues';
	}

	public static function m_urls(): string {
		$permalinks = (array) get_option( 'mylisting_permalinks', [] );
		$permalinks['job_base'] = '%listing_type%';
		$permalinks['region_base'] = Locations::REGION_BASE;
		update_option( 'mylisting_permalinks', $permalinks );

		$redirects = (array) get_option( 'ppn_redirects', [] );
		$explore = (int) get_option( 'options_general_explore_listings_page' );
		if ( $explore && 'places' !== get_post_field( 'post_name', $explore ) ) {
			$redirects[ trim( (string) get_post_field( 'post_name', $explore ), '/' ) ] = 'places';
			wp_update_post( [ 'ID' => $explore, 'post_name' => 'places', 'post_title' => 'Find Places' ] );
		}
		update_option( 'ppn_redirects', $redirects );

		$count = 0;
		foreach ( get_posts( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_case27_listing_type', 'meta_value' => Pool_Schema::VENUE_TYPE ] ) as $id ) {
			$count += Locations::maybe_set_slug( (int) $id ) ? 1 : 0;
		}
		flush_rewrite_rules( false );
		return "{$count} venue slugs";
	}

	public static function m_seo(): string {
		$noindex = [];
		foreach ( [ 'cart-2', 'checkout-2', 'my-account-2', 'shop-2', 'login-2', 'register-2', 'dashboard', 'claim-listing', 'claim-your-listing', 'blog-2', 'blog-3', 'terms-and-conditions', 'refund_returns' ] as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				$noindex[] = (int) $page->ID;
			}
		}
		foreach ( [ 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_shop_page_id' ] as $opt ) {
			if ( (int) get_option( $opt ) ) {
				$noindex[] = (int) get_option( $opt );
			}
		}
		update_option( 'ppn_noindex_pages', array_values( array_unique( $noindex ) ) );
		self::regenerate_elementor_text();
		return count( $noindex ) . ' pages noindexed';
	}

	/**
	 * Elementor keeps a plain-text copy of each page in post_content for search engines and
	 * excerpts. Pages edited outside the editor kept stale copies, so rebuild them.
	 */
	public static function regenerate_elementor_text( array $ids = [] ): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		if ( ! $ids ) {
			global $wpdb;
			$ids = $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_edit_mode' AND m.meta_value = 'builder' WHERE p.post_status = 'publish' AND p.post_type IN ('page','elementor_library')" );
		}
		foreach ( $ids as $id ) {
			$document = \Elementor\Plugin::$instance->documents->get( (int) $id, false );
			if ( $document ) {
				$elements = $document->get_elements_data();
				if ( $elements ) {
					$document->save( [ 'elements' => $elements ] );
				}
			}
		}
	}

	public static function m_pages_nav(): string {
		$pages = [];
		$pages['tournaments'] = self::ensure_explore_page( 'tournaments', 'Tournaments', Pool_Schema::TOURNAMENT_TYPE, 'Upcoming pool tournaments', 'Find 8-ball, 9-ball and 10-ball tournaments near you. Know one that is missing? Venue owners can add events to their listing.' );
		$pages['leagues'] = self::ensure_explore_page( 'leagues', 'Leagues', Pool_Schema::LEAGUE_TYPE, 'Pool leagues near you', 'APA, BCA, USAPL and local leagues, with the night they play and where. Run a league? Add it to your venue.' );

		// Add a Venue: short community form. The theme's full form moves to "List your venue" for owners.
		$add = get_page_by_path( 'add-a-venue' ) ?: get_page_by_path( 'add-your-hall' );
		if ( $add ) {
			$redirects = (array) get_option( 'ppn_redirects', [] );
			if ( 'add-a-venue' !== $add->post_name ) {
				$redirects[ $add->post_name ] = 'add-a-venue';
				update_option( 'ppn_redirects', $redirects );
			}
			$owner_page = get_page_by_path( 'list-your-venue' );
			if ( ! $owner_page ) {
				$owner_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'List Your Venue', 'post_name' => 'list-your-venue' ] );
				foreach ( [ '_elementor_data', '_elementor_edit_mode', '_elementor_template_type', '_elementor_version', '_wp_page_template', '_elementor_page_settings' ] as $key ) {
					update_post_meta( $owner_id, $key, wp_slash( get_post_meta( $add->ID, $key, true ) ) );
				}
				update_option( 'options_general_add_listing_page', $owner_id );
			}
			wp_update_post( [ 'ID' => $add->ID, 'post_name' => 'add-a-venue', 'post_title' => 'Add a Venue' ] );
			Pages::write_add_venue( (int) $add->ID );
			$pages['add'] = (int) $add->ID;
		}

		Pages::write_navigation( $pages );
		Pages::write_home();
		self::regenerate_elementor_text();
		return wp_json_encode( $pages );
	}

	public static function ensure_explore_page( string $slug, string $title, string $type, string $heading, string $intro, array $actions = [] ): int {
		$page = get_page_by_path( $slug );
		$id = $page ? (int) $page->ID : (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ] );
		Pages::write_explore( $id, $type, $heading, $intro, $actions );
		return $id;
	}

	public static function m_retire_legacy(): string {
		$log = [];
		$mu = WPMU_PLUGIN_DIR . '/playpoolnation-config.php';
		// Only GeoDirectory setup code (plugin inactive) and an obsolete admin notice. Kept on disk, disabled.
		if ( file_exists( $mu ) && ! class_exists( 'GeoDirectory' ) ) {
			rename( $mu, $mu . '.disabled' );
			$log[] = 'disabled legacy mu-plugin';
		}
		update_option( 'ppn_setup_complete', true );
		return implode( '; ', $log );
	}

	/** My Listing caches listing-type URL bases; drop the cache so /tournament/ and /league/ resolve, then rebuild rules. */
	public static function m_rewrites(): string {
		delete_option( 'mylisting_permalinks_types_cache' );
		Seo_Meta::rewrite();
		flush_rewrite_rules( false );
		return 'listing type permalinks refreshed';
	}

	/**
	 * Events (the tournament type, renamed for visitors) and the new Instructor type,
	 * with their pages and the navigation.
	 */
	public static function m_events_instructors(): string {
		$log = [];
		$event = Listing_Config::type_id( Pool_Schema::TOURNAMENT_TYPE );
		if ( $event ) {
			Listing_Config::set( $event, 'fields', Listing_Config::tournament_fields( Listing_Config::get( $event, 'fields' ) ) );
			Listing_Config::set( $event, 'search', Listing_Config::tournament_search( Listing_Config::get( $event, 'search' ) ) );
			Listing_Config::set( $event, 'single', Listing_Config::tournament_single( Listing_Config::get( $event, 'single' ) ) );
			Listing_Config::set( $event, 'settings', Listing_Config::play_settings( Listing_Config::get( $event, 'settings' ), 'Event', 'Events', 'event', 'mi emoji_events' ) );
			wp_update_post( [ 'ID' => $event, 'post_title' => 'Event' ] );
			$log[] = "event type {$event}";
		}
		$venue = Listing_Config::type_id( Pool_Schema::VENUE_TYPE );
		if ( $venue ) {
			Listing_Config::set( $venue, 'single', Listing_Config::venue_single( Listing_Config::get( $venue, 'single' ) ) );
		}

		// Instructors: a new type, starting from the venue type's base fields (photo, contact, social links, location).
		$instructor = Listing_Config::type_id( Pool_Schema::INSTRUCTOR_TYPE );
		if ( ! $instructor && $venue ) {
			$instructor = (int) wp_insert_post( [ 'post_type' => 'case27_listing_type', 'post_status' => 'publish', 'post_title' => 'Instructor', 'post_name' => Pool_Schema::INSTRUCTOR_TYPE ] );
			foreach ( array_keys( Listing_Config::META ) as $part ) {
				Listing_Config::set( $instructor, $part, Listing_Config::get( $venue, $part ) );
			}
		}
		if ( $instructor ) {
			Listing_Config::set( $instructor, 'fields', Listing_Config::instructor_fields( Listing_Config::get( $instructor, 'fields' ) ) );
			Listing_Config::set( $instructor, 'search', Listing_Config::instructor_search( Listing_Config::get( $instructor, 'search' ) ) );
			Listing_Config::set( $instructor, 'single', Listing_Config::instructor_single( Listing_Config::get( $instructor, 'single' ) ) );
			Listing_Config::set( $instructor, 'result', Listing_Config::play_result( Listing_Config::get( $instructor, 'result' ), '[[lesson_formats]]' ) );
			Listing_Config::set( $instructor, 'settings', Listing_Config::instructor_settings( Listing_Config::get( $instructor, 'settings' ) ) );
			$log[] = "instructor type {$instructor}";
		}

		// Pages. The Tournaments page becomes Events; its old address redirects.
		$redirects = (array) get_option( 'ppn_redirects', [] );
		$events_page = get_page_by_path( 'events' ) ?: get_page_by_path( 'tournaments' );
		if ( $events_page && 'events' !== $events_page->post_name ) {
			wp_update_post( [ 'ID' => $events_page->ID, 'post_name' => 'events', 'post_title' => 'Events' ] );
		}
		$redirects['tournaments'] = 'events';
		$redirects['tournament'] = 'events';
		update_option( 'ppn_redirects', $redirects );

		$pages = [];
		$pages['events'] = self::ensure_explore_page( 'events', 'Events', Pool_Schema::TOURNAMENT_TYPE, 'Pool tournaments and events', 'Weekly tournaments, league sign-ups, clinics and more near you. Running one? Post it here.', [ 'Post an event' => Events::page_url() ] );
		$league = get_page_by_path( 'leagues' );
		$pages['leagues'] = $league ? (int) $league->ID : 0;
		$pages['instructors'] = self::ensure_explore_page( 'instructors', 'Instructors', Pool_Schema::INSTRUCTOR_TYPE, 'Pool instructors near you', 'Find someone to help with your stroke, position play or league game. Compare what they teach, how they teach and what they charge.', [ 'Teach pool? Create a free profile' => home_url( '/' . Instructors::SIGNUP_PAGE . '/' ) ] );
		$add = get_page_by_path( 'add-a-venue' );
		$pages['add'] = $add ? (int) $add->ID : 0;

		$post_event = get_page_by_path( Events::PAGE );
		$post_event_id = $post_event ? (int) $post_event->ID : (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Post an Event', 'post_name' => Events::PAGE ] );
		Pages::write_post_event( $post_event_id );
		$teach = get_page_by_path( Instructors::SIGNUP_PAGE );
		$teach_id = $teach ? (int) $teach->ID : (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Create Your Instructor Profile', 'post_name' => Instructors::SIGNUP_PAGE ] );
		Pages::write_instructor_signup( $teach_id );

		Pages::write_navigation( $pages );
		Pages::write_home();
		self::regenerate_elementor_text();
		Stats::flush();
		$log[] = 'pages ' . wp_json_encode( $pages + [ 'post_event' => $post_event_id, 'teach' => $teach_id ] );
		return implode( '; ', $log );
	}

	/** The signed-in player page, kept out of search, and linked from the account menu. */
	public static function m_my_pool(): string {
		$page = get_page_by_path( My_Pool::PAGE );
		$id = $page ? (int) $page->ID : (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'My Pool', 'post_name' => My_Pool::PAGE ] );
		Pages::write_my_pool( $id );
		$noindex = array_map( 'intval', (array) get_option( 'ppn_noindex_pages', [] ) );
		if ( ! in_array( $id, $noindex, true ) ) {
			$noindex[] = $id;
			update_option( 'ppn_noindex_pages', $noindex );
		}
		// The header's account dropdown is a WordPress menu (it holds "Saved Places"); put My Pool first.
		$added = 0;
		foreach ( wp_get_nav_menus() as $menu ) {
			$items = (array) wp_get_nav_menu_items( $menu->term_id );
			$titles = array_map( static fn( $i ) => trim( preg_replace( '/\[[^\]]*\]/', '', $i->title ) ), $items );
			if ( ! in_array( 'Saved Places', $titles, true ) || in_array( 'My Pool', $titles, true ) ) {
				continue;
			}
			foreach ( $items as $item ) {
				wp_update_post( [ 'ID' => (int) $item->ID, 'menu_order' => (int) $item->menu_order + 1 ] );
			}
			wp_update_nav_menu_item( $menu->term_id, 0, [ 'menu-item-title' => '[27-icon icon="mi place"] My Pool', 'menu-item-object' => 'page', 'menu-item-object-id' => $id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => 1 ] );
			$added++;
		}
		return "page {$id}; account menus updated: {$added}";
	}

	/** /sign-in/ and /join/: friendly addresses for the account form (noindexed). */
	public static function m_sign_in_pages(): string {
		$ids = [];
		foreach ( [ My_Pool::SIGN_IN => [ 'Sign In', false ], My_Pool::JOIN => [ 'Create Your Free Account', true ] ] as $slug => [ $title, $join ] ) {
			$page = get_page_by_path( $slug );
			$id = $page ? (int) $page->ID : (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ] );
			Pages::write_sign_in( $id, $join );
			$ids[] = $id;
		}
		$noindex = array_map( 'intval', (array) get_option( 'ppn_noindex_pages', [] ) );
		update_option( 'ppn_noindex_pages', array_values( array_unique( array_merge( $noindex, $ids ) ) ) );
		return 'pages ' . implode( ',', $ids );
	}

	/**
	 * Promotion packages (hidden, off until switched on), the owner Promote page,
	 * the "Grow your business" sales page (draft until switched on), and the
	 * theme's demo shop products moved to draft so nothing stray is for sale.
	 */
	public static function m_promotions() {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			return self::DEFER;
		}
		$log = [];
		$ids = Promotions::ensure_products();
		$log[] = 'packages ' . wp_json_encode( $ids );

		$drafted = [];
		foreach ( get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1 ] ) as $product ) {
			if ( in_array( (int) $product->ID, $ids, true ) ) {
				continue;
			}
			$types = wp_get_object_terms( $product->ID, 'product_type', [ 'fields' => 'slugs' ] );
			$demo = array_intersect( (array) $types, [ 'promotion_package', 'job_package', 'job_package_subscription' ] ) || 'Some random product' === $product->post_title;
			if ( $demo ) {
				wp_update_post( [ 'ID' => $product->ID, 'post_status' => 'draft' ] );
				$drafted[] = (int) $product->ID;
			}
		}
		update_option( 'ppn_drafted_demo_products', $drafted, false );
		$log[] = 'demo products drafted ' . implode( ',', $drafted );

		$promote = get_page_by_path( Promotions::PAGE );
		$promote_id = $promote ? (int) $promote->ID : (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Promote', 'post_name' => Promotions::PAGE ] );
		Pages::write_shortcode_page( $promote_id, 'Promote your venue or event', 'Show up first when players near you search. Pick a listing, pay securely, and it is promoted right away.', '[ppn_promote]' );
		$noindex = array_map( 'intval', (array) get_option( 'ppn_noindex_pages', [] ) );
		update_option( 'ppn_noindex_pages', array_values( array_unique( array_merge( $noindex, [ $promote_id ] ) ) ) );

		$sales_id = (int) get_option( Promotions::SALES_OPTION );
		if ( ! $sales_id || ! get_post( $sales_id ) ) {
			$sales_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => Promotions::enabled() ? 'publish' : 'draft', 'post_title' => 'Grow Your Business', 'post_name' => Promotions::SALES_PAGE ] );
			update_option( Promotions::SALES_OPTION, $sales_id, false );
		}
		Pages::write_shortcode_page( $sales_id, 'Grow your pool hall or bar', 'Players use PlayPoolNation to decide where to play tonight. Listing is free. Featured placement puts you first.', '[ppn_advertise]' );
		$log[] = "pages promote {$promote_id}, advertise {$sales_id}";
		return implode( '; ', $log );
	}
}
