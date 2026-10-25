<?php

use PHPUnit\Framework\TestCase;
use PlayPoolNation\Core\Helpers\City_Text;
use PlayPoolNation\Core\Helpers\Index_Rule;

final class CityTextTest extends TestCase {

	/** Sioux Falls as the live site has it: two venues with table counts, one with sizes and brands. */
	private function sioux_falls(): array {
		$bar = static fn( string $name ) => [ 'name' => $name, 'type' => 'bars-with-pool-tables', 'hours' => [ [ 660, 1560 ] ] ];
		return [
			[ 'name' => 'Rack City Billiards', 'type' => 'pool-halls', 'street' => '309 S Bahnson Ave', 'tables' => 18, 'size_counts' => [ '7-foot' => 16, '9-foot' => 2 ], 'sizes' => [ '7-foot', '9-foot' ], 'brands' => [ 'Diamond', 'Valley' ], 'games' => [ 'Darts' ] ],
			[ 'name' => 'Bigs Bar', 'type' => 'pool-halls', 'tables' => 12, 'pricing' => [ 'hourly' ] ],
			[ 'name' => 'JJ’s Billiards & Darts', 'type' => 'pool-halls' ],
			$bar( 'Gibs Sports Bar' ),
			$bar( 'Lucky’s' ),
			$bar( 'Lucky’s on Louise' ),
			$bar( 'Nickel Spot' ),
			$bar( 'Thirsty Duck' ),
			$bar( 'Tommy Jack’s Pub' ),
			$bar( 'Upper Cut Bar & Grill' ),
		];
	}

	private function assertPlain( string $text ): void {
		$this->assertDoesNotMatchRegularExpression( '/[;—–]| - /u', $text, 'no semicolons or dashes' );
		$this->assertDoesNotMatchRegularExpression( '/\b(best|top|great|popular|favorite)\b/i', $text, 'no invented comparisons' );
	}

	public function test_intro_states_facts_and_says_what_it_compares(): void {
		$intro = City_Text::intro( 'Sioux Falls', $this->sioux_falls() );
		$this->assertSame(
			"Sioux Falls has 10 places to play pool on our map. That's 7 bars and 3 pool halls. Of the 2 places where we know the count, Rack City Billiards has the most tables, with 18. Rack City Billiards has 9-foot tables.",
			$intro
		);
		$this->assertPlain( $intro );
	}

	public function test_most_tables_needs_two_known_counts(): void {
		$this->assertNull( City_Text::most_tables( [ [ 'name' => 'A', 'tables' => 9 ], [ 'name' => 'B' ] ] ) );
		$this->assertSame( [ 'names' => [ 'A', 'B' ], 'tables' => 9, 'known' => 2, 'all' => true ], City_Text::most_tables( [ [ 'name' => 'A', 'tables' => 9 ], [ 'name' => 'B', 'size_counts' => [ '8-foot' => 9 ] ] ] ) );
		$intro = City_Text::intro( 'Omaha', [ [ 'name' => 'A', 'type' => 'pool-halls', 'tables' => 9 ], [ 'name' => 'B', 'type' => 'pool-halls', 'tables' => 9 ] ] );
		$this->assertStringContainsString( 'A and B have the most tables, with 9 each.', $intro );
	}

	public function test_intro_mentions_free_pool_and_leagues_only_when_known(): void {
		$venues = [
			[ 'name' => 'A', 'type' => 'pool-halls', 'pricing' => [ 'free-pool' ], 'leagues' => [ [ 'day' => 1, 'time' => '7 PM', 'orgs' => [ 'APA' ], 'name' => 'APA 8-Ball' ] ] ],
			[ 'name' => 'B', 'type' => 'sports-bar', 'leagues' => [ [ 'day' => 3, 'time' => '', 'orgs' => [ 'APA', 'BCA' ], 'name' => 'Thursday league' ] ] ],
		];
		$intro = City_Text::intro( 'Tulsa', $venues );
		$this->assertStringContainsString( 'A has free pool.', $intro );
		$this->assertStringContainsString( '2 of them run APA leagues during the week.', $intro );
		$this->assertStringNotContainsString( 'free pool', City_Text::intro( 'Sioux Falls', $this->sioux_falls() ) );
		$this->assertStringNotContainsString( 'league', City_Text::intro( 'Sioux Falls', $this->sioux_falls() ) );
	}

