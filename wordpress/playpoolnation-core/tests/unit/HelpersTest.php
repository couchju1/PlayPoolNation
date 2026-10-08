<?php

use PHPUnit\Framework\TestCase;
use PlayPoolNation\Core\Helpers\Address;
use PlayPoolNation\Core\Helpers\Dedupe;
use PlayPoolNation\Core\Helpers\Format;
use PlayPoolNation\Core\Helpers\Tri_State;

final class HelpersTest extends TestCase {

	/* ---------------------------------------------------------- address */

	public function test_parses_us_address(): void {
		$a = Address::parse( '309 S Bahnson Ave, Sioux Falls, SD 57103' );
		$this->assertSame( '309 S Bahnson Ave', $a['street'] );
		$this->assertSame( 'Sioux Falls', $a['city'] );
		$this->assertSame( 'SD', $a['state'] );
		$this->assertSame( '57103', $a['postal_code'] );
		$this->assertSame( 'US', $a['country'] );
	}

	public function test_parses_suite_country_and_plus_code(): void {
		$a = Address::parse( '773M+R8, 900 Gallatin Pike S, Madison, TN 37115, USA' );
		$this->assertSame( '900 Gallatin Pike S', $a['street'] );
		$this->assertSame( 'Madison', $a['city'] );
		$this->assertSame( 'TN', $a['state'] );

		$b = Address::parse( '12344 Gulf Fwy Ste A&B, Houston, TX 77034' );
		$this->assertSame( '12344 Gulf Fwy Ste A&B', $b['street'] );
		$this->assertSame( 'Houston', $b['city'] );
	}

	public function test_unparseable_address_guesses_nothing(): void {
		$a = Address::parse( 'Somewhere downtown' );
		$this->assertSame( '', $a['city'] );
		$this->assertSame( '', $a['state'] );
		$this->assertSame( 'Somewhere downtown', $a['street'] );
	}

	public function test_state_names(): void {
		$this->assertSame( 'South Dakota', Address::state_name( 'sd' ) );
		$this->assertSame( 'DC', Address::state_abbr( 'Washington, D.C.' ) );
		$this->assertSame( '', Address::state_abbr( 'Atlantis' ) );
	}

	/* ------------------------------------------------------- tri-state */

	public function test_unknown_is_not_no(): void {
		$this->assertSame( Tri_State::UNKNOWN, Tri_State::from_term( false, false ) );
		$this->assertSame( Tri_State::NO, Tri_State::from_term( false, true ) );
		$this->assertSame( Tri_State::YES, Tri_State::from_term( true, true ) );
	}

	public function test_counts_distinguish_blank_from_zero(): void {
		$this->assertNull( Tri_State::count( '' ) );
		$this->assertNull( Tri_State::count( null ) );
		$this->assertNull( Tri_State::count( 'about 10' ) );
		$this->assertSame( 0, Tri_State::count( '0' ) );
		$this->assertSame( 12, Tri_State::count( '12' ) );
		$this->assertSame( Tri_State::UNKNOWN, Tri_State::from_count( '' ) );
		$this->assertSame( Tri_State::NO, Tri_State::from_count( '0' ) );
		$this->assertSame( Tri_State::YES, Tri_State::from_count( '3' ) );
	}

	/* --------------------------------------------------------- format */

	public function test_venue_slug(): void {
		$this->assertSame( 'rack-city-billiards-sioux-falls-sd', Format::venue_slug( 'Rack City Billiards', 'Sioux Falls', 'SD' ) );
		$this->assertSame( 'jjs-billiards-and-darts-sioux-falls-sd', Format::venue_slug( "JJ's Billiards & Darts", 'Sioux Falls', 'SD' ) );
		// City is not repeated when the name already ends with it.
		$this->assertSame( 'k-and-k-billiards-miami-fl', Format::venue_slug( 'K&K Billiards Miami', 'Miami', 'FL' ) );
	}

	public function test_sizes_label(): void {
		$this->assertSame( "7' & 9'", Format::sizes_label( [ '9-foot', '7-foot' ] ) );
		$this->assertSame( "7', 8' & 9'", Format::sizes_label( [ '7-foot', '8-foot', '9-foot' ] ) );
		$this->assertSame( '', Format::sizes_label( [] ) );
	}

	public function test_money_and_clock(): void {
		$this->assertSame( '$12', Format::money( '12' ) );
		$this->assertSame( '$12.50', Format::money( '12.5' ) );
		$this->assertSame( '', Format::money( '' ) );
		$this->assertSame( '1 AM', Format::clock( 60 ) );
		$this->assertSame( '11:30 PM', Format::clock( 23 * 60 + 30 ) );
		$this->assertSame( 'Midnight', Format::clock( 0 ) );
		$this->assertSame( 19 * 60, Format::parse_time( '7:00 PM' ) );
		$this->assertSame( 19 * 60 + 30, Format::parse_time( '19:30' ) );
		$this->assertSame( 0, Format::parse_time( '12 AM' ) );
		$this->assertNull( Format::parse_time( 'after work' ) );
	}

