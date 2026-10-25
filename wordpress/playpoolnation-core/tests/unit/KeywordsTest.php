<?php

use PHPUnit\Framework\TestCase;
use PlayPoolNation\Core\Helpers\Address;
use PlayPoolNation\Core\Helpers\Keyword_Report;
use PlayPoolNation\Core\Helpers\Keywords;

final class KeywordsTest extends TestCase {

	private const VENUES = [
		[ 'Rack City Billiards', 'Sioux Falls', 'SD' ],
		[ 'Chicago Billiard Cafe', 'Chicago', 'IL' ],
		[ 'Buffalo Billiards', 'Washington', 'DC' ],
		[ 'Shooters Billiard Club & Café', 'Burnsville', 'MN' ],
		[ 'Hall of Fame Billiards Bar & Grill', 'Warren', 'MI' ],
		[ 'The Rack', 'Livonia', 'MI' ],
	];

	private static ?array $index = null;

	private function index(): array {
		if ( null === self::$index ) {
			self::$index = Keywords::index( require __DIR__ . '/../../includes/data/us-cities.php', Address::STATES, self::VENUES );
		}
		return self::$index;
	}

	private function classify( string $keyword ): array {
		return Keywords::classify( $keyword, $this->index() );
	}

	private function city( array $class ): string {
		return $class['city'] ? $class['city']['name'] . ', ' . $class['city']['state'] : '';
	}

	/* ------------------------------------------------------------ fixtures */

	public function test_city_with_state_abbreviation(): void {
		$c = $this->classify( 'pool halls in sioux falls sd' );
		$this->assertSame( 'city', $c['intent'] );
		$this->assertSame( 'Sioux Falls, SD', $this->city( $c ) );
		$this->assertFalse( $c['named'] );
	}

	public function test_near_me(): void {
		$c = $this->classify( 'bars with pool tables near me' );
		$this->assertSame( 'near_me', $c['intent'] );
		$this->assertTrue( $c['near_me'] );
		$this->assertSame( [], $c['topics'] );
	}

	public function test_attribute_with_a_city_is_city_intent_and_keeps_the_topic(): void {
		$c = $this->classify( '9 foot pool table minneapolis' );
		$this->assertSame( 'city', $c['intent'] );
		$this->assertSame( 'Minneapolis, MN', $this->city( $c ) );
		$this->assertSame( [ '9-foot tables' ], $c['topics'] );
	}

	public function test_venue_name(): void {
		$c = $this->classify( 'rack city billiards hours' );
		$this->assertSame( 'venue', $c['intent'] );
		$this->assertSame( 'Rack City Billiards', $c['venue'] );
	}

	public function test_how_to_is_other(): void {
		$c = $this->classify( 'how to clean pool table felt' );
		$this->assertSame( 'other', $c['intent'] );
		$this->assertTrue( $c['shopping'] );
	}

	/* --------------------------------------------------------- more classes */

	public function test_attribute_without_a_place(): void {
		$cases = [
			'apa pool league'          => [ 'APA leagues' ],
			'24 hour pool hall'        => [ '24 hour' ],
			'pool hall open late'      => [ 'Open late' ],
			'bca pool league'          => [ 'BCA leagues' ],
			'pool tournaments'         => [ 'Tournaments' ],
			'billiards lessons'        => [ 'Lessons' ],
			'free pool night'          => [ 'Free pool' ],
		];
		foreach ( $cases as $keyword => $topics ) {
			$c = $this->classify( $keyword );
			$this->assertSame( 'attribute', $c['intent'], $keyword );
			$this->assertSame( $topics, $c['topics'], $keyword );
		}
	}

	public function test_buying_or_playing_online_is_other(): void {
		foreach ( [ 'brunswick pool table', 'pool tables for sale near me', 'used 9 foot pool table', 'billiards shop near me', 'pool table movers near me', 'free pool games', '8 ball pool online', 'billiards supplies near me' ] as $keyword ) {
			$this->assertSame( 'other', $this->classify( $keyword )['intent'], $keyword );
		}
		$this->assertSame( 'near_me', $this->classify( 'pool tables near me' )['intent'] );
	}

	public function test_swimming_pools_are_other(): void {
		foreach ( [ 'swimming pool hall', 'denver pool party', 'hall of fame pool', 'above ground pool near me' ] as $keyword ) {
			$c = $this->classify( $keyword );
			$this->assertSame( 'other', $c['intent'], $keyword );
			$this->assertNull( $c['city'], $keyword );
		}
	}

