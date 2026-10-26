<?php

use PHPUnit\Framework\TestCase;
use PlayPoolNation\Core\Helpers\Enrich_Fact;

final class EnrichFactTest extends TestCase {

	private function fact( array $over = [] ): array {
		return $over + [
			'field'         => 'table_count',
			'key'           => 'table_count',
			'value'         => 18,
			'source_url'    => 'https://rackcitybilliards.com/',
			'quote_snippet' => 'We have 18 pool tables.',
			'checked_at'    => '2026-10-25',
			'confidence'    => 'stated',
			'flags'         => [],
			'source_type'   => 'official_website',
		];
	}

	public function test_normalize_keeps_a_good_fact(): void {
		$f = Enrich_Fact::normalize( $this->fact() );
		$this->assertSame( 18, $f['value'] );
		$this->assertSame( 'official_website', $f['source_type'] );
		$this->assertSame( 'stated', $f['confidence'] );
	}

	public function test_normalize_rejects_bad_input(): void {
		$this->assertNull( Enrich_Fact::normalize( $this->fact( [ 'field' => 'nonsense' ] ) ) );
		$this->assertNull( Enrich_Fact::normalize( $this->fact( [ 'source_url' => 'javascript:alert(1)' ] ) ) );
		$this->assertNull( Enrich_Fact::normalize( $this->fact( [ 'value' => 0 ] ) ) );
		$this->assertNull( Enrich_Fact::normalize( $this->fact( [ 'field' => 'table_sizes', 'value' => [ 'size' => '6ft', 'count' => 2 ] ] ) ) );
		$this->assertNull( Enrich_Fact::normalize( $this->fact( [ 'field' => 'food', 'value' => 'yes' ] ) ) );
	}

	public function test_unknown_confidence_becomes_implied(): void {
		$this->assertSame( 'implied', Enrich_Fact::normalize( $this->fact( [ 'confidence' => 'guess' ] ) )['confidence'] );
	}

	public function test_quote_stays_under_twenty_words(): void {
		$long = implode( ' ', array_fill( 0, 40, 'word' ) );
		$words = array_filter( explode( ' ', Enrich_Fact::short_quote( $long ) ), static fn( $w ) => '…' !== $w );
		$this->assertLessThan( 20, count( $words ) );
	}

	public function test_labels(): void {
		$this->assertSame( '18 tables', Enrich_Fact::label( 'table_count', 18 ) );
		$this->assertSame( '9 ft: 2 tables', Enrich_Fact::label( 'table_sizes', [ 'size' => '9ft', 'count' => 2 ] ) );
		$this->assertSame( 'Hourly, $12 an hour', Enrich_Fact::label( 'pricing_model', [ 'model' => 'hourly', 'rate' => 12.0 ] ) );
		$this->assertSame( 'Free pool: Sunday, Monday', Enrich_Fact::label( 'pricing_model', [ 'model' => 'free-pool', 'days' => [ 'sunday', 'monday' ] ] ) );
		$this->assertSame( 'APA on Tuesday', Enrich_Fact::label( 'league_nights', [ 'league' => 'APA', 'weekday' => 'tuesday' ] ) );
	}

	public function test_edit_text_round_trips(): void {
		$cases = [
			[ 'table_count', 18 ],
			[ 'table_sizes', [ 'size' => '9ft', 'count' => 2 ] ],
			[ 'table_sizes', [ 'size' => '7ft', 'count' => null ] ],
			[ 'table_brands', 'Diamond' ],
			[ 'pricing_model', [ 'model' => 'hourly', 'rate' => 12.5 ] ],
			[ 'pricing_model', [ 'model' => 'free-pool', 'days' => [ 'monday', 'sunday' ] ] ],
			[ 'league_nights', [ 'league' => 'APA', 'weekday' => 'tuesday' ] ],
			[ 'league_nights', [ 'league' => 'VNEA', 'weekday' => null ] ],
			[ 'age_policy', '21-plus' ],
			[ 'food', false ],
			[ 'venue_status', 'permanently-closed' ],
		];
		foreach ( $cases as [ $field, $value ] ) {
			$this->assertSame( $value, Enrich_Fact::from_text( $field, Enrich_Fact::to_text( $field, $value ) ), $field );
		}
	}

	public function test_edit_text_rejects_garbage(): void {
		$this->assertNull( Enrich_Fact::from_text( 'table_count', 'lots' ) );
		$this->assertNull( Enrich_Fact::from_text( 'league_nights', 'APA / someday' ) );
		$this->assertNull( Enrich_Fact::from_text( 'food', 'maybe' ) );
	}

	public function test_plan_for_sizes_and_brands(): void {
		$this->assertSame(
			[ [ 'count', 'tables-9ft', 2, 'tables-9ft' ], [ 'term', 'table-size', '9-foot', 'table-size:9-foot' ] ],
			Enrich_Fact::plan( 'table_sizes', [ 'size' => '9ft', 'count' => 2 ] )
		);
		$this->assertSame( [ [ 'term', 'table-size', '7-foot', 'table-size:7-foot' ] ], Enrich_Fact::plan( 'table_sizes', [ 'size' => '7ft', 'count' => null ] ) );
		$this->assertSame( [ [ 'term', 'table-brand', 'diamond', 'table-brand:diamond' ] ], Enrich_Fact::plan( 'table_brands', 'Diamond' ) );
		$this->assertSame( 'other-brand', Enrich_Fact::plan( 'table_brands', 'Dynamic' )[0][2] );
		$this->assertSame( [ 'append', 'table-models', 'Dynamic', 'table-models' ], Enrich_Fact::plan( 'table_brands', 'Dynamic' )[1] );
	}

	public function test_plan_for_pricing_league_and_amenities(): void {
		$this->assertSame( [ 'meta', 'hourly-rate', '12', 'hourly-rate' ], Enrich_Fact::plan( 'pricing_model', [ 'model' => 'hourly', 'rate' => 12.0 ] )[1] );
		$this->assertSame( [ 'append', 'pricing-notes', 'Free pool Sundays.', 'pricing-notes' ], Enrich_Fact::plan( 'pricing_model', [ 'model' => 'free-pool', 'days' => [ 'sunday' ] ] )[1] );
		$this->assertSame( [ [ 'league', 'apa', 'APA', 'tuesday', 'league:apa:tuesday' ] ], Enrich_Fact::plan( 'league_nights', [ 'league' => 'APA', 'weekday' => 'tuesday' ] ) );
		$this->assertSame( 'local-league', Enrich_Fact::plan( 'league_nights', [ 'league' => 'SEPSA pool league', 'weekday' => 'thursday' ] )[0][1] );
		$this->assertSame( [ [ 'no', 'case27_job_listing_tags', 'full-bar', 'case27_job_listing_tags:full-bar' ] ], Enrich_Fact::plan( 'full_bar', false ) );
		$this->assertSame( [ [ 'status', 'permanently-closed', 'venue-status' ] ], Enrich_Fact::plan( 'venue_status', 'permanently-closed' ) );
	}
}