	public function test_open_status(): void {
		$tz = new DateTimeZone( 'America/Chicago' );
		// Mon-Sun 14:00 to 02:00 next day (minutes from Monday 00:00).
		$ranges = [];
		for ( $d = 0; $d < 7; $d++ ) {
			$ranges[] = [ $d * 1440 + 14 * 60, $d * 1440 + 26 * 60 ];
		}
		$wed_10pm = new DateTimeImmutable( '2026-10-07 22:00', $tz ); // Wednesday
		$this->assertSame( [ 'state' => 'open', 'label' => 'Open until 2 AM' ], Format::open_status( $ranges, $wed_10pm ) );

		$wed_noon = new DateTimeImmutable( '2026-10-07 12:00', $tz );
		$this->assertSame( [ 'state' => 'closed', 'label' => 'Opens 2 PM' ], Format::open_status( $ranges, $wed_noon ) );

		$this->assertSame( 'unknown', Format::open_status( [], $wed_noon )['state'] );
		$this->assertSame( 'Open 24 hours', Format::open_status( [ [ 0, 10080 ] ], $wed_noon )['label'] );

		// Closed all day Monday, opens Tuesday.
		$tue_only = [ [ 1440 + 16 * 60, 1440 + 23 * 60 ] ];
		$mon = new DateTimeImmutable( '2026-10-05 12:00', $tz ); // Monday
		$this->assertSame( 'Opens Tue 4 PM', Format::open_status( $tue_only, $mon )['label'] );
	}

	/** The same cases run against ppnOpenStatus in tests/js/open-status.test.js. */
	public function test_open_status_fixtures(): void {
		$cases = json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/open-status.json' ), true );
		foreach ( $cases as $c ) {
			$now = ( new DateTimeImmutable( $c['now'] ) )->setTimezone( new DateTimeZone( $c['tz'] ) );
			$this->assertSame( [ 'state' => $c['state'], 'label' => $c['label'] ], Format::listing_open_status( $c['r'], $c['s'], $now ), $c['name'] );
		}
	}

	/* --------------------------------------------------------- dedupe */

	public function test_external_id_is_decisive(): void {
		$r = Dedupe::compare( [ 'name' => 'A', 'external_ids' => [ 'google_places' => 'X1' ] ], [ 'name' => 'Totally different', 'external_ids' => [ 'google_places' => 'X1' ] ] );
		$this->assertSame( Dedupe::MATCH, $r['class'] );
	}

	public function test_same_place_different_spelling_matches(): void {
		$a = [ 'name' => "JJ's Billiards & Darts", 'phone' => '(605) 335-7637', 'lat' => 43.5465, 'lng' => -96.7637 ];
		$b = [ 'name' => 'JJs Billiards and Darts', 'phone' => '605.335.7637', 'lat' => 43.54655, 'lng' => -96.76372 ];
		$this->assertSame( Dedupe::MATCH, Dedupe::compare( $a, $b )['class'] );
	}

	public function test_chain_in_another_city_is_distinct(): void {
		$a = [ 'name' => 'Clicks Billiards', 'website' => 'https://www.clicks.com/houston', 'lat' => 29.86, 'lng' => -95.53 ];
		$b = [ 'name' => 'Clicks Billiards', 'website' => 'https://www.clicks.com/', 'lat' => 32.80, 'lng' => -97.05 ];
		$this->assertSame( Dedupe::DISTINCT, Dedupe::compare( $a, $b )['class'] );
	}

	public function test_similar_name_nearby_without_phone_needs_review(): void {
		$a = [ 'name' => 'Corner Pocket', 'lat' => 41.2200, 'lng' => -95.9700 ];
		$b = [ 'name' => 'The Corner Pocket Bar', 'lat' => 41.2215, 'lng' => -95.9700 ];
		$this->assertSame( Dedupe::POSSIBLE, Dedupe::compare( $a, $b )['class'] );
	}

	public function test_normalizers(): void {
		// "$this->assertSame( 'k and k billiards', Dedupe::normalize_name( 'The K&K Billiards Bar' ) );", "and", "the" and generic words like "bar" carry no identity.
		$this->assertSame( 'k k billiards', Dedupe::normalize_name( 'The K&K Billiards Bar' ) );
		$this->assertSame( Dedupe::normalize_name( 'K and K Billiards' ), Dedupe::normalize_name( 'K&K Billiards' ) );
		$this->assertSame( '6053357637', Dedupe::normalize_phone( '+1 (605) 335-7637' ) );
		$this->assertSame( '', Dedupe::normalize_phone( '911' ) );
		$this->assertSame( 'bigsbar.com', Dedupe::domain( 'http://www.bigsbar.com/billiards' ) );
		$this->assertSame( '', Dedupe::domain( 'https://www.facebook.com/RackCity605/' ) );
	}
}