	public function test_city_names(): void {
		$cases = [
			'nyc billiards'                 => 'New York, NY',
			'pool hall in brooklyn'         => 'New York, NY',
			'pool hall dc'                  => 'Washington, DC',
			'pool hall in st paul'          => 'St. Paul, MN',
			'saint paul pool hall'          => 'St. Paul, MN',
			'pool hall mobile al'           => 'Mobile, AL',
			'springfield il pool hall'      => 'Springfield, IL',
			'springfield pool hall'         => 'Springfield, MO',
			'pool hall portland maine'      => 'Portland, ME',
			'moreno valley pool hall'       => 'Moreno Valley, CA',
		];
		foreach ( $cases as $keyword => $city ) {
			$c = $this->classify( $keyword );
			$this->assertSame( 'city', $c['intent'], $keyword );
			$this->assertSame( $city, $this->city( $c ), $keyword );
		}
		$this->assertSame( [], $this->classify( 'moreno valley pool hall' )['topics'], 'Valley is the city, not the brand' );
	}

	public function test_states_and_ambiguous_names(): void {
		$wa = $this->classify( 'washington pool hall' );
		$this->assertNull( $wa['city'] );
		$this->assertSame( 'WA', $wa['state'] );
		$this->assertSame( 'city', $wa['intent'] );

		$ny = $this->classify( 'new york state pool league' );
		$this->assertNull( $ny['city'] );
		$this->assertSame( 'NY', $ny['state'] );

		foreach ( [ 'mobile pool hall', 'pool hall of fame columbia', 'suffolk county pool hall', 'pool hall london ontario' ] as $keyword ) {
			$this->assertNull( $this->classify( $keyword )['city'], $keyword );
		}
	}

	public function test_business_names(): void {
		$this->assertTrue( $this->classify( 'zanzibar billiards denver' )['named'] );
		$this->assertTrue( $this->classify( '8 ball sports bar & billiards columbus' )['named'] );
		$this->assertFalse( $this->classify( 'best pool hall in downtown denver' )['named'] );
		$this->assertFalse( $this->classify( 'bars with pool tables nyc' )['named'] );
	}

	public function test_venue_matching_needs_a_distinctive_name(): void {
		$this->assertNull( $this->classify( 'chicago pool hall' )['venue'], 'a venue named after its city is not every search there' );
		$this->assertSame( 'Chicago Billiard Cafe', $this->classify( 'chicago billiard cafe' )['venue'] );
		$this->assertSame( 'Shooters Billiard Club & Café', $this->classify( 'shooters billiards burnsville' )['venue'] );
		$this->assertNull( $this->classify( 'shooters billiards' )['venue'], 'one word needs the city' );
		$this->assertNull( $this->classify( 'the rack pool hall' )['venue'] );
		$this->assertSame( 'The Rack', $this->classify( 'the rack livonia' )['venue'] );
		$this->assertNull( $this->classify( 'hall of fame billiards brooklyn' )['venue'], 'same name, other state' );

		$buffalo = $this->classify( 'buffalo billiards' );
		$this->assertSame( 'venue', $buffalo['intent'] );
		$this->assertNull( $buffalo['city'], 'the city in a venue\'s name is not a location' );
	}

	/* --------------------------------------------------------------- input */

	public function test_reads_ahrefs_utf16_tab_export(): void {
		$csv = "\u{FEFF}#\tKeyword\tCountry\tDifficulty\tVolume\n1\tpool hall\tus\t12\t1.6K\n2\t\"bars, pool\"\tus\t3\t90\n";
		$raw = "\xFF\xFE" . mb_convert_encoding( substr( $csv, 3 ), 'UTF-16LE', 'UTF-8' );
		$export = Keywords::read_export( $raw );
		$this->assertSame( '', $export['error'] );
		$this->assertSame( [ [ 'keyword' => 'pool hall', 'volume' => 1600 ], [ 'keyword' => 'bars, pool', 'volume' => 90 ] ], $export['rows'] );
	}

