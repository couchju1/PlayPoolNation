<?php

use PHPUnit\Framework\TestCase;
use PlayPoolNation\Core\Helpers\Html_Fix;

final class HtmlFixTest extends TestCase {

	private const LIVE = 'https://playpoolnation.com';

	/* ------------------------------------------------------ live domain */

	public function test_rewrites_temporary_domain_links(): void {
		[ $html, $n ] = Html_Fix::live_domain( '<a href="https://gray-hedgehog-574573.hostingersite.com/place/x/">X</a>', self::LIVE );
		$this->assertSame( '<a href="https://playpoolnation.com/place/x/">X</a>', $html );
		$this->assertSame( 1, $n );
	}

	public function test_rewrites_escaped_scheme_relative_and_bare_hosts(): void {
		[ $json ] = Html_Fix::live_domain( '{"u":"http:\/\/gray-hedgehog-574573.hostingersite.com\/places\/"}', self::LIVE );
		$this->assertSame( '{"u":"https:\/\/playpoolnation.com\/places\/"}', $json );
		[ $rel ] = Html_Fix::live_domain( '<img src="//gray-hedgehog-574573.hostingersite.com/a.png">', self::LIVE );
		$this->assertSame( '<img src="//playpoolnation.com/a.png">', $rel );
		[ $bare, $n ] = Html_Fix::live_domain( 'Moved from gray-hedgehog-574573.hostingersite.com.', self::LIVE );
		$this->assertSame( 'Moved from playpoolnation.com.', $bare );
		$this->assertSame( 1, $n );
	}

	public function test_leaves_html_alone_without_a_temporary_domain_or_when_running_on_one(): void {
		$this->assertSame( [ '<a href="/x/">x</a>', 0 ], Html_Fix::live_domain( '<a href="/x/">x</a>', self::LIVE ) );
		$html = '<a href="https://a-b.hostingersite.com/x/">x</a>';
		$this->assertSame( [ $html, 0 ], Html_Fix::live_domain( $html, 'https://a-b.hostingersite.com' ) );
	}

	public function test_rewrites_inside_stored_arrays(): void {
		$count = 0;
		$value = Html_Fix::live_domain_deep( [ 'menu' => [ 'url' => 'https://x-1.hostingersite.com/events/' ], 'n' => 3 ], self::LIVE, $count );
		$this->assertSame( [ 'menu' => [ 'url' => 'https://playpoolnation.com/events/' ], 'n' => 3 ], $value );
		$this->assertSame( 1, $count );
	}

	/* ---------------------------------------------------------- explore */

	private function explore_page( int $found ): string {
		return '<div class="cts-explore"><load-more-results ref="loadMoreComponent" inline-template><div class="explore-desktop-head" v-if="foundPosts !== 0">'
			. '<span v-if="showing" href="#" class="fl-results-no text-left" v-cloak>' . "\n"
			. '<span class="rslt-nr" v-if="showing === 1">Showing <b>1</b> result</span>' . "\n"
			. '<span class="rslt-nr" v-else-if="showing <= foundPosts">' . "\n" . 'Showing <b>{{ showing }}</b> out of <b>{{ foundPosts }}</b> results	</span>' . "\n"
			. '</span></div></load-more-results>'
			. '<div class="no-results-wrapper" v-show="!loading && found_posts === 0" style="display:none;"><i class="mi mood_bad"></i><li class="no_job_listings_found">There are no listings matching your search.<a href="#">Reset Filters</a></li></div>'
			. '<label>{{label}}</label><span>({{ compare.length }})</span><h6 v-else>No listings</h6>'
			. '<script type="text/template"><div style="background-image: url({{marker-bg}});"></div></script>'
			. '</div><script id="cfg">{"prefetched_results":{"found_posts":' . $found . ',"showing":"x","max_num_pages":6,"posts_per_page":20}}</script>';
	}

	public function test_explore_prints_the_real_count(): void {
		$html = Html_Fix::explore( $this->explore_page( 107 ) );
		$this->assertStringContainsString( 'Showing <b>1-20</b> out of <b>107</b> results</span>', $html );
		$this->assertStringNotContainsString( 'Showing <b>1</b> result', $html );
		$this->assertStringNotContainsString( '{{ showing }}', $html );
		$this->assertStringNotContainsString( 'v-cloak', $html );
		$this->assertStringContainsString( 'v-html="&#039;Showing &lt;b&gt;&#039; + showing + &#039;&lt;/b&gt; out of &lt;b&gt;&#039; + foundPosts + &#039;&lt;/b&gt; results&#039;"', $html );
	}

