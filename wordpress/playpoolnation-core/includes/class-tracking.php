<?php
/**
 * First-party click counts for venue listings: calls, directions, website visits,
 * saves, shares, suggested edits and claims, plus venue page views per day. These
 * are the numbers an owner report is built from (Venue_Stats).
 *
 * Pages are cached, so assets/ppn.js sends each click to POST /wp-json/ppn/v1/event
 * with navigator.sendBeacon. No cookies are set, and an event row holds only the
 * venue, what was clicked, where, the page path, the venue's city, the device
 * class, a traffic source bucket and the hour (UTC). IP addresses are used, hashed,
 * only for a short-lived rate limit and are never stored with an event.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Event_Data;

defined( 'ABSPATH' ) || exit;

final class Tracking {

	public const EVENTS_TABLE  = 'ppn_events';
	public const VIEWS_TABLE   = 'ppn_venue_views';
	public const MONTHLY_TABLE = 'ppn_events_monthly';
	private const DB_OPTION    = 'ppn_core_db_version';
	public const CRON          = 'ppn_events_rollup';

	/** Requests per visitor (IP hash) per window; a person clicking around stays far below it. */
	private const RATE_LIMIT  = 120;
	private const RATE_WINDOW = 10 * MINUTE_IN_SECONDS;

	/** User agents that are crawlers, previews or scripts (a browser runs the beacon; these do not count). */
	private const BOT_UA = '#bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|preview|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|python|curl|wget|httpclient|okhttp|java/|go-http|axios|node-fetch|phantom|selenium|puppeteer|playwright#i';

	public static function boot(): void {
		add_action( 'init', [ __CLASS__, 'maybe_install' ], 5 );
		add_action( 'init', [ __CLASS__, 'schedule' ] );
		add_action( self::CRON, [ __CLASS__, 'rollup' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'ppn_markup', static fn() => Markup::add( [ Helpers\Html_Fix::class, 'track_links' ] ) );
	}

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	/* -------------------------------------------------------------- schema */

	public static function maybe_install(): void {
		if ( (int) get_option( self::DB_OPTION, 0 ) < PPN_CORE_DB_VERSION ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$events = self::table( self::EVENTS_TABLE );
		$views = self::table( self::VIEWS_TABLE );
		$monthly = self::table( self::MONTHLY_TABLE );
		dbDelta( "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			venue_id bigint(20) unsigned NOT NULL,
			event_type varchar(20) NOT NULL,
			context varchar(20) NOT NULL,
			path varchar(200) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			device varchar(10) NOT NULL,
			source varchar(10) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY venue_created (venue_id,created_at),
			KEY type_created (event_type,created_at)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$views} (
			venue_id bigint(20) unsigned NOT NULL,
			day date NOT NULL,
			views int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (venue_id,day),
			KEY day (day)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$monthly} (
			month date NOT NULL,
			venue_id bigint(20) unsigned NOT NULL,
			event_type varchar(20) NOT NULL,
			context varchar(20) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			device varchar(10) NOT NULL DEFAULT '',
			source varchar(10) NOT NULL DEFAULT '',
			count int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (month,venue_id,event_type,context,city,device,source),
			KEY venue_month (venue_id,month)
		) {$charset};" );
		update_option( self::DB_OPTION, PPN_CORE_DB_VERSION );
	}

	/* ------------------------------------------------------------ front end */

	/**
	 * What ppn.js needs to send events on this page, or null when this visitor's
	 * clicks are not counted (staff). Staff pages are not served from the page cache.
	 *
	 * @return array{url:string,page:string,venue:int}|null
	 */
	public static function page_config(): ?array {
		if ( current_user_can( 'edit_posts' ) ) {
			return null;
		}
		$page = 'other';
		$venue = 0;
		if ( is_singular( Pool_Schema::POST_TYPE ) && Venue::is_venue( (int) get_queried_object_id() ) ) {
			$page = 'venue';
			$venue = (int) get_queried_object_id();
		} elseif ( is_page( My_Pool::PAGE ) ) {
			$page = 'my_pool';
		} elseif ( City_Page::current() ) {
			$page = 'city';
		} elseif ( Performance::maps_on_load() ) {
			$page = Seo_Meta::region_term() || Metros::current() ? 'city' : 'hub';
		}
		return [ 'url' => esc_url_raw( rest_url( 'ppn/v1/event' ) ), 'page' => $page, 'venue' => $venue ];
	}

	/* ----------------------------------------------------------------- REST */

	public static function routes(): void {
		register_rest_route( 'ppn/v1', '/event', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'record' ],
			'permission_callback' => '__return_true',
		] );
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function record( \WP_REST_Request $request ) {
		$none = new \WP_REST_Response( null, 204 );
		$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		if ( '' === $ua || preg_match( self::BOT_UA, $ua ) ) {
			return $none;
		}
		// Beacons carry the login cookie but no REST nonce, so read the cookie directly.
		$user = (int) wp_validate_auth_cookie( '', 'logged_in' );
		if ( $user && user_can( $user, 'edit_posts' ) ) {
			return $none;
		}
		if ( self::rate_limited() ) {
			return new \WP_Error( 'ppn_rate_limited', 'Too many events.', [ 'status' => 429 ] );
		}
		$event = Event_Data::validate( $request->get_params(), (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( ! $event || 'publish' !== get_post_status( $event['venue_id'] ) || ! Venue::is_venue( $event['venue_id'] ) ) {
			return new \WP_Error( 'ppn_invalid_event', 'Invalid event.', [ 'status' => 400 ] );
		}
		self::store( $event );
		return $none;
	}

	/** @param array{venue_id:int,event_type:string,context:string,path:string,device:string,source:string} $event */
	public static function store( array $event ): void {
		global $wpdb;
		if ( Event_Data::VIEW === $event['event_type'] ) {
			$wpdb->query( $wpdb->prepare(
				'INSERT INTO ' . self::table( self::VIEWS_TABLE ) . ' (venue_id, day, views) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE views = views + 1',
				$event['venue_id'],
				gmdate( 'Y-m-d' )
			) );
			return;
		}
		$wpdb->insert( self::table( self::EVENTS_TABLE ), $event + [
			'city'       => self::city_slug( $event['venue_id'] ),
			'created_at' => gmdate( 'Y-m-d H:00:00' ),
		] );
	}

	/** The venue's city region term slug, or '' when it has none. */
	public static function city_slug( int $venue_id ): string {
		$terms = wp_get_object_terms( $venue_id, Pool_Schema::TAX_REGION );
		if ( is_wp_error( $terms ) ) {
			return '';
		}
		foreach ( $terms as $term ) {
			if ( $term->parent ) {
				return $term->slug;
			}
		}
		return '';
	}

	/**
	 * A per-visitor counter for the current window, keyed on a salted hash of the
	 * address(es) and the day. The forwarded address is part of the key because the
	 * CDN in front of the site can make many visitors share REMOTE_ADDR.
	 */
	private static function rate_limited(): bool {
		$who = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) . '|' . (string) ( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' );
		$key = 'ppn_ev_' . substr( hash_hmac( 'sha256', $who . '|' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) ), 0, 32 );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return true;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return false;
	}

	/* --------------------------------------------------------------- rollup */

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:17 UTC' ), 'daily', self::CRON );
		}
	}

	/**
	 * Events and daily views older than Event_Data::KEEP_MONTHS whole months become
	 * monthly counts; the raw rows are then deleted. Runs in batches, counts first:
	 * an interrupted batch is counted again rather than lost. No transaction of its
	 * own, since MySQL would commit any transaction a caller has open.
	 *
	 * @return int Raw rows rolled up.
	 */
	public static function rollup( ?int $now = null ): int {
		global $wpdb;
		$cutoff = Event_Data::rollup_cutoff( $now ?? time() );
		$events = self::table( self::EVENTS_TABLE );
		$views = self::table( self::VIEWS_TABLE );
		$done = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$events} WHERE created_at < %s ORDER BY id LIMIT 5000", $cutoff ), ARRAY_A );
			if ( $rows ) {
				self::add_monthly( Event_Data::monthly( $rows ) );
				$wpdb->query( 'DELETE FROM ' . $events . ' WHERE id IN (' . implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) ) . ')' );
				$done += count( $rows );
			}
		} while ( count( $rows ) === 5000 );
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT venue_id, day, views AS count FROM {$views} WHERE day < %s ORDER BY day, venue_id LIMIT 5000", substr( $cutoff, 0, 10 ) ), ARRAY_A );
			if ( $rows ) {
				foreach ( $rows as &$row ) {
					$row['event_type'] = Event_Data::VIEW;
					$row['context'] = 'venue_page';
				}
				unset( $row );
				self::add_monthly( Event_Data::monthly( $rows ) );
				foreach ( $rows as $row ) {
					$wpdb->delete( $views, [ 'venue_id' => (int) $row['venue_id'], 'day' => $row['day'] ] );
				}
				$done += count( $rows );
			}
		} while ( count( $rows ) === 5000 );
		return $done;
	}

	/** @param array<int,array<string,mixed>> $counts from Event_Data::monthly() */
	private static function add_monthly( array $counts ): void {
		global $wpdb;
		$table = self::table( self::MONTHLY_TABLE );
		foreach ( $counts as $c ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$table} (month, venue_id, event_type, context, city, device, source, count) VALUES (%s, %d, %s, %s, %s, %s, %s, %d)
				 ON DUPLICATE KEY UPDATE count = count + VALUES(count)",
				$c['month'], $c['venue_id'], $c['event_type'], $c['context'], $c['city'], $c['device'], $c['source'], $c['count']
			) );
		}
	}
}