	public function test_fact_lines_leave_out_unknowns(): void {
		$venues = $this->sioux_falls();
		$this->assertSame( [
			[ 'Type', 'Pool hall' ],
			[ 'Address', '309 S Bahnson Ave' ],
			[ 'Tables', '18' ],
			[ 'Sizes', '7-foot (16) and 9-foot (2)' ],
			[ 'Brands', 'Diamond and Valley' ],
			[ 'Also', 'Darts' ],
		], City_Text::fact_lines( $venues[0] ) );
		$this->assertSame( [ [ 'Type', 'Pool hall' ], [ 'Tables', '12' ], [ 'Pool', 'By the hour' ] ], City_Text::fact_lines( $venues[1] ) );
		$this->assertSame( [ [ 'Type', 'Bar' ] ], City_Text::fact_lines( $venues[3] ) );
		foreach ( $venues as $v ) {
			foreach ( City_Text::fact_lines( $v ) as [ , $value ] ) {
				$this->assertStringNotContainsStringIgnoringCase( 'unknown', $value );
			}
		}
	}

	public function test_week_hours_keep_late_nights_on_their_day(): void {
		// Rack City: Monday 2 PM to midnight … Sunday 11 AM to midnight, Saturday until 2 AM.
		$week = City_Text::week_hours( [ [ 840, 1440 ], [ 2100, 2880 ], [ 3720, 4320 ], [ 5160, 5760 ], [ 6600, 7320 ], [ 7860, 8760 ], [ 9300, 10080 ] ] );
		$this->assertSame( [ 'day' => 'Monday', 'hours' => '2 PM to midnight' ], $week[0] );
		$this->assertSame( [ 'day' => 'Saturday', 'hours' => '11 AM to 2 AM' ], $week[5] );
		// Sunday noon to 2 AM, split by My Listing at the end of the week; Tuesday noon to midnight only.
		$week = City_Text::week_hours( [ [ 9360, 10080 ], [ 0, 120 ], [ 2160, 2880 ] ] );
		$this->assertSame( 'Closed', $week[0]['hours'] );
		$this->assertSame( 'Noon to 2 AM', $week[6]['hours'] );
		$this->assertSame( 'Noon to midnight', $week[1]['hours'] );
		$this->assertSame( 'Closed', $week[2]['hours'] );
		$this->assertSame( [], City_Text::week_hours( [] ) );
		$this->assertSame( 'Open 24 hours', City_Text::week_hours( [ [ 0, 1440 ] ] )[0]['hours'] );
	}

	public function test_league_nights_group_by_weekday(): void {
		$venues = [
			[ 'name' => 'Rack City Billiards', 'leagues' => [ [ 'day' => 3, 'time' => '7 PM', 'orgs' => [ 'APA' ], 'name' => 'x' ], [ 'day' => 1, 'time' => '7:30 PM', 'orgs' => [], 'name' => 'Tuesday 9-Ball' ] ] ],
			[ 'name' => 'Bigs Bar', 'leagues' => [ [ 'day' => 1, 'time' => '', 'orgs' => [ 'BCA' ], 'name' => 'y' ] ] ],
			[ 'name' => 'Nickel Spot', 'leagues' => [ [ 'day' => null, 'time' => '', 'orgs' => [ 'APA' ], 'name' => 'z' ] ] ],
		];
		$this->assertSame( [
			'Tuesday'  => [ 'Tuesday 9-Ball at Rack City Billiards, 7:30 PM', 'BCA at Bigs Bar' ],
			'Thursday' => [ 'APA at Rack City Billiards, 7 PM' ],
		], City_Text::league_nights( $venues ) );
		$this->assertSame( [], City_Text::league_nights( $this->sioux_falls() ) );
		$this->assertSame( [ 1, 3 ], City_Text::league_days( $venues[0] ) );
		$this->assertSame( [ 'APA on Thursdays at 7 PM', 'Tuesday 9-Ball on Tuesdays at 7:30 PM' ], City_Text::league_lines( $venues[0] ) );
	}