	public function test_explore_single_result(): void {
		$html = Html_Fix::explore( $this->explore_page( 1 ) );
		$this->assertStringContainsString( 'v-if="showing === 1" v-html="&#039;Showing &lt;b&gt;&#039; + showing + &#039;&lt;/b&gt; result&#039;">Showing <b>1</b> result</span>', $html );
		$this->assertStringNotContainsString( 'out of <b>', $html );
	}

	public function test_explore_holds_back_no_results_message_only_when_there_are_results(): void {
		$html = Html_Fix::explore( $this->explore_page( 107 ) );
		$this->assertStringNotContainsString( 'There are no listings', $html );
		$this->assertMatchesRegularExpression( '#<div class="no-results-wrapper"[^>]*><template><i class="mi mood_bad"></i><li class="no_job_listings_found">Nothing matches your search\.<a href="\#">Reset Filters</a></li></template></div>#', $html );
		$empty = Html_Fix::explore( $this->explore_page( 0 ) );
		$this->assertStringContainsString( 'There are no listings matching your search.', $empty );
	}

	public function test_explore_replaces_placeholders_but_not_script_templates(): void {
		$html = Html_Fix::explore( $this->explore_page( 107 ) );
		$this->assertStringContainsString( '<label v-text="label"></label>', $html );
		$this->assertStringContainsString( '<span v-text="&#039;(&#039; + (compare.length) + &#039;)&#039;"></span>', $html );
		$this->assertStringContainsString( '<h6 v-else v-text="\'None\'"></h6>', $html );
		$this->assertStringContainsString( 'url({{marker-bg}})', $html );
		$this->assertStringNotContainsString( 'No listings', $html );
	}

	public function test_explore_ignores_other_pages(): void {
		$html = '<p>{{ not vue }}</p>';
		$this->assertSame( $html, Html_Fix::explore( $html ) );
	}

	/* ------------------------------------------------------------- hero */

	public function test_hero_replaces_the_shared_heading_and_intro(): void {
		$html = '<div class="elementor-element elementor-element-e574025 ppn-h1 elementor-widget elementor-widget-heading" data-id="e574025">' . "\n\t"
			. '<h1 class="elementor-heading-title elementor-size-default">Find a pool table <em>tonight.</em></h1></div>'
			. '<div class="elementor-element ppn-sub elementor-widget elementor-widget-text-editor" data-id="ff5182d">' . "\n\t"
			. '<p><strong>107 verified pool halls.</strong> Search near you.</p></div><h1>Other</h1><p>Other</p>';
		$out = Html_Fix::hero( $html, 'Pool halls & places to play pool in Sioux Falls, SD', '10 places to play pool in Sioux Falls, SD.' );
		$this->assertStringContainsString( '<h1 class="elementor-heading-title elementor-size-default">Pool halls &amp; places to play pool in Sioux Falls, SD</h1>', $out );
		$this->assertStringContainsString( "data-id=\"ff5182d\">\n\t<p>10 places to play pool in Sioux Falls, SD.</p></div>", $out );
		$this->assertStringContainsString( '<h1>Other</h1><p>Other</p>', $out );
		$this->assertStringNotContainsString( 'tonight', $out );
	}

	/* --------------------------------------------------------- pool game */

	public function test_pool_game_waits_for_play(): void {
		$html = '<div class="ppn-pg__frame">' . "\n\t" . '<iframe data-ppn="frame" title="Billiards game" src="https://x.test/game/index.html?ruletype=nineball&#038;v=1" loading="lazy"></iframe>' . "\n</div>";
		$out = Html_Fix::pool_game_on_click( $html );
		$this->assertStringContainsString( '<div class="ppn-pg__frame" data-ppn-idle>', $out );
		$this->assertStringContainsString( 'data-src="https://x.test/game/index.html?ruletype=nineball&#038;v=1"', $out );
		$this->assertStringNotContainsString( ' src=', $out );
		$this->assertStringContainsString( '<button type="button" class="ppn-pg__play" data-ppn-play>Play</button>', $out );
	}
}