	public function test_reads_semrush_and_dataforseo_exports(): void {
		$semrush = "Keyword,Position,Previous position,Search Volume,Keyword Difficulty,CPC\npool hall denver,4,5,\"1,300\",20,1.2\n";
		$this->assertSame( [ [ 'keyword' => 'pool hall denver', 'volume' => 1300 ] ], Keywords::read_export( $semrush )['rows'] );

		$dataforseo = "keyword,search_volume,keyword_difficulty,core_keyword\npool halls near me,74000,9,pool hall near me\n";
		$this->assertSame( [ [ 'keyword' => 'pool halls near me', 'volume' => 74000 ] ], Keywords::read_export( $dataforseo )['rows'] );

		$this->assertNotSame( '', Keywords::read_export( "url,traffic\n/x,4\n" )['error'] );
	}

	public function test_volume_formats(): void {
		$this->assertSame( 1600, Keywords::volume( '1.6K' ) );
		$this->assertSame( 1600, Keywords::volume( '1,600' ) );
		$this->assertSame( 2000000, Keywords::volume( '2M' ) );
		$this->assertSame( 1000, Keywords::volume( '1K – 10K' ) );
		$this->assertSame( 0, Keywords::volume( '' ) );
	}

	public function test_dedupe_keeps_the_highest_volume_and_every_source(): void {
		$rows = Keywords::dedupe( [
			[ 'keyword' => 'Pool Hall', 'volume' => 10, 'source' => 'a.csv' ],
			[ 'keyword' => ' pool  hall', 'volume' => 30, 'source' => 'b.csv' ],
		] );
		$this->assertSame( [ 'pool hall' => [ 'keyword' => 'pool hall', 'volume' => 30, 'sources' => [ 'a.csv', 'b.csv' ] ] ], $rows );
	}

	public function test_close_variants_count_once(): void {
		$this->assertSame( Keywords::variant_key( 'pool halls near me' ), Keywords::variant_key( 'pool hall nearby' ) );
		$this->assertSame( Keywords::variant_key( 'billiards in denver' ), Keywords::variant_key( 'denver billiard' ) );
		$this->assertSame( 1300, Keywords::total( [
			[ 'keyword' => 'pool hall denver', 'volume' => 1000 ],
			[ 'keyword' => 'denver pool halls', 'volume' => 1000 ],
			[ 'keyword' => 'bars with pool tables denver', 'volume' => 300 ],
		] ) );
	}

	/* -------------------------------------------------------------- report */

	private function site(): array {
		return [
			'venues' => [
				[ 'Rack City Billiards', 'https://ppn.test/place/rack-city/', 'Sioux Falls', 'SD' ],
				[ 'Buffalo Billiards', 'https://ppn.test/place/buffalo/', 'Washington', 'DC' ],
			],
			'cities' => [
				'sioux falls|SD' => [ 'Sioux Falls', 'SD', 'https://ppn.test/places/south-dakota/sioux-falls/', 10 ],
				'denver|CO'      => [ 'Denver', 'CO', 'https://ppn.test/places/colorado/denver/', 4 ],
				'st paul|MN'     => [ 'St. Paul', 'MN', 'https://ppn.test/places/minnesota/st-paul/', 2 ],
				'omaha|NE'       => [ 'Omaha', 'NE', '', 1 ],
			],
			'metros' => [ [ 'label' => 'Minneapolis-St. Paul', 'url' => 'https://ppn.test/places/minneapolis-st-paul/', 'venues' => 6, 'cities' => [ 'minneapolis|MN', 'st paul|MN' ] ] ],
			'states' => [ 'SD' => [ 'https://ppn.test/places/south-dakota/', 10 ] ],
			'hubs'   => [ 'APA leagues' => [ 'https://ppn.test/leagues/', true ], 'Leagues' => [ 'https://ppn.test/leagues/', true ], 'Lessons' => [ 'https://ppn.test/instructors/', false ] ],
			'places' => 'https://ppn.test/places/',
		];
	}

	private function report( array $keywords ): array {
		$rows = [];
		foreach ( $keywords as $keyword => $volume ) {
			$rows[] = [ 'keyword' => $keyword, 'volume' => $volume ];
		}
		return Keyword_Report::classify_all( Keywords::dedupe( $rows ), $this->index(), $this->site() );
	}

