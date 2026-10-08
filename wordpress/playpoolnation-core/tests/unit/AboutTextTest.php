<?php

use PHPUnit\Framework\TestCase;
use PlayPoolNation\Core\Helpers\About_Text;

final class AboutTextTest extends TestCase {

	private function text( array $facts ): string {
		return implode( ' ', About_Text::build( $facts )['sentences'] );
	}

	private function claim( array $facts ): string {
		$c = About_Text::build( $facts )['claim'];
		return $c ? $c['lead'] . ' ' . $c['action'] . $c['rest'] : '';
	}

	/** Same voice rules for every output: no dashes, no semicolons. */
	private function assertPlain( string $text ): void {
		$this->assertDoesNotMatchRegularExpression( '/[\x{2013}\x{2014};]| - /u', $text );
	}

	public function test_full_data(): void {
		$f = [
			'name' => 'Rack City Billiards', 'type' => 'pool-halls', 'street' => '309 S Bahnson Ave', 'city_state' => 'Sioux Falls, SD',
			'tables' => 18, 'size_counts' => [ '7-foot' => 16, '9-foot' => 2 ], 'sizes' => [ '7-foot', '9-foot' ],
			'brands' => [ 'Diamond', 'Valley' ], 'games' => [ 'Darts' ], 'pricing' => [ 'hourly' ], 'hourly_rate' => '$12',
			'leagues' => true, 'photo' => true, 'claimed' => false,
		];
		$text = $this->text( $f );
		$this->assertSame(
			"Rack City Billiards is a pool hall at 309 S Bahnson Ave in Sioux Falls, SD. It has 18 tables. Most are 7-foot, plus two 9-foot tables. They're Diamond and Valley tables. Pool is \$12 an hour. There's darts too. League nights are listed below.",
			$text
		);
		$this->assertPlain( $text );
		$this->assertSame( 'Own Rack City Billiards? Claim it to keep it current.', $this->claim( $f ) );
	}

	public function test_partial_data_lists_only_unknowns(): void {
		$f = [ 'name' => 'Rack City Billiards', 'type' => 'pool-halls', 'city_state' => 'Sioux Falls, SD', 'tables' => 18, 'size_counts' => [ '7-foot' => 16, '9-foot' => 2 ], 'brands' => [ 'Diamond' ] ];
		$this->assertSame( 'Rack City Billiards is a pool hall in Sioux Falls, SD. It has 18 tables. Most are 7-foot, plus two 9-foot tables. They\'re Diamond tables.', $this->text( $f ) );
		$this->assertSame( 'Own Rack City Billiards? Claim it to add hourly rates, league nights and photos.', $this->claim( $f ) );

		$sizes_only = [ 'name' => 'Q Lounge', 'type' => 'billiards-lounge', 'sizes' => [ '9-foot', '7-foot' ] ];
		$this->assertSame( "Q Lounge is a billiards lounge. The tables are 7-foot and 9-foot. We don't know how many there are yet.", $this->text( $sizes_only ) );

		$even = [ 'name' => 'Cue Club', 'type' => 'pool-halls', 'tables' => 8, 'size_counts' => [ '8-foot' => 4, '9-foot' => 4 ] ];
		$this->assertStringContainsString( "It has eight tables. That's four 8-foot and four 9-foot.", $this->text( $even ) );

		$total_only = [ 'name' => 'Bigs Bar', 'type' => 'bars-with-pool-tables', 'tables' => 4, 'pricing' => [ 'hourly' ] ];
		$this->assertSame( "Bigs Bar is a bar with pool tables. It has four tables. We don't know the sizes yet. You pay for pool by the hour.", $this->text( $total_only ) );
	}

	public function test_nothing_known(): void {
		$f = [ 'name' => 'Amsterdam Billiards', 'type' => 'pool-halls', 'street' => '110 E 11th St', 'city_state' => 'New York, NY' ];
		$text = $this->text( $f );
		$this->assertSame( "Amsterdam Billiards is a pool hall at 110 E 11th St in New York, NY. We don't know the table count or sizes yet.", $text );
		$this->assertPlain( $text );
		$this->assertSame( 'Own Amsterdam Billiards? Claim it to add the table count, table sizes, hourly rates, league nights and photos.', $this->claim( $f ) );
		$this->assertNull( About_Text::build( $f + [ 'claimed' => true ] )['claim'] );
	}

	public function test_bar_versus_hall(): void {
		$this->assertSame( 'The Dugout is a sports bar on Main Street in Fargo, ND with pool tables.', About_Text::identity( [ 'name' => 'The Dugout', 'type' => 'sports-bar', 'street' => 'Main Street', 'city_state' => 'Fargo, ND' ] ) );
		$this->assertSame( 'Eight Ball is a pool hall in Fargo, ND.', About_Text::identity( [ 'name' => 'Eight Ball', 'type' => 'pool-halls', 'city_state' => 'Fargo, ND' ] ) );
		$this->assertSame( 'Somewhere is a place to play pool.', About_Text::identity( [ 'name' => 'Somewhere', 'type' => 'other-venue' ] ) );
	}

	public function test_hours(): void {
		$day = static fn( int $d, int $open, int $close ) => [ $d * 1440 + $open, $d * 1440 + $close ];
		// Tue-Sun 4 PM to midnight, Fri and Sat until 2 AM.
		$week = [ $day( 1, 960, 1440 ), $day( 2, 960, 1440 ), $day( 3, 960, 1440 ), $day( 4, 960, 1560 ), $day( 5, 960, 1560 ), $day( 6, 960, 1440 ) ];
		$this->assertSame( [ "It's closed on Mondays.", 'It stays open until 2 AM on Fridays and Saturdays.' ], About_Text::hours( [ 'hours' => $week ] ) );

		$daily = array_map( static fn( $d ) => $day( $d, 840, 1560 ), range( 0, 6 ) );
		$this->assertSame( [ "It's open every day until 2 AM." ], About_Text::hours( [ 'hours' => $daily ] ) );

		$weekdays = array_map( static fn( $d ) => $day( $d, 660, 1380 ), range( 0, 4 ) );
		$this->assertSame( [ "It's closed on Saturdays and Sundays." ], About_Text::hours( [ 'hours' => $weekdays ] ) );

		// Sunday night stored as [9300, 10080] plus [0, 120] belongs to Sunday, not Monday.
		$split = [ [ 0, 120 ], [ 660, 1560 ], [ 2100, 3000 ], [ 3540, 4440 ], [ 4980, 5880 ], [ 6420, 7320 ], [ 7860, 8760 ], [ 9300, 10080 ] ];
		$this->assertSame( [ "It's open every day until 2 AM." ], About_Text::hours( [ 'hours' => $split ] ) );

		$this->assertSame( [ "It's open 24 hours a day." ], About_Text::hours( [ 'hours' => [ [ 0, 10080 ] ] ] ) );
		$this->assertSame( [ "It's temporarily closed." ], About_Text::hours( [ 'hours' => $daily, 'status' => 'temporarily-closed' ] ) );
		$this->assertSame( [], About_Text::hours( [] ) );
	}

	public function test_no_tables_and_single_table(): void {
		$this->assertStringContainsString( "It doesn't have pool tables right now.", $this->text( [ 'name' => 'X', 'type' => 'pool-halls', 'tables' => 0 ] ) );
		$this->assertStringContainsString( "It has one table. It's an 8-foot table.", $this->text( [ 'name' => 'X', 'type' => 'bars-with-pool-tables', 'tables' => 1, 'size_counts' => [ '8-foot' => 1 ] ] ) );
	}
}
