<?php
/**
 * One output buffer for front-end pages, for the few theme and plugin templates
 * that offer no hook. Each fix registers a narrow transform; pages with none
 * registered are not buffered at all.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Markup {

	/** @var array<int,callable(string):string> */
	private static array $transforms = [];

	public static function boot(): void {
		add_action( 'template_redirect', [ __CLASS__, 'start' ], 999 );
	}

	/** @param callable(string):string $transform */
	public static function add( callable $transform ): void {
		self::$transforms[] = $transform;
	}

	public static function start(): void {
		if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		// Fixes register their transforms here, once the query is known.
		do_action( 'ppn_markup' );
		if ( self::$transforms ) {
			ob_start( [ __CLASS__, 'apply' ] );
		}
	}

	public static function apply( string $html ): string {
		if ( '' === $html || false === stripos( $html, '<html' ) ) {
			return $html;
		}
		foreach ( self::$transforms as $transform ) {
			$out = $transform( $html );
			if ( is_string( $out ) && '' !== $out ) {
				$html = $out;
			}
		}
		return $html;
	}
}
