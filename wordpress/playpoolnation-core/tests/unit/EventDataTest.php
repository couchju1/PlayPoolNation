<?php

use PHPUnit\Framework\TestCase;
use PlayPoolNation\Core\Helpers\Event_Data;
use PlayPoolNation\Core\Helpers\Html_Fix;

final class EventDataTest extends TestCase {

	private const OWN = 'playpoolnation.com';

	private function event( array $over = [] ): array {
		return array_merge( [ 'venue' => '1824', 'type' => 'call', 'where' => 'venue_page', 'path' => '/place/rack-city-billiards-sioux-falls-sd/', 'device' => 'mobile', 'ref' => 'www.google.com' ], $over );
	}

	/* ----------------------------------------------------------- source */

	public function test_referrer_hosts_become_buckets(): void {
		$cases = [
			'www.google.com'                          => 'google',
			'google.co.uk'                            => 'google',
			'www.google.com.au'                       => 'google',
			'com.google.android.googlequicksearchbox' => 'google',
			'www.bing.com'                            => 'bing',
			'l.facebook.com'                          => 'facebook',
			'm.facebook.com'                          => 'facebook',
			'com.facebook.katana'                     => 'facebook',
			'old.reddit.com'                          => 'reddit',
			'out.reddit.com'                          => 'reddit',
			''                                        => 'direct',
			'playpoolnation.com'                      => 'direct',
			'www.playpoolnation.com'                  => 'direct',
			'duckduckgo.com'                          => 'other',
			'googleadservices.com'                    => 'other',
			'notgoogle.com'                           => 'other',
			'bing.com.evil.example'                   => 'other',
			'reddit.com.example'                      => 'other',
		];
		foreach ( $cases as $host => $bucket ) {
			$this->assertSame( $bucket, Event_Data::source( $host, 'www.' . self::OWN ), $host );
		}
	}

	/* ------------------------------------------------------- validation */

	public function test_valid_event_is_normalized(): void {
		$this->assertSame(
			[ 'venue_id' => 1824, 'event_type' => 'call', 'context' => 'venue_page', 'path' => '/place/rack-city-billiards-sioux-falls-sd/', 'device' => 'mobile', 'source' => 'google' ],
			Event_Data::validate( $this->event(), self::OWN )
		);
	}

	public function test_query_string_is_dropped_and_view_is_a_venue_page_event(): void {
		$e = Event_Data::validate( $this->event( [ 'path' => '/places/?type=place&search_location=x#map', 'type' => 'view', 'where' => 'card', 'ref' => '' ] ), self::OWN );
		$this->assertSame( '/places/', $e['path'] );
		$this->assertSame( 'venue_page', $e['context'] );
		$this->assertSame( 'direct', $e['source'] );
	}

	public function test_anything_outside_the_allow_lists_is_rejected(): void {
		$bad = [
			[ 'venue' => '0' ],
			[ 'venue' => '-3' ],
			[ 'venue' => '12abc' ],
			[ 'venue' => [ 1 ] ],
			[ 'type' => 'purchase' ],
			[ 'type' => 'CALL' ],
			[ 'where' => 'footer' ],
			[ 'device' => 'tablet' ],
			[ 'device' => '' ],
			[ 'path' => 'place/x/' ],
			[ 'path' => '//evil.example/x' ],
			[ 'path' => '/place/<script>/' ],
			[ 'path' => '/' . str_repeat( 'a', 210 ) ],
			[ 'ref' => 'https://www.google.com/search?q=x' ],
			[ 'ref' => 'bad host' ],
		];
		foreach ( $bad as $over ) {
			$this->assertNull( Event_Data::validate( $this->event( $over ), self::OWN ), json_encode( $over ) );
		}
		$this->assertNull( Event_Data::validate( [], self::OWN ) );
	}

	public function test_every_event_and_context_is_accepted(): void {
		foreach ( Event_Data::EVENTS as $type ) {
			foreach ( Event_Data::CONTEXTS as $where ) {
				$this->assertNotNull( Event_Data::validate( $this->event( [ 'type' => $type, 'where' => $where ] ), self::OWN ), "{$type} {$where}" );
			}
		}
	}

	/* ----------------------------------------------------------- rollup */

