<?php
/**
 * Fixes to the theme's single listing markup that no My Listing hook reaches.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Listing_Page {

	public static function boot(): void {
		add_action( 'ppn_markup', [ __CLASS__, 'markup' ] );
	}

	public static function markup(): void {
		if ( is_singular( Pool_Schema::POST_TYPE ) ) {
			Markup::add( [ __CLASS__, 'single_heading' ] );
			Markup::add( [ __CLASS__, 'drop_empty_blocks' ] );
		}
	}

	/**
	 * Page blocks whose shortcode found nothing (Display::EMPTY) are removed with their
	 * title, so pages never show a heading with nothing under it.
	 */
	public static function drop_empty_blocks( string $html ): string {
		return (string) preg_replace(
			'#<div class="[^"]*\bblock-type-raw\b[^"]*" id="[^"]*">\s*<div class="element content-block">\s*<div class="pf-head">\s*<div class="title-style-1">(?:(?!</div>).)*</div>\s*</div>\s*<div class="pf-body">\s*' . preg_quote( Display::EMPTY, '#' ) . '\s*</div>\s*</div>\s*</div>#s',
			'',
			$html
		);
	}

	/**
	 * templates/listing.php prints the cover title partial twice, once for wide screens
	 * (.main-info-desktop) and once for narrow ones (.main-info-mobile), so every listing
	 * had two H1s and two subtitle H2s. The mobile copy, the one Google's mobile crawler
	 * sees, stays a heading; the desktop copy becomes paragraphs with the same classes,
	 * styled in ppn.css to look exactly as before.
	 */
	public static function single_heading( string $html ): string {
		$start = strpos( $html, '<div class="main-info-desktop">' );
		$end = false === $start ? false : strpos( $html, '<div class="main-info-mobile">', $start );
		if ( false === $start || false === $end ) {
			return $html;
		}
		$desktop = substr( $html, $start, $end - $start );
		$desktop = (string) preg_replace( '#<h1 class="([^"]*)">(.*?)</h1>#s', '<p class="$1 ppn-title-copy">$2</p>', $desktop, 1 );
		$desktop = (string) preg_replace( '#<h2 class="(profile-tagline[^"]*)">(.*?)</h2>#s', '<p class="$1 ppn-title-copy">$2</p>', $desktop, 1 );
		return substr( $html, 0, $start ) . $desktop . substr( $html, $end );
	}
}