	public function test_faq_asks_only_what_the_data_answers(): void {
		$faq = City_Text::faq( 'Sioux Falls', $this->sioux_falls() );
		$this->assertSame( [
			[ 'q' => 'Which pool hall in Sioux Falls has the most tables?', 'a' => 'Of the 2 places in Sioux Falls where we know the table count, Rack City Billiards has the most, with 18 tables.' ],
			[ 'q' => 'Where can I play on a 9-foot table in Sioux Falls?', 'a' => 'Rack City Billiards has two 9-foot tables.' ],
		], $faq );
		$this->assertSame( [], City_Text::faq( 'Omaha', [ [ 'name' => 'A' ], [ 'name' => 'B', 'tables' => 6 ] ] ) );
		$free = City_Text::faq( 'Tulsa', [ [ 'name' => 'A', 'pricing' => [ 'free-pool' ] ] ] );
		$this->assertSame( [ [ 'q' => 'Is there free pool in Tulsa?', 'a' => 'Yes. A has free pool.' ] ], $free );
		foreach ( $faq as $item ) {
			$this->assertPlain( $item['a'] );
		}
	}

	public function test_title_and_description_fit(): void {
		$this->assertSame( '10 Places to Play Pool in Sioux Falls, SD | PlayPoolNation', City_Text::title( 'Sioux Falls, SD', 10, 'PlayPoolNation' ) );
		$this->assertSame( 'A Place to Play Pool in Omaha, NE | PlayPoolNation', City_Text::title( 'Omaha, NE', 1, 'PlayPoolNation' ) );
		$long = City_Text::title( 'Rancho Santa Margarita, CA', 12, 'PlayPoolNation' );
		$this->assertLessThan( 60, mb_strlen( $long ) );
		$this->assertStringContainsString( 'Rancho Santa Margarita, CA', $long );

		$description = City_Text::description( 'Sioux Falls, SD', $this->sioux_falls() );
		$this->assertSame( "10 places to play pool in Sioux Falls, SD, with table counts, sizes, league nights and who's open now. Rack City Billiards has 18 tables.", $description );
		$this->assertLessThan( 155, mb_strlen( $description ) );
		$this->assertLessThan( 155, mb_strlen( City_Text::description( 'Rancho Santa Margarita, CA', array_fill( 0, 3, [ 'name' => str_repeat( 'Very Long Billiards Name ', 3 ), 'tables' => 20, 'sizes' => [ '9-foot' ] ] ) ) ) );
	}

	public function test_table_facts_and_index_rule(): void {
		$venues = $this->sioux_falls();
		$this->assertSame( 3, City_Text::table_facts( $venues[0] ) );
		$this->assertSame( 1, City_Text::table_facts( $venues[1] ) );
		$this->assertSame( 0, City_Text::table_facts( $venues[3] ) );
		$this->assertSame( 2, City_Text::table_facts( [ 'sizes' => [ '7-foot' ], 'brands' => [ 'Diamond', 'Other brand' ] ] ) );
		$this->assertSame( 1, City_Text::table_facts( [ 'brands' => [ 'Other brand' ], 'sizes' => [ '9-foot' ] ] ) );

		$this->assertSame( [], Index_Rule::missing( true, true, true, 2 ) );
		$this->assertSame( [ 'table facts' ], Index_Rule::missing( true, true, true, Index_Rule::MIN_TABLE_FACTS - 1 ) );
		$this->assertSame( [ 'address', 'phone or website', 'hours', 'table facts' ], Index_Rule::missing( false, false, false, 0 ) );
	}

	public function test_type_mix_needs_every_type(): void {
		$this->assertSame( '2 pool halls and 1 sports bar', City_Text::type_mix( [ [ 'type' => 'pool-halls' ], [ 'type' => 'sports-bar' ], [ 'type' => 'pool-halls' ] ] ) );
		$this->assertSame( '', City_Text::type_mix( [ [ 'type' => 'pool-halls' ], [ 'type' => '' ] ] ) );
	}
}