	public function test_rollup_cutoff_keeps_thirteen_whole_months(): void {
		$this->assertSame( '2025-09-01 00:00:00', Event_Data::rollup_cutoff( strtotime( '2026-10-25 08:00:00 UTC' ) ) );
		$this->assertSame( '2025-01-01 00:00:00', Event_Data::rollup_cutoff( strtotime( '2026-02-01 00:30:00 UTC' ) ) );
	}

	public function test_rollup_counts_events_and_views_by_month(): void {
		$rows = [
			[ 'venue_id' => '7', 'event_type' => 'call', 'context' => 'venue_page', 'city' => 'sioux-falls', 'device' => 'mobile', 'source' => 'google', 'created_at' => '2025-03-04 18:00:00' ],
			[ 'venue_id' => '7', 'event_type' => 'call', 'context' => 'venue_page', 'city' => 'sioux-falls', 'device' => 'mobile', 'source' => 'google', 'created_at' => '2025-03-29 02:00:00' ],
			[ 'venue_id' => '7', 'event_type' => 'call', 'context' => 'venue_page', 'city' => 'sioux-falls', 'device' => 'desktop', 'source' => 'google', 'created_at' => '2025-03-29 02:00:00' ],
			[ 'venue_id' => '7', 'event_type' => 'call', 'context' => 'venue_page', 'city' => 'sioux-falls', 'device' => 'mobile', 'source' => 'google', 'created_at' => '2025-04-01 00:00:00' ],
			[ 'venue_id' => '7', 'event_type' => 'view', 'context' => 'venue_page', 'day' => '2025-03-10', 'count' => '40' ],
			[ 'venue_id' => '7', 'event_type' => 'view', 'context' => 'venue_page', 'day' => '2025-03-11', 'count' => '2' ],
			[ 'venue_id' => '8', 'event_type' => 'save', 'created_at' => 'not a date' ],
		];
		$this->assertSame( [
			[ 'month' => '2025-03-01', 'venue_id' => 7, 'event_type' => 'call', 'context' => 'venue_page', 'city' => 'sioux-falls', 'device' => 'mobile', 'source' => 'google', 'count' => 2 ],
			[ 'month' => '2025-03-01', 'venue_id' => 7, 'event_type' => 'call', 'context' => 'venue_page', 'city' => 'sioux-falls', 'device' => 'desktop', 'source' => 'google', 'count' => 1 ],
			[ 'month' => '2025-04-01', 'venue_id' => 7, 'event_type' => 'call', 'context' => 'venue_page', 'city' => 'sioux-falls', 'device' => 'mobile', 'source' => 'google', 'count' => 1 ],
			[ 'month' => '2025-03-01', 'venue_id' => 7, 'event_type' => 'view', 'context' => 'venue_page', 'city' => '', 'device' => '', 'source' => '', 'count' => 42 ],
		], Event_Data::monthly( $rows ) );
	}

	/* -------------------------------------------------------- link marks */

	public function test_tracked_links_are_marked(): void {
		$html = '<a href="tel:+16052712951" rel="nofollow">Call</a>'
			. '<a href="https://www.google.com/maps/dir/?api=1&amp;destination=309%20S%20Bahnson" target="_blank">Directions</a>'
			. '<a href="https://playpoolnation.com/claim-listing/?listing_id=1824">Claim</a>'
			. '<a data-ppn-event="website" href="https://www.rackcitybilliards.com/">Site</a>'
			. '<a href="https://playpoolnation.com/place/x/">Venue</a>'
			. '<script>var t = \'<a href="tel:1">\';</script>';
		$out = Html_Fix::track_links( $html );
		$this->assertStringContainsString( '<a data-ppn-event="call" href="tel:+16052712951" rel="nofollow">', $out );
		$this->assertStringContainsString( '<a data-ppn-event="directions" href="https://www.google.com/maps/dir/', $out );
		$this->assertStringContainsString( '<a data-ppn-event="claim" href="https://playpoolnation.com/claim-listing/?listing_id=1824">', $out );
		$this->assertSame( 1, substr_count( $out, 'data-ppn-event="website"' ) );
		$this->assertStringContainsString( '<a href="https://playpoolnation.com/place/x/">Venue</a>', $out );
		$this->assertStringContainsString( '<script>var t = \'<a href="tel:1">\';</script>', $out );
	}
}
