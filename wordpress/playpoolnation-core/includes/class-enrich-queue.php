<?php
/**
 * Review queue for facts and new venues found by tools/enrich.
 *
 * `wp ppn enrich import <city>` loads the tool's JSON into {prefix}ppn_enrich_queue.
 * Nothing on the public site changes until a reviewer approves an item under
 * PlayPoolNation → Review: an approved fact is written through Pool_Data::apply_fact()
 * with its source URL as provenance; an approved new venue becomes a listing.
 * The quote shown to the reviewer stays in this table and is never printed on the site.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Enrich_Fact;

defined( 'ABSPATH' ) || exit;

final class Enrich_Queue {

	public const TABLE = 'ppn_enrich_queue';
	public const PAGE = 'ppn-enrich-review';
	private const NONCE = 'ppn_enrich_review';
	/** Files in out/<city>/ that are not venue files. */
	private const NOT_VENUES = [ 'new-venues.json', 'listings.json', 'league-facts.json', 'osm-listing-links.json', 'osm-bars.json' ];

	public static function boot(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 15 );
		add_action( 'wp_ajax_ppn_enrich_decide', [ __CLASS__, 'ajax_decide' ] );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function install(): string {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_hash char(32) NOT NULL,
			city varchar(100) NOT NULL,
			venue_slug varchar(190) NOT NULL,
			venue_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(20) NOT NULL DEFAULT 'fact',
			field varchar(40) NOT NULL DEFAULT '',
			fact_key varchar(120) NOT NULL DEFAULT '',
			value longtext NOT NULL,
			source_url text NOT NULL,
			source_type varchar(30) NOT NULL DEFAULT '',
			quote varchar(300) NOT NULL DEFAULT '',
			confidence varchar(10) NOT NULL DEFAULT 'implied',
			flags text NOT NULL,
			payload longtext NOT NULL,
			checked_at date DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			note varchar(500) NOT NULL DEFAULT '',
			decided_value longtext,
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewed_at datetime DEFAULT NULL,
			sort_order int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY item_hash (item_hash),
			KEY status_city (status,city),
			KEY venue (city,venue_slug)
		) {$charset};" );
		return 'enrichment review queue table';
	}

	private static function exists(): bool {
		global $wpdb;
		return self::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() ) );
	}

	/* ------------------------------------------------------------- import */

	/**
	 * Load one city's output folder into the queue.
	 *
	 * Pending items are refreshed in place. An item already approved or rejected
	 * is left alone when the value is the same, and reopened with a note when a
	 * later run found a different value (the monthly re-check).
	 *
	 * @return array{inserted:int,updated:int,unchanged:int,reopened:int,invalid:int,unresolved:string[],new_venues:int}
	 */
	public static function import_dir( string $dir, string $city ): array {
		if ( ! self::exists() ) {
			self::install();
		}
		$city = sanitize_title( $city );
		$sum = [ 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'reopened' => 0, 'invalid' => 0, 'unresolved' => [], 'new_venues' => 0 ];
		$order = 0;

		// Venues in the order the tool worked them (pool halls first); every file only without a state file.
		$state = self::read( $dir . '/_state.json' ) ?: [];
		$slugs = array_keys( (array) ( $state['venues'] ?? [] ) );
		foreach ( $slugs ? [] : ( glob( $dir . '/*.json' ) ?: [] ) as $file ) {
			$name = basename( $file, '.json' );
			if ( ! str_starts_with( $name, '_' ) && ! in_array( basename( $file ), self::NOT_VENUES, true ) && ! in_array( $name, $slugs, true ) ) {
				$slugs[] = $name;
			}
		}

		$new_slugs = [];
		foreach ( (array) self::read( $dir . '/new-venues.json' ) as $venue ) {
			if ( ! is_array( $venue ) || empty( $venue['slug'] ) || empty( $venue['name'] ) || ! empty( $venue['places_expired'] ) ) {
				continue;
			}
			$new_slugs[] = $venue['slug'];
			$payload = array_intersect_key( $venue, array_flip( [ 'name', 'street', 'city', 'state', 'zip', 'lat', 'lng', 'phone', 'website', 'facebook', 'venue_type', 'sources', 'match', 'places_expires' ] ) );
			$flags = array_map( 'strval', (array) ( $venue['flags'] ?? [] ) );
			if ( 'possible' === ( $venue['match']['class'] ?? '' ) ) {
				array_unshift( $flags, 'may already be listed: ' . ( $venue['match']['slug'] ?? '' ) . ' (' . implode( ', ', (array) ( $venue['match']['reasons'] ?? [] ) ) . ')' );
			}
			$source = (array) ( $venue['sources'][0] ?? [] );
			$row = [
				'kind'        => 'new_venue',
				'field'       => 'new_venue',
				'fact_key'    => 'new_venue',
				'value'       => wp_json_encode( (string) $venue['name'] ),
				'source_url'  => esc_url_raw( (string) ( $source['url'] ?? '' ) ),
				'source_type' => sanitize_key( (string) ( $source['source'] ?? '' ) ),
				'quote'       => sanitize_text_field( (string) ( $source['evidence'] ?? '' ) ),
				'confidence'  => 'stated',
				'flags'       => wp_json_encode( $flags ),
				'payload'     => wp_json_encode( $payload ),
				'checked_at'  => self::date( (string) ( $venue['discovered_at'] ?? '' ) ),
			];
			$sum[ self::upsert( $city, (string) $venue['slug'], 0, $row, ++$order ) ]++;
			++$sum['new_venues'];
		}

		foreach ( $slugs as $slug ) {
			$record = self::read( $dir . '/' . $slug . '.json' );
			if ( ! is_array( $record ) || empty( $record['facts'] ) ) {
				continue;
			}
			$venue_id = 'new' === ( $record['kind'] ?? '' ) ? self::created_from( $city, $slug ) : self::resolve( (array) ( $record['listing'] ?? [] ), $slug );
			if ( ! $venue_id && 'new' !== ( $record['kind'] ?? '' ) ) {
				$sum['unresolved'][] = $slug;
				continue;
			}
			++$order;
			foreach ( (array) $record['facts'] as $raw ) {
				$fact = is_array( $raw ) ? Enrich_Fact::normalize( $raw ) : null;
				if ( ! $fact ) {
					++$sum['invalid'];
					continue;
				}
				$flags = $fact['flags'];
				if ( 'implied' === $fact['confidence'] ) {
					array_unshift( $flags, 'implied, not stated' );
				}
				$row = [
					'kind'        => 'fact',
					'field'       => $fact['field'],
					'fact_key'    => $fact['key'],
					'value'       => wp_json_encode( $fact['value'] ),
					'source_url'  => esc_url_raw( $fact['source_url'] ),
					'source_type' => $fact['source_type'],
					'quote'       => $fact['quote'],
					'confidence'  => $fact['confidence'],
					'flags'       => wp_json_encode( $flags ),
					'payload'     => wp_json_encode( [ 'conflicts' => $fact['conflicts'], 'evidence' => $fact['evidence'] ] ),
					'checked_at'  => $fact['checked_at'],
				];
				$sum[ self::upsert( $city, $slug, $venue_id, $row, $order ) ]++;
			}
		}
		return $sum;
	}

	/** @return mixed */
	private static function read( string $file ) {
		if ( ! is_readable( $file ) ) {
			return null;
		}
		return json_decode( (string) file_get_contents( $file ), true );
	}

	private static function date( string $d ): string {
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : gmdate( 'Y-m-d' );
	}

	/** Listing ID for a venue file: its ID if still a venue, else its URL, else its slug. */
	public static function resolve( array $listing, string $slug ): int {
		$id = (int) ( $listing['id'] ?? 0 );
		if ( $id && Venue::is_venue( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		if ( ! empty( $listing['url'] ) ) {
			$id = url_to_postid( (string) $listing['url'] );
			if ( $id && Venue::is_venue( $id ) ) {
				return $id;
			}
		}
		$post = get_page_by_path( $slug, OBJECT, Pool_Schema::POST_TYPE );
		return $post && Venue::is_venue( (int) $post->ID ) ? (int) $post->ID : 0;
	}

	/** The listing an approved new venue became, or 0 while it waits. */
	private static function created_from( string $city, string $slug ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT venue_id FROM ' . self::table() . " WHERE city = %s AND venue_slug = %s AND kind = 'new_venue' AND status = 'approved'", $city, $slug ) );
	}

	private static function upsert( string $city, string $slug, int $venue_id, array $row, int $order ): string {
		global $wpdb;
		$hash = md5( $city . '|' . $slug . '|' . $row['fact_key'] );
		$now = gmdate( 'Y-m-d H:i:s' );
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE item_hash = %s', $hash ) );
		if ( ! $existing ) {
			$wpdb->insert( self::table(), $row + [ 'item_hash' => $hash, 'city' => $city, 'venue_slug' => $slug, 'venue_id' => $venue_id, 'status' => 'pending', 'note' => '', 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now ] );
			return 'inserted';
		}
		$same = $existing->value === $row['value'];
		if ( 'pending' === $existing->status ) {
			$wpdb->update( self::table(), $row + [ 'venue_id' => $venue_id ?: (int) $existing->venue_id, 'sort_order' => $order, 'updated_at' => $now ], [ 'id' => $existing->id ] );
			return $same ? 'unchanged' : 'updated';
		}
		$decided = null !== $existing->decided_value ? $existing->decided_value : $existing->value;
		if ( $same || $decided === $row['value'] ) {
			$wpdb->update( self::table(), [ 'checked_at' => $row['checked_at'], 'source_url' => $row['source_url'], 'updated_at' => $now ], [ 'id' => $existing->id ] );
			return 'unchanged';
		}
		$was = Enrich_Fact::FIELDS[ $existing->field ] ?? null ? Enrich_Fact::label( $existing->field, json_decode( $decided, true ) ) : (string) json_decode( $decided, true );
		$note = sprintf( 'Changed since you %s it on %s (was: %s).', 'approved' === $existing->status ? 'approved' : 'rejected', substr( (string) $existing->reviewed_at, 0, 10 ), $was );
		$wpdb->update( self::table(), $row + [ 'venue_id' => $venue_id ?: (int) $existing->venue_id, 'status' => 'pending', 'note' => mb_substr( $note, 0, 500 ), 'decided_value' => null, 'sort_order' => $order, 'updated_at' => $now ], [ 'id' => $existing->id ] );
		return 'reopened';
	}

	/* ---------------------------------------------------------- decisions */

	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ) ?: null;
	}

	/**
	 * Approve or reject one item. `$edited` replaces the value (the Edit action).
	 *
	 * @return array{ok:bool,message:string,status?:string,now?:string,venue_id?:int}
	 */
	public static function decide( int $id, string $decision, ?string $edited = null ): array {
		global $wpdb;
		$item = self::get( $id );
		if ( ! $item || ! in_array( $decision, [ 'approve', 'reject' ], true ) ) {
			return [ 'ok' => false, 'message' => 'Unknown item.' ];
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$done = [ 'reviewed_by' => get_current_user_id(), 'reviewed_at' => $now, 'updated_at' => $now ];
		if ( 'reject' === $decision ) {
			$wpdb->update( self::table(), [ 'status' => 'rejected' ] + $done, [ 'id' => $id ] );
			return [ 'ok' => true, 'message' => 'Rejected.', 'status' => 'rejected' ];
		}

		if ( 'new_venue' === $item->kind ) {
			if ( null !== $edited && '' !== trim( $edited ) ) {
				$payload = json_decode( $item->payload, true ) ?: [];
				$payload['street'] = trim( $edited );
				$item->payload = (string) wp_json_encode( $payload );
				$wpdb->update( self::table(), [ 'payload' => $item->payload ], [ 'id' => $id ] );
			}
			if ( '' === trim( (string) ( json_decode( $item->payload, true )['street'] ?? '' ) ) ) {
				return [ 'ok' => false, 'message' => 'No street address yet. Press e (Edit), type it, then Enter.' ];
			}
			$venue_id = self::create_venue( $item );
			if ( ! $venue_id ) {
				return [ 'ok' => false, 'message' => 'Could not create the listing.' ];
			}
			$wpdb->update( self::table(), [ 'status' => 'approved', 'venue_id' => $venue_id ] + $done, [ 'id' => $id ] );
			// Facts found for this venue can now be reviewed against the new listing.
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET venue_id = %d WHERE city = %s AND venue_slug = %s AND kind = 'fact'", $venue_id, $item->city, $item->venue_slug ) );
			return [ 'ok' => true, 'message' => 'Listing created.', 'status' => 'approved', 'venue_id' => $venue_id, 'now' => get_edit_post_link( $venue_id, 'raw' ) ?: '' ];
		}

		if ( ! $item->venue_id || ! Venue::is_venue( (int) $item->venue_id ) ) {
			return [ 'ok' => false, 'message' => 'Approve the venue itself first.' ];
		}
		$value = json_decode( $item->value, true );
		if ( null !== $edited ) {
			$value = Enrich_Fact::from_text( $item->field, $edited );
			if ( null === $value ) {
				return [ 'ok' => false, 'message' => 'Could not read that value. Example: ' . Enrich_Fact::to_text( $item->field, json_decode( $item->value, true ) ) ];
			}
		}
		$source = in_array( $item->source_type, [ 'official_website', 'default' ], true ) ? $item->source_type : 'web_research';
		Pool_Data::apply_fact( (int) $item->venue_id, $item->field, $value, $source, (string) $item->source_url );
		$wpdb->update( self::table(), [ 'status' => 'approved', 'decided_value' => wp_json_encode( $value ) ] + $done, [ 'id' => $id ] );
		Stats::flush();
		return [ 'ok' => true, 'message' => null !== $edited ? 'Edited and approved.' : 'Approved.', 'status' => 'approved', 'now' => self::current_label( (int) $item->venue_id, $item->field, $value ) ];
	}

	private static function create_venue( object $item ): int {
		$p = json_decode( $item->payload, true ) ?: [];
		$source = (array) ( $p['sources'][0] ?? [] );
		$provider = 'google_places' === ( $source['source'] ?? '' ) ? 'google_places' : ( 'openstreetmap' === ( $source['source'] ?? '' ) ? 'openstreetmap' : '' );
		$row = [
			'name'        => (string) ( $p['name'] ?? json_decode( $item->value, true ) ),
			'street'      => (string) ( $p['street'] ?? '' ),
			'city'        => (string) ( $p['city'] ?? '' ),
			'state'       => (string) ( $p['state'] ?? '' ),
			'zip'         => (string) ( $p['zip'] ?? '' ),
			'lat'         => (string) ( $p['lat'] ?? '' ),
			'lng'         => (string) ( $p['lng'] ?? '' ),
			'phone'       => (string) ( $p['phone'] ?? '' ),
			'website'     => (string) ( $p['website'] ?? '' ),
			'venue_type'  => (string) ( $p['venue_type'] ?? '' ),
			'provider'    => $provider,
			'external_id' => $provider ? (string) ( $source['id'] ?? '' ) : '',
			'source_url'  => (string) ( $source['url'] ?? '' ),
		];
		return Importer::create_venue( $row, $provider ?: 'web_research', true );
	}

	public static function ajax_decide(): void {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( Moderation::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Not allowed.' ], 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$edited = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : null;
		$result = self::decide( $id, $decision, $edited );
		$result['ok'] ? wp_send_json_success( $result ) : wp_send_json_error( $result );
	}

	/* ------------------------------------------------- current site values */

	/** What the site says now for the thing a fact is about, in the same words as the fact. */
	public static function current_label( int $venue_id, string $field, $value ): string {
		$v = $venue_id ? Venue::get( $venue_id ) : null;
		if ( ! $v ) {
			return '—';
		}
		$state = static fn( string $tax, string $slug ): string => [ 'yes' => 'Yes', 'no' => 'No' ][ $v->state_of( $tax, $slug ) ] ?? 'Unknown';
		switch ( $field ) {
			case 'table_count':
				$n = $v->count( 'number-of-tables' );
				return null === $n ? 'Unknown' : sprintf( '%d tables', $n );
			case 'table_sizes':
				[ $count_field, $term ] = Enrich_Fact::SIZES[ $value['size'] ] ?? [ '', null ];
				if ( null === $term ) {
					return $v->field( 'equipment-notes' ) ?: 'Unknown';
				}
				$n = $v->count( $count_field );
				return $state( 'table-size', $term ) . ( null !== $n ? sprintf( ', %d tables', $n ) : '' );
			case 'table_brands':
				$slug = sanitize_title( (string) $value );
				return in_array( $slug, Enrich_Fact::BRAND_TERMS, true ) ? $state( 'table-brand', $slug ) : ( $v->field( 'table-models' ) ?: 'Unknown' );
			case 'pricing_model':
				$out = $state( 'pool-pricing', $value['model'] );
				if ( 'hourly' === $value['model'] && '' !== $v->field( 'hourly-rate' ) ) {
					$out .= ', $' . $v->field( 'hourly-rate' ) . ' an hour';
				} elseif ( 'per-game' === $value['model'] && '' !== $v->field( 'game-price' ) ) {
					$out .= ', $' . $v->field( 'game-price' ) . ' a game';
				} elseif ( 'free-pool' === $value['model'] && '' !== $v->field( 'pricing-notes' ) ) {
					$out .= ' (' . $v->field( 'pricing-notes' ) . ')';
				}
				return $out;
			case 'league_nights':
				$leagues = Play::leagues( $venue_id );
				return $leagues ? implode( '; ', array_map( static fn( $l ) => $l['name'] . ( $l['day'] ? ' (' . $l['day'] . ')' : '' ), $leagues ) ) : 'None listed';
			case 'age_policy':
				$on = array_filter( array_keys( Enrich_Fact::AGES ), static fn( $s ) => 'yes' === $v->state_of( Pool_Schema::TAX_AMENITY, $s ) );
				return $on ? implode( ', ', array_map( static fn( $s ) => Enrich_Fact::AGES[ $s ], $on ) ) : 'Unknown';
			case 'food':
				return $state( Pool_Schema::TAX_AMENITY, 'food' );
			case 'full_bar':
				return $state( Pool_Schema::TAX_AMENITY, 'full-bar' );
			case 'venue_status':
				return Pool_Schema::VENUE_STATUSES[ $v->status() ] ?? 'Open';
		}
		return '';
	}

	/* --------------------------------------------------------- admin page */

	public static function pending_count(): int {
		global $wpdb;
		return self::exists() ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE status = 'pending'" ) : 0;
	}

	public static function page_url( array $args = [] ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGE ) );
	}

	public static function menu(): void {
		$count = self::pending_count();
		$label = 'Review' . ( $count ? ' <span class="awaiting-mod">' . number_format_i18n( $count ) . '</span>' : '' );
		add_submenu_page( Moderation::MENU, 'Review web research', $label, Moderation::CAP, self::PAGE, [ __CLASS__, 'render' ] );
	}

	/** @return array<int,object> venues with pending items: city, venue_slug, n */
	private static function queue( string $city ): array {
		global $wpdb;
		$where = "status = 'pending'" . ( $city ? $wpdb->prepare( ' AND city = %s', $city ) : '' );
		return $wpdb->get_results( 'SELECT city, venue_slug, COUNT(*) AS n, MIN(sort_order) AS o, MAX(venue_id) AS vid FROM ' . self::table() . " WHERE {$where} GROUP BY city, venue_slug ORDER BY city, o, venue_slug" );
	}

	public static function render(): void {
		global $wpdb;
		if ( ! current_user_can( Moderation::CAP ) ) {
			return;
		}
		if ( ! self::exists() ) {
			echo '<div class="wrap"><h1>Review web research</h1><p>Nothing imported yet. Run <code>wp ppn enrich import &lt;city&gt;</code>.</p></div>';
			return;
		}
		$cities = $wpdb->get_col( 'SELECT DISTINCT city FROM ' . self::table() . ' ORDER BY city' );
		$city = isset( $_GET['city'] ) ? sanitize_title( wp_unslash( $_GET['city'] ) ) : '';
		$queue = self::queue( $city );
		$slug = isset( $_GET['venue'] ) ? sanitize_title( wp_unslash( $_GET['venue'] ) ) : '';
		$pos = 0;
		foreach ( $queue as $i => $q ) {
			if ( $q->venue_slug === $slug && ( ! $city || $q->city === $city ) ) {
				$pos = $i;
			}
		}
		$current = $queue[ $pos ] ?? null;
		if ( $slug && ( ! $current || $current->venue_slug !== $slug ) ) {
			// The venue asked for has nothing pending: still show it, read-only for decided rows.
			$current = (object) [ 'city' => $city ?: (string) $wpdb->get_var( $wpdb->prepare( 'SELECT city FROM ' . self::table() . ' WHERE venue_slug = %s LIMIT 1', $slug ) ), 'venue_slug' => $slug, 'n' => 0 ];
		}
		$next = $queue[ $pos + 1 ] ?? null;
		$prev = $pos > 0 ? $queue[ $pos - 1 ] : null;
		$pending_total = array_sum( array_map( static fn( $q ) => (int) $q->n, $queue ) );

		echo '<div class="wrap ppn-review"><h1>Review web research</h1>';
		self::styles();
		echo '<form method="get" class="ppn-review-bar"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><label>City <select name="city" onchange="this.form.submit()"><option value="">All cities</option>';
		foreach ( $cities as $c ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $c ), selected( $city, $c, false ), esc_html( ucwords( str_replace( '-', ' ', $c ) ) ) );
		}
		echo '</select></label> ';
		printf( '<span>%s pending in %s venues</span>', esc_html( number_format_i18n( $pending_total ) ), esc_html( number_format_i18n( count( $queue ) ) ) );
		echo ' <span class="ppn-keys">Keys: <kbd>j</kbd>/<kbd>k</kbd> move · <kbd>a</kbd> approve · <kbd>r</kbd> reject · <kbd>e</kbd> edit · <kbd>n</kbd> next venue · <kbd>p</kbd> previous</span></form>';

		if ( ! $current ) {
			echo '<p class="ppn-done">Nothing waiting for review. Import a city with <code>wp ppn enrich import &lt;city&gt;</code>.</p></div>';
			return;
		}
		$items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE city = %s AND venue_slug = %s ORDER BY kind = 'new_venue' DESC, status = 'pending' DESC, id", $current->city, $current->venue_slug ) );
		$venue_id = (int) max( array_map( static fn( $r ) => (int) $r->venue_id, $items ?: [ (object) [ 'venue_id' => 0 ] ] ) );
		$nav = static fn( $q ) => self::page_url( array_filter( [ 'city' => $city, 'venue' => $q->venue_slug ] ) );

		echo '<div class="ppn-review-venue">';
		$new = array_values( array_filter( $items, static fn( $r ) => 'new_venue' === $r->kind ) );
		if ( $new ) {
			$p = json_decode( $new[0]->payload, true ) ?: [];
			printf( '<h2>%s <span class="ppn-badge">New venue</span></h2>', esc_html( (string) ( $p['name'] ?? '' ) ) );
		} else {
			$v = Venue::get( $venue_id );
			printf( '<h2>%s</h2>', $v ? '<a href="' . esc_url( get_edit_post_link( $venue_id ) ?: '' ) . '">' . esc_html( $v->name() ) . '</a> <a class="ppn-small" href="' . esc_url( $v->url() ) . '" target="_blank" rel="noopener">view</a>' : esc_html( $current->venue_slug ) );
			if ( $v ) {
				printf( '<p class="ppn-meta">%s%s</p>', esc_html( $v->address() ), $v->website() ? ' · <a href="' . esc_url( $v->website() ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', rtrim( $v->website(), '/' ) ) ) . '</a>' : '' );
			}
		}
		printf( '<p class="ppn-meta">%s · venue %d of %d%s%s</p>', esc_html( ucwords( str_replace( '-', ' ', $current->city ) ) ), $pos + 1, max( 1, count( $queue ) ), $prev ? ' · <a id="ppn-prev" href="' . esc_url( $nav( $prev ) ) . '">← previous</a>' : '', $next ? ' · <a id="ppn-next" href="' . esc_url( $nav( $next ) ) . '">next venue →</a>' : '' );

		echo '<table class="widefat ppn-review-table"><thead><tr><th>Field</th><th>On the site now</th><th>Found</th><th>Source</th><th>Decision</th></tr></thead><tbody>';
		foreach ( $items as $r ) {
			self::row( $r, $venue_id );
		}
		echo '</tbody></table></div>';
		printf( '<p class="description">Approving writes the value to the listing with the source link as its provenance. Quotes are for your review only and are never shown on the site. Only <strong>stated</strong> facts are meant for publishing; implied ones are flagged.</p>' );
		self::script( $next ? $nav( $next ) : '', $prev ? $nav( $prev ) : '' );
		echo '</div>';
	}

	private static function row( object $r, int $venue_id ): void {
		$value = json_decode( $r->value, true );
		$flags = json_decode( $r->flags, true ) ?: [];
		$payload = json_decode( $r->payload, true ) ?: [];
		if ( 'new_venue' === $r->kind ) {
			$field = 'New listing';
			$found = esc_html( implode( ', ', array_filter( [ $payload['street'] ?? '', $payload['city'] ?? '', $payload['state'] ?? '' ] ) ) );
			$found .= ! empty( $payload['phone'] ) ? '<br>' . esc_html( $payload['phone'] ) : '';
			$found .= ! empty( $payload['website'] ) ? '<br><a href="' . esc_url( $payload['website'] ) . '" target="_blank" rel="noopener">' . esc_html( $payload['website'] ) . '</a>' : '';
			$found .= '<br><span class="ppn-small">Type: ' . esc_html( Pool_Schema::VENUE_TYPES[ $payload['venue_type'] ?? '' ] ?? 'not known' ) . '</span>';
			$now = 'Not listed';
			$editable = (string) ( $payload['street'] ?? '' );
		} else {
			$field = Enrich_Fact::FIELDS[ $r->field ] ?? $r->field;
			$found = '<strong>' . esc_html( Enrich_Fact::label( $r->field, $value ) ) . '</strong>';
			$now = esc_html( self::current_label( $venue_id, $r->field, $value ) );
			$editable = Enrich_Fact::to_text( $r->field, null !== $r->decided_value ? json_decode( $r->decided_value, true ) : $value );
			if ( null !== $r->decided_value && $r->decided_value !== $r->value ) {
				$found .= '<br><span class="ppn-small">Approved as: ' . esc_html( Enrich_Fact::label( $r->field, json_decode( $r->decided_value, true ) ) ) . '</span>';
			}
		}
		$conf = '<span class="ppn-conf ppn-' . esc_attr( $r->confidence ) . '">' . esc_html( $r->confidence ) . '</span>';
		foreach ( $flags as $f ) {
			$conf .= ' <span class="ppn-flag">' . esc_html( $f ) . '</span>';
		}
		foreach ( (array) ( $payload['conflicts'] ?? [] ) as $c ) {
			$conf .= '<br><span class="ppn-small">Other source says ' . esc_html( Enrich_Fact::FIELDS[ $r->field ] ?? null ? Enrich_Fact::label( $r->field, Enrich_Fact::clean_value( $r->field, $c['value'] ?? null ) ?? $value ) : '' ) . ( ! empty( $c['source_url'] ) ? ' (<a href="' . esc_url( $c['source_url'] ) . '" target="_blank" rel="noopener">source</a>)' : '' ) . '</span>';
		}
		if ( $r->note ) {
			$conf .= '<br><span class="ppn-flag ppn-flag-note">' . esc_html( $r->note ) . '</span>';
		}
		$source = $r->source_url ? '<a href="' . esc_url( $r->source_url ) . '" target="_blank" rel="noopener">' . esc_html( mb_strimwidth( preg_replace( '#^https?://(www\.)?#', '', $r->source_url ), 0, 48, '…' ) ) . '</a>' : '';
		$source .= ! empty( $payload['evidence'] ) ? ' · <a href="' . esc_url( $payload['evidence'] ) . '" target="_blank" rel="noopener">evidence</a>' : '';
		$source .= $r->quote ? '<blockquote>' . esc_html( $r->quote ) . '</blockquote>' : '';
		$source .= '<span class="ppn-small">Checked ' . esc_html( (string) $r->checked_at ) . '</span>';
		$same = 'fact' === $r->kind && 'pending' === $r->status && self::matches_current( $now, Enrich_Fact::label( $r->field, $value ) );
		printf(
			'<tr class="ppn-item ppn-status-%1$s" data-id="%2$d" data-kind="%11$s" data-edit="%3$s" tabindex="-1"><td>%4$s</td><td>%5$s%6$s</td><td>%7$s<div class="ppn-conf-wrap">%8$s</div></td><td class="ppn-source">%9$s</td><td class="ppn-actions">%10$s</td></tr>',
			esc_attr( $r->status ),
			(int) $r->id,
			esc_attr( $editable ),
			esc_html( $field ),
			'<span class="ppn-now">' . $now . '</span>',
			$same ? '<br><span class="ppn-small">Same as now; approving records the source.</span>' : '',
			$found,
			$conf,
			$source,
			'pending' === $r->status
				? '<button type="button" class="button button-primary" data-do="approve">Approve</button> <button type="button" class="button" data-do="reject">Reject</button>' . ( '' !== $editable || 'new_venue' === $r->kind ? ' <button type="button" class="button-link" data-do="edit">' . ( 'new_venue' === $r->kind ? 'Edit address' : 'Edit' ) . '</button>' : '' ) . '<div class="ppn-msg"></div>'
				: '<span class="ppn-decided">' . esc_html( ucfirst( $r->status ) ) . '</span>',
			esc_attr( $r->kind )
		);
	}

	private static function matches_current( string $now, string $found ): bool {
		$now = strtolower( html_entity_decode( $now ) );
		return 'yes' === $now || str_starts_with( $now, 'yes,' ) || $now === strtolower( $found );
	}

	private static function styles(): void {
		echo '<style>
		.ppn-review-bar{display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin:12px 0}
		.ppn-keys{color:#646970} .ppn-keys kbd{font-size:11px}
		.ppn-review-venue h2{margin:8px 0 2px} .ppn-meta{margin:2px 0;color:#50575e}
		.ppn-badge{background:#2271b1;color:#fff;border-radius:3px;padding:1px 6px;font-size:12px;vertical-align:middle}
		.ppn-review-table{margin-top:12px} .ppn-review-table td{vertical-align:top}
		.ppn-review-table td:nth-child(1){width:110px;font-weight:600} .ppn-review-table td:nth-child(5){width:210px}
		.ppn-item.ppn-selected{outline:2px solid #2271b1;outline-offset:-2px;background:#f0f6fc}
		.ppn-status-approved{background:#edfaef} .ppn-status-rejected{background:#fcf0f1;color:#787c82}
		.ppn-source blockquote{margin:4px 0;padding-left:8px;border-left:3px solid #c3c4c7;color:#3c434a;font-style:italic}
		.ppn-small{color:#646970;font-size:12px}
		.ppn-conf{display:inline-block;margin-top:4px;font-size:11px;text-transform:uppercase;border-radius:3px;padding:0 5px}
		.ppn-stated{background:#d1e7dd;color:#0a3622} .ppn-implied{background:#fff3cd;color:#664d03}
		.ppn-flag{display:inline-block;margin-top:4px;font-size:11px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;padding:0 5px}
		.ppn-flag-note{background:#fcf9e8;border-color:#dba617}
		.ppn-msg{font-size:12px;margin-top:4px} .ppn-msg.err{color:#b32d2e}
		.ppn-edit-input{width:100%;margin-top:6px}
		.ppn-done{font-size:15px}
		</style>';
	}

	private static function script( string $next, string $prev ): void {
		$data = [
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE ),
			'next'  => $next,
			'prev'  => $prev,
		];
		?>
		<script>
		(function () {
			var cfg = <?php echo wp_json_encode( $data ); ?>;
			var rows = Array.prototype.slice.call(document.querySelectorAll('.ppn-item'));
			var sel = -1;
			function pending() { return rows.filter(function (r) { return r.classList.contains('ppn-status-pending'); }); }
			function select(i) {
				if (!rows.length) return;
				sel = Math.max(0, Math.min(rows.length - 1, i));
				rows.forEach(function (r) { r.classList.remove('ppn-selected'); });
				rows[sel].classList.add('ppn-selected');
				rows[sel].scrollIntoView({ block: 'nearest' });
			}
			function nextPending() {
				var p = pending();
				if (p.length) { select(rows.indexOf(p[0])); } else if (cfg.next) { msgAll('All done here. Press n for the next venue.'); }
			}
			function msgAll(t) { var b = document.querySelector('.ppn-done-msg'); if (!b) { b = document.createElement('p'); b.className = 'ppn-done-msg ppn-done'; document.querySelector('.ppn-review-table').after(b); } b.textContent = t; }
			function send(row, decision, value) {
				var msg = row.querySelector('.ppn-msg');
				var body = new URLSearchParams({ action: 'ppn_enrich_decide', _ajax_nonce: cfg.nonce, id: row.dataset.id, decision: decision });
				if (value !== undefined) body.append('value', value);
				msg.className = 'ppn-msg'; msg.textContent = 'Saving…';
				fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); }).then(function (res) {
					var d = res.data || {};
					if (!res.success) { msg.className = 'ppn-msg err'; msg.textContent = d.message || 'Failed.'; return; }
					row.classList.remove('ppn-status-pending'); row.classList.add('ppn-status-' + d.status);
					row.querySelector('.ppn-actions').innerHTML = '<span class="ppn-decided">' + (d.status === 'approved' ? 'Approved' : 'Rejected') + '</span>';
					if (d.now && d.venue_id) { row.querySelector('.ppn-now').innerHTML = '<a href="' + d.now + '">Listing #' + d.venue_id + '</a>'; }
					else if (d.now) { row.querySelector('.ppn-now').textContent = d.now; }
					if (d.venue_id) { setTimeout(function () { location.reload(); }, 600); return; }
					nextPending();
				}).catch(function () { msg.className = 'ppn-msg err'; msg.textContent = 'Network error.'; });
			}
			function edit(row) {
				if (!row.dataset.edit && row.dataset.kind !== 'new_venue') return;
				var cell = row.querySelector('.ppn-actions');
				if (cell.querySelector('input')) { cell.querySelector('input').focus(); return; }
				var input = document.createElement('input');
				input.type = 'text'; input.className = 'ppn-edit-input'; input.value = row.dataset.edit;
				if (row.dataset.kind === 'new_venue') input.placeholder = 'Street address, e.g. 123 S Main Ave';
				input.title = 'Enter saves and approves, Esc cancels';
				cell.appendChild(input); input.focus(); input.select();
				input.addEventListener('keydown', function (e) {
					e.stopPropagation();
					if (e.key === 'Enter') { e.preventDefault(); send(row, 'approve', input.value); }
					if (e.key === 'Escape') { input.remove(); row.focus(); }
				});
			}
			rows.forEach(function (row, i) {
				row.addEventListener('click', function (e) {
					var b = e.target.closest('[data-do]');
					select(i);
					if (!b) return;
					if (b.dataset.do === 'edit') edit(row); else send(row, b.dataset.do);
				});
			});
			document.addEventListener('keydown', function (e) {
				if (e.target.matches('input, textarea, select') || e.metaKey || e.ctrlKey || e.altKey) return;
				var row = rows[sel];
				switch (e.key) {
					case 'j': case 'ArrowDown': select(sel + 1); e.preventDefault(); break;
					case 'k': case 'ArrowUp': select(sel - 1); e.preventDefault(); break;
					case 'a': if (row && row.classList.contains('ppn-status-pending')) send(row, 'approve'); break;
					case 'r': if (row && row.classList.contains('ppn-status-pending')) send(row, 'reject'); break;
					case 'e': if (row && row.classList.contains('ppn-status-pending')) { e.preventDefault(); edit(row); } break;
					case 'n': if (cfg.next) location.href = cfg.next; break;
					case 'p': if (cfg.prev) location.href = cfg.prev; break;
				}
			});
			nextPending();
		})();
		</script>
		<?php
	}
}