	public function test_pages_for_each_intent(): void {
		$pages = [];
		foreach ( $this->report( [ 'rack city billiards' => 500, 'pool hall sioux falls' => 90, 'pool hall st paul' => 50, 'apa pool league' => 8100, 'pool lessons' => 40, 'pool halls near me' => 74000, 'pool hall omaha' => 70, 'how to rack' => 10 ] ) as $row ) {
			$pages[ $row['keyword'] ] = array_column( $row['pages'], 'url' );
		}
		$this->assertSame( [ 'https://ppn.test/place/rack-city/' ], $pages['rack city billiards'] );
		$this->assertSame( [ 'https://ppn.test/places/south-dakota/sioux-falls/' ], $pages['pool hall sioux falls'] );
		$this->assertSame( [ 'https://ppn.test/places/minnesota/st-paul/', 'https://ppn.test/places/minneapolis-st-paul/' ], $pages['pool hall st paul'] );
		$this->assertSame( [ 'https://ppn.test/leagues/' ], $pages['apa pool league'] );
		$this->assertSame( [], $pages['pool lessons'], 'a noindexed hub answers nothing' );
		$this->assertSame( [ 'https://ppn.test/places/' ], $pages['pool halls near me'] );
		$this->assertSame( [], $pages['pool hall omaha'], 'a thin city page is no page' );
	}

	public function test_business_names_by_volume(): void {
		$rows = [];
		foreach ( $this->report( [ 'clifton billiards nj' => 3600, 'clifton billiards' => 3600, 'pool hall clifton nj' => 140, 'springfield billiards' => 3600, 'pool hall springfield' => 260, 'denver billiards' => 1300, 'pool hall denver' => 880, 'buffalo billiards dc' => 8100 ] ) as $row ) {
			$rows[ $row['keyword'] ] = $row['class'];
		}
		$this->assertTrue( $rows['clifton billiards']['named'], 'same volume as a business search in the city' );
		$this->assertTrue( $rows['springfield billiards']['named'], 'far more than the city\'s pool hall searches' );
		$this->assertFalse( $rows['denver billiards']['named'] );
		$this->assertFalse( $rows['pool hall clifton nj']['named'] );
	}

	public function test_city_ranking_and_coverage(): void {
		$cities = Keyword_Report::cities( $this->report( [
			'pool hall sioux falls'     => 90,
			'pool halls sioux falls'    => 90,
			'denver pool hall'          => 880,
			'zanzibar billiards denver' => 3600,
			'pool hall st paul'         => 50,
			'pool hall tulsa'           => 170,
		] ), $this->site() );
		$this->assertSame( [ 'Denver', 'Tulsa', 'Sioux Falls', 'St. Paul' ], array_column( $cities, 'name' ) );
		$this->assertSame( [ 880, 170, 90, 50 ], array_column( $cities, 'generic' ) );
		$this->assertSame( 4480, $cities[0]['volume'] );
		$this->assertSame( [ 'Page, under 5 venues', 'No page', 'Covered', 'Page, under 5 venues' ], array_column( $cities, 'coverage' ) );
		$this->assertSame( 1, $cities[2]['venues'] );
	}

	public function test_gaps_competition_and_markdown(): void {
		$classified = $this->report( [ 'pool hall tulsa' => 170, 'pool hall st paul' => 50, 'brunswick pool table' => 9000, 'rack city billiards' => 500, 'free pool near me' => 30 ] );
		$this->assertSame( [ 'pool hall tulsa', 'free pool near me' ], array_column( Keyword_Report::gaps( $classified ), 'keyword' ) );
		$this->assertSame( [ [ 'keyword' => 'pool hall st paul', 'volume' => 50, 'pages' => [ 'https://ppn.test/places/minnesota/st-paul/', 'https://ppn.test/places/minneapolis-st-paul/' ] ] ], Keyword_Report::competing( $classified ) );

		$md = Keyword_Report::markdown( $classified, $this->site(), [ 'date' => '2026-10-25', 'files' => [ 'a.csv' ], 'rows' => 5, 'home' => 'https://ppn.test/' ] );
		$this->assertStringStartsWith( "# Keyword demand report, 2026-10-25\n", $md );
		foreach ( [ '## Cities by search volume', '## Attribute topics', '## Top 50 keywords with no page', '## Keywords two of our pages could compete for' ] as $heading ) {
			$this->assertStringContainsString( $heading, $md );
		}
		$this->assertStringContainsString( '| 1 | Tulsa, OK | 170 | 170 | 1 | pool hall tulsa | 0 |  | No page |', $md );
		$this->assertStringContainsString( '/places/minnesota/st-paul/, /places/minneapolis-st-paul/', $md );
	}
}
