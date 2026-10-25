<?php
/**
 * PlayPoolNation > Stats: listing clicks and venue page views from Tracking.
 * Totals for 7, 30 or 90 days, top venues by calls and directions, top cities,
 * traffic sources and devices, a page per venue (the basis of an owner report)
 * and a CSV export for a date range, for all venues or one.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Event_Data;

defined( 'ABSPATH' ) || exit;

final class Venue_Stats {

	public const PAGE = 'ppn-stats';
	private const CAP = 'manage_options';
	private const RANGES = [ 7, 30, 90 ];

	private const LABELS = [
		'view'         => 'Page views',
		'call'         => 'Calls',
		'directions'   => 'Directions',
		'website'      => 'Website visits',
		'save'         => 'Saves',
		'share'        => 'Shares',
		'suggest_edit' => 'Suggested edits',
		'claim'        => 'Claim clicks',
	];

	public static function boot(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 30 );
		add_action( 'admin_post_ppn_stats_csv', [ __CLASS__, 'csv' ] );
	}

	public static function menu(): void {
		add_submenu_page( Moderation::MENU, 'Stats', 'Stats', self::CAP, self::PAGE, [ __CLASS__, 'page' ] );
	}

	private static function since( int $days ): string {
		return gmdate( 'Y-m-d 00:00:00', time() - ( $days - 1 ) * DAY_IN_SECONDS );
	}

	/* ----------------------------------------------------------------- page */

	public static function page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'playpoolnation-core' ) );
		}
		$days = isset( $_GET['days'] ) && in_array( (int) $_GET['days'], self::RANGES, true ) ? (int) $_GET['days'] : 30; // phpcs:ignore WordPress.Security.NonceVerification
		$venue = isset( $_GET['venue'] ) ? absint( $_GET['venue'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="wrap"><h1>' . esc_html( $venue ? 'Stats: ' . get_the_title( $venue ) : 'Stats' ) . '</h1>';
		echo '<p>Clicks on listing buttons and venue page views, counted without cookies or personal data. Staff who are signed in are not counted. Times are UTC.</p>';
		self::range_links( $days, $venue );
		if ( $venue ) {
			self::venue_report( $venue, $days );
		} else {
			self::overview( $days );
		}
		self::export_form( $venue );
		echo '</div>';
	}

	private static function range_links( int $days, int $venue ): void {
		$links = [];
		foreach ( self::RANGES as $d ) {
			$url = add_query_arg( array_filter( [ 'page' => self::PAGE, 'days' => $d, 'venue' => $venue ?: null ] ), admin_url( 'admin.php' ) );
			$links[] = $d === $days ? '<strong>Last ' . $d . ' days</strong>' : '<a href="' . esc_url( $url ) . '">Last ' . $d . ' days</a>';
		}
		if ( $venue ) {
			$links[] = '<a href="' . esc_url( add_query_arg( [ 'page' => self::PAGE, 'days' => $days ], admin_url( 'admin.php' ) ) ) . '">All venues</a>';
		}
		echo '<p>' . implode( ' | ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function overview( int $days ): void {
		global $wpdb;
		$events = Tracking::table( Tracking::EVENTS_TABLE );
		$since = self::since( $days );
		self::totals( self::type_counts( $since, 0 ) );

		$top = self::top_venues( $since );
		$rows = [];
		foreach ( $top as $r ) {
			$url = add_query_arg( [ 'page' => self::PAGE, 'days' => $days, 'venue' => (int) $r->venue_id ], admin_url( 'admin.php' ) );
			$rows[] = [ '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( (int) $r->venue_id ) ) . '</a>', (int) $r->calls, (int) $r->directions, (int) $r->website, (int) $r->total ];
		}
		self::table( 'Top venues by calls and directions', [ 'Venue', 'Calls', 'Directions', 'Website', 'All clicks' ], $rows );

		$cities = $wpdb->get_results( $wpdb->prepare(
			"SELECT city, COUNT(*) AS n FROM {$events} WHERE created_at >= %s AND city <> '' GROUP BY city ORDER BY n DESC LIMIT 15",
			$since
		) );
		self::table( 'Top cities', [ 'City', 'Clicks' ], array_map( static fn( $r ) => [ esc_html( self::city_name( $r->city ) ), (int) $r->n ], $cities ) );
		self::splits( $since, 0 );
	}

	/**
	 * Venues with the most calls and directions since a time. MySQL cannot order by
	 * an expression of SELECT aliases of aggregates (error 1247), so the order
	 * repeats the aggregates.
	 *
	 * @return array<int,object{venue_id:string,calls:string,directions:string,website:string,total:string}>
	 */
	public static function top_venues( string $since, int $limit = 25 ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT venue_id, SUM(event_type = 'call') AS calls, SUM(event_type = 'directions') AS directions, SUM(event_type = 'website') AS website, COUNT(*) AS total
			 FROM " . Tracking::table( Tracking::EVENTS_TABLE ) . " WHERE created_at >= %s GROUP BY venue_id
			 ORDER BY SUM(event_type IN ('call', 'directions')) DESC, COUNT(*) DESC LIMIT %d",
			$since,
			$limit
		) );
	}

	private static function venue_report( int $venue, int $days ): void {
		global $wpdb;
		$events = Tracking::table( Tracking::EVENTS_TABLE );
		$since = self::since( $days );
		echo '<p><a href="' . esc_url( (string) get_permalink( $venue ) ) . '">View the venue page</a></p>';
		self::totals( self::type_counts( $since, $venue ) );
		self::splits( $since, $venue );

		$detail = $wpdb->get_results( $wpdb->prepare(
			"SELECT event_type, context, device, source, COUNT(*) AS n FROM {$events} WHERE venue_id = %d AND created_at >= %s
			 GROUP BY event_type, context, device, source ORDER BY event_type, n DESC",
			$venue,
			$since
		) );
		self::table( 'Every click by where, device and source', [ 'Click', 'Where', 'Device', 'Source', 'Count' ], array_map(
			static fn( $r ) => [ esc_html( self::LABELS[ $r->event_type ] ?? $r->event_type ), esc_html( str_replace( '_', ' ', $r->context ) ), esc_html( $r->device ), esc_html( $r->source ), (int) $r->n ],
			$detail
		) );

		self::table( 'By month', array_merge( [ 'Month' ], array_values( self::LABELS ) ), self::months( $venue ) );
	}

	/** Monthly totals for a venue: rolled-up months plus the raw months still kept. */
	private static function months( int $venue ): array {
		global $wpdb;
		$by = [];
		$add = static function ( string $month, string $type, int $n ) use ( &$by ): void {
			$by[ $month ][ $type ] = ( $by[ $month ][ $type ] ?? 0 ) + $n;
		};
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT month, event_type, SUM(count) AS n FROM ' . Tracking::table( Tracking::MONTHLY_TABLE ) . ' WHERE venue_id = %d GROUP BY month, event_type', $venue ) ) as $r ) {
			$add( substr( $r->month, 0, 7 ), $r->event_type, (int) $r->n );
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS month, event_type, COUNT(*) AS n FROM " . Tracking::table( Tracking::EVENTS_TABLE ) . ' WHERE venue_id = %d GROUP BY month, event_type', $venue ) ) as $r ) {
			$add( $r->month, $r->event_type, (int) $r->n );
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(day, '%%Y-%%m') AS month, SUM(views) AS n FROM " . Tracking::table( Tracking::VIEWS_TABLE ) . ' WHERE venue_id = %d GROUP BY month', $venue ) ) as $r ) {
			$add( $r->month, Event_Data::VIEW, (int) $r->n );
		}
		krsort( $by );
		$rows = [];
		foreach ( $by as $month => $counts ) {
			$row = [ esc_html( $month ) ];
			foreach ( array_keys( self::LABELS ) as $type ) {
				$row[] = (int) ( $counts[ $type ] ?? 0 );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/** @return array<string,int> event type (and 'view') => count */
	private static function type_counts( string $since, int $venue ): array {
		global $wpdb;
		$where = $venue ? $wpdb->prepare( ' AND venue_id = %d', $venue ) : '';
		$counts = array_fill_keys( array_keys( self::LABELS ), 0 );
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT event_type, COUNT(*) AS n FROM ' . Tracking::table( Tracking::EVENTS_TABLE ) . " WHERE created_at >= %s{$where} GROUP BY event_type", $since ) ) as $r ) {
			$counts[ $r->event_type ] = (int) $r->n;
		}
		$counts['view'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(views), 0) FROM ' . Tracking::table( Tracking::VIEWS_TABLE ) . " WHERE day >= %s{$where}", substr( $since, 0, 10 ) ) );
		return $counts;
	}

	private static function totals( array $counts ): void {
		$rows = [];
		foreach ( self::LABELS as $type => $label ) {
			$rows[] = [ esc_html( $label ), (int) ( $counts[ $type ] ?? 0 ) ];
		}
		self::table( 'Totals', [ '', 'Count' ], $rows );
	}

	private static function splits( string $since, int $venue ): void {
		global $wpdb;
		$where = $venue ? $wpdb->prepare( ' AND venue_id = %d', $venue ) : '';
		foreach ( [ 'source' => 'Traffic source', 'device' => 'Device', 'context' => 'Where the click happened' ] as $col => $title ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$col} AS k, COUNT(*) AS n FROM " . Tracking::table( Tracking::EVENTS_TABLE ) . " WHERE created_at >= %s{$where} GROUP BY {$col} ORDER BY n DESC", $since ) );
			self::table( $title, [ '', 'Clicks' ], array_map( static fn( $r ) => [ esc_html( str_replace( '_', ' ', $r->k ) ), (int) $r->n ], $rows ) );
		}
	}

	private static function city_name( string $slug ): string {
		$term = get_term_by( 'slug', $slug, Pool_Schema::TAX_REGION );
		return $term instanceof \WP_Term ? html_entity_decode( $term->name, ENT_QUOTES ) : $slug;
	}

	/** @param array<int,array<int,string|int>> $rows cells already escaped */
	private static function table( string $title, array $head, array $rows ): void {
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( ! $rows ) {
			echo '<p>Nothing counted yet.</p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr>';
		foreach ( $head as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . implode( '</td><td>', $row ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table>';
	}

	private static function export_form( int $venue ): void {
		echo '<h2>Export CSV</h2><form method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="ppn_stats_csv">';
		wp_nonce_field( 'ppn_stats_csv', '_wpnonce', false );
		printf(
			'<p><label>From <input type="date" name="from" value="%s" required></label> <label>To <input type="date" name="to" value="%s" required></label> <label>Venue ID (blank for all) <input type="number" name="venue" min="1" value="%s"></label> <button class="button button-primary">Download CSV</button></p></form>',
			esc_attr( gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ) ),
			esc_attr( gmdate( 'Y-m-d' ) ),
			$venue ? esc_attr( (string) $venue ) : ''
		);
		echo '<p class="description">One row per day, venue and kind of click (with where, device and source), plus page views per day.</p>';
	}

	/* ------------------------------------------------------------------ CSV */

	public static function csv(): void {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'ppn_stats_csv' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'playpoolnation-core' ) );
		}
		global $wpdb;
		$date = static fn( string $key, string $fallback ): string => preg_match( '#^\d{4}-\d{2}-\d{2}$#', (string) ( $_GET[ $key ] ?? '' ) ) ? (string) $_GET[ $key ] : $fallback; // phpcs:ignore WordPress.Security.NonceVerification
		$from = $date( 'from', gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ) );
		$to = $date( 'to', gmdate( 'Y-m-d' ) );
		$venue = absint( $_GET['venue'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$where = $venue ? $wpdb->prepare( ' AND venue_id = %d', $venue ) : '';
		$clicks = $wpdb->get_results( $wpdb->prepare(
			'SELECT DATE(created_at) AS day, venue_id, event_type, context, city, device, source, COUNT(*) AS n FROM ' . Tracking::table( Tracking::EVENTS_TABLE ) .
			" WHERE created_at >= %s AND created_at < %s{$where} GROUP BY day, venue_id, event_type, context, city, device, source ORDER BY day, venue_id, event_type",
			$from . ' 00:00:00',
			gmdate( 'Y-m-d', (int) strtotime( $to . ' +1 day UTC' ) ) . ' 00:00:00'
		), ARRAY_A );
		$views = $wpdb->get_results( $wpdb->prepare(
			'SELECT day, venue_id, views AS n FROM ' . Tracking::table( Tracking::VIEWS_TABLE ) . " WHERE day >= %s AND day <= %s{$where} ORDER BY day, venue_id",
			$from,
			$to
		), ARRAY_A );
		$name = 'playpoolnation-stats-' . $from . '-to-' . $to . ( $venue ? '-venue-' . $venue : '' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'date', 'venue_id', 'venue', 'event', 'where', 'city', 'device', 'source', 'count' ] );
		$titles = [];
		$title = static function ( int $id ) use ( &$titles ): string {
			// A leading = + - @ would make a spreadsheet run the name as a formula.
			return $titles[ $id ] ??= (string) preg_replace( '#^([=+\-@])#', "'$1", html_entity_decode( get_the_title( $id ), ENT_QUOTES ) );
		};
		foreach ( $views as $r ) {
			fputcsv( $out, [ $r['day'], $r['venue_id'], $title( (int) $r['venue_id'] ), Event_Data::VIEW, 'venue_page', '', '', '', $r['n'] ] );
		}
		foreach ( $clicks as $r ) {
			fputcsv( $out, [ $r['day'], $r['venue_id'], $title( (int) $r['venue_id'] ), $r['event_type'], $r['context'], $r['city'], $r['device'], $r['source'], $r['n'] ] );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
