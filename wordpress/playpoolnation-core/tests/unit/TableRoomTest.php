<?php
use PHPUnit\Framework\TestCase;
use PlayPoolNation\Core\Helpers\Table_Room;

final class TableRoomTest extends TestCase {

	public function test_models_line_that_adds_up_gives_brand_groups(): void {
		$g = Table_Room::groups( [ '7-foot' => 16, '9-foot' => 2 ], 18, "12 Diamond 7', 2 Diamond 9', 4 Valley bar box 7'" );
		$this->assertSame( [
			[ 'size' => '9-foot', 'count' => 2, 'brand' => 'Diamond' ],
			[ 'size' => '7-foot', 'count' => 12, 'brand' => 'Diamond' ],
			[ 'size' => '7-foot', 'count' => 4, 'brand' => 'Valley bar box' ],
		], $g );
		$this->assertSame( '18 tables: 2 Diamond 9-foot, 12 Diamond 7-foot and 4 Valley bar box 7-foot.', Table_Room::summary( $g ) );
	}

	public function test_models_line_that_does_not_add_up_is_ignored(): void {
		$g = Table_Room::groups( [ '7-foot' => 16 ], null, "10 Diamond 7'" );
		$this->assertSame( [ [ 'size' => '7-foot', 'count' => 16, 'brand' => '' ] ], $g );
	}

	public function test_models_line_that_does_not_parse_is_ignored(): void {
		$this->assertNull( Table_Room::from_models( 'Mostly Diamonds, a couple of old Brunswicks', [ '7-foot' => 8 ] ) );
	}

	public function test_feet_spelled_out(): void {
		$this->assertSame( [ [ 'size' => '9-foot', 'count' => 6, 'brand' => 'Diamond Pro-Am' ] ], Table_Room::from_models( '6 Diamond Pro-Am 9 ft', [ '9-foot' => 6 ] ) );
	}

	public function test_size_not_in_models_line_is_still_drawn(): void {
		$g = Table_Room::from_models( "4 Diamond 9'", [ '9-foot' => 4, '7-foot' => 3 ] );
		$this->assertSame( [ 'size' => '7-foot', 'count' => 3, 'brand' => '' ], $g[1] );
	}

	public function test_unknown_sizes_are_the_rest_of_the_total(): void {
		$g = Table_Room::groups( [ '9-foot' => 2 ], 10 );
		$this->assertSame( [ 'size' => '', 'count' => 8, 'brand' => '' ], end( $g ) );
		$this->assertSame( '8 more, size not known', Table_Room::caption( end( $g ) ) );
	}

	public function test_total_only(): void {
		$g = Table_Room::groups( [], 5 );
		$this->assertSame( '5 tables, sizes not known yet.', Table_Room::summary( $g ) );
	}

	public function test_one_size_summary(): void {
		$this->assertSame( '4 tables, all 7-foot.', Table_Room::summary( Table_Room::groups( [ '7-foot' => 4 ], 4 ) ) );
	}

	public function test_nothing_known_draws_nothing(): void {
		$this->assertSame( [], Table_Room::groups( [], null ) );
		$this->assertSame( '', Table_Room::html( [] ) );
	}

	public function test_drawing_is_capped(): void {
		$html = Table_Room::html( Table_Room::groups( [ '7-foot' => 60 ], 60 ) );
		$this->assertSame( Table_Room::MAX_DRAWN, substr_count( $html, '<use ' ) );
		$this->assertStringContainsString( '+12 more not drawn', $html );
	}
}
