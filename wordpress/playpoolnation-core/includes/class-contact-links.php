<?php
/**
 * Phone, directions and venue website links.
 *
 * The theme prints `tel:(605)%20271-2951`, `http://maps.google.com/maps?daddr=…`
 * and the website exactly as entered (often http). Its templates have no hook for
 * these links, so they are rewritten through Markup on every front-end page:
 * phone links in E.164, directions as a Google Maps directions URL over https,
 * and the venue's website upgraded to https when the site supports it. That check
 * runs once when the listing is saved (or with `wp ppn check-websites`, or
 * `wp ppn recheck-https` to check every listing again) and is stored, never at
 * page view.
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
			\WP_CLI::add_command( 'ppn recheck-https', [ __CLASS__, 'recheck_cli' ] );
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

	/** Try the website over https once and remember the answer; $force checks it again. */
	public static function check( int $id, bool $force = false ): string {
		$site = trim( (string) get_post_meta( $id, '_job_website', true ) );
		if ( '' === $site || str_starts_with( strtolower( $site ), 'https://' ) || ( ! $force && (string) get_post_meta( $id, self::CHECKED_META, true ) === $site ) ) {
			return self::https_url( $id, $site );
		}
		$https = self::find_https( $site );
		update_post_meta( $id, self::CHECKED_META, $site );
		update_post_meta( $id, self::HTTPS_META, $https );
		return $https;
	}

	/**
	 * The same address over https, or else wherever the site sends http visitors when
	 * that is https (rackcitybilliards.com has a certificate for www. only, and
	 * redirects there). An https address that redirects back to http does not count.
	 */
	private static function find_https( string $site ): string {
		foreach ( array_unique( [ (string) preg_replace( '#^http://#i', 'https://', $site ), $site ] ) as $try ) {
			$res = wp_remote_head( $try, [ 'timeout' => 6, 'redirection' => 5 ] );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			// A working page, or a site that only refuses HEAD requests, over a valid TLS connection.
			if ( ! ( ( $code >= 200 && $code < 400 ) || in_array( $code, [ 403, 405 ], true ) ) ) {
				continue;
			}
			$response = $res['http_response'] ?? null;
			$final = $response instanceof \WP_HTTP_Requests_Response ? (string) $response->get_response_object()->url : $try;
			$https = Format::https_upgrade( $site, $final );
			if ( $https ) {
				return $https;
			}
		}
		return '';
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

	/** wp ppn recheck-https: check every published listing's website again, including ones checked before. */
	public static function recheck_cli(): void {
		$ids = get_posts( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_job_website', 'meta_compare' => '!=', 'meta_value' => '' ] );
		$https = 0;
		$changed = 0;
		foreach ( $ids as $id ) {
			$site = trim( (string) get_post_meta( $id, '_job_website', true ) );
			$before = self::https_url( (int) $id, $site );
			$after = self::check( (int) $id, true );
			$https += $after ? 1 : 0;
			$changed += $after !== $before ? 1 : 0;
			\WP_CLI::log( sprintf( '%s: %s%s', get_the_title( $id ), $after ?: $site . ' (no https)', $after !== $before ? ' (changed)' : '' ) );
		}
		do_action( 'litespeed_purge_all' );
		\WP_CLI::success( sprintf( '%d of %d listings link over https; %d changed.', $https, count( $ids ), $changed ) );
	}
}
