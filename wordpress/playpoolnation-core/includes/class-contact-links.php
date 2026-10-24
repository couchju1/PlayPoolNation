<?php
/**
 * Phone, directions and venue website links.
 *
 * The theme prints `tel:(605)%20271-2951`, `http://maps.google.com/maps?daddr=…`
 * and the website exactly as entered (often http). Its templates have no hook for
 * these links, so they are rewritten through Markup on every front-end page:
 * phone links in E.164, directions as a Google Maps directions URL over https,
 * and the venue's website upgraded to https when the site supports it. That check
 * runs once when the listing is saved (or with `wp ppn check-websites`) and is
 * stored, never at page view.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Format;

defined( 'ABSPATH' ) || exit;

final class Contact_Links {

	/** https URL that worked for the stored website, or '' when it did not. */
	private const HTTPS_META = '_ppn_website_https';
	/** The website value that HTTPS_META was checked for. */
	private const CHECKED_META = '_ppn_website_checked';

	public static function boot(): void {
		add_action( 'ppn_markup', static fn() => Markup::add( [ __CLASS__, 'rewrite' ] ) );
		add_action( 'save_post_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'on_save' ], 99, 1 );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'ppn check-websites', [ __CLASS__, 'cli' ] );
		}
	}

	public static function rewrite( string $html ): string {
		$html = (string) preg_replace_callback( '#href="tel:([^"]*)"#', static function ( $m ) {
			$e164 = Format::e164( rawurldecode( html_entity_decode( $m[1], ENT_QUOTES ) ) );
			return $e164 ? 'href="tel:' . $e164 . '"' : $m[0];
		}, $html );
		$html = (string) preg_replace_callback( '#href="https?://maps\.google\.com/maps\?daddr=([^"&]*)[^"]*"#', static function ( $m ) {
			return 'href="' . esc_attr( Format::directions_url( urldecode( html_entity_decode( $m[1], ENT_QUOTES ) ) ) ) . '"';
		}, $html );
		if ( is_singular( Pool_Schema::POST_TYPE ) ) {
			$html = self::website_links( $html, (int) get_queried_object_id() );
		}
		return $html;
	}

	/** Links to the venue's own website: https when known to work, new tab, nofollow noopener. */
	public static function website_links( string $html, int $id ): string {
		$site = trim( (string) get_post_meta( $id, '_job_website', true ) );
		$host = $site ? strtolower( (string) wp_parse_url( $site, PHP_URL_HOST ) ) : '';
		if ( '' === $host ) {
			return $html;
		}
		$https = self::https_url( $id, $site );
		return (string) preg_replace_callback( '#<a\s[^>]*href="(https?://[^"]+)"[^>]*>#', static function ( $m ) use ( $host, $site, $https ) {
			$href = html_entity_decode( $m[1], ENT_QUOTES );
			if ( strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) ) !== $host ) {
				return $m[0];
			}
			$tag = $m[0];
			if ( $https && untrailingslashit( $href ) === untrailingslashit( $site ) ) {
				$tag = str_replace( 'href="' . $m[1] . '"', 'href="' . esc_attr( $https ) . '"', $tag );
			}
			$tag = (string) preg_replace( '#\s(rel|target)="[^"]*"#', '', $tag );
			return (string) preg_replace( '#^<a\s#', '<a target="_blank" rel="nofollow noopener" ', $tag );
		}, $html );
	}

	/** Stored https version of the website, valid only for the value it was checked for. */
	public static function https_url( int $id, string $site ): string {
		if ( str_starts_with( strtolower( $site ), 'https://' ) ) {
			return $site;
		}
		if ( (string) get_post_meta( $id, self::CHECKED_META, true ) !== $site ) {
			return '';
		}
		return (string) get_post_meta( $id, self::HTTPS_META, true );
	}

	public static function on_save( int $id ): void {
		if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! Venue::is_venue( $id ) ) {
			return;
		}
		self::check( $id );
	}

	/** Try the website over https once and remember the answer. */
	public static function check( int $id ): string {
		$site = trim( (string) get_post_meta( $id, '_job_website', true ) );
		if ( '' === $site || str_starts_with( strtolower( $site ), 'https://' ) || (string) get_post_meta( $id, self::CHECKED_META, true ) === $site ) {
			return self::https_url( $id, $site );
		}
		$try = (string) preg_replace( '#^http://#i', 'https://', $site );
		$res = wp_remote_head( $try, [ 'timeout' => 6, 'redirection' => 3 ] );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		// A working page, or a site that only refuses HEAD requests, over a valid TLS connection.
		$ok = ( $code >= 200 && $code < 400 ) || in_array( $code, [ 403, 405 ], true );
		update_post_meta( $id, self::CHECKED_META, $site );
		update_post_meta( $id, self::HTTPS_META, $ok ? $try : '' );
		return $ok ? $try : '';
	}

	/** wp ppn check-websites: check every published venue that has not been checked yet. */
	public static function cli(): void {
		$ids = get_posts( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_case27_listing_type', 'meta_value' => Pool_Schema::VENUE_TYPE ] );
		$upgraded = 0;
		foreach ( $ids as $id ) {
			$https = self::check( (int) $id );
			$upgraded += $https ? 1 : 0;
			\WP_CLI::log( get_the_title( $id ) . ': ' . ( $https ?: 'stays as entered' ) );
		}
		\WP_CLI::success( sprintf( '%d of %d venues link over https.', $upgraded, count( $ids ) ) );
	}
}
