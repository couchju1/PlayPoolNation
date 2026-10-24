<?php
/**
 * String-level fixes for front-end HTML that themes and other plugins print
 * without a hook. Pure functions, so they are unit tested without WordPress.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Html_Fix {

	/** Hostinger's temporary domains, e.g. gray-hedgehog-574573.hostingersite.com. */
	public const TEMP_HOST = '[a-z0-9-]+\.hostingersite\.com';

	/**
	 * Point anything on a Hostinger temporary domain at the live site: plain and
	 * JSON-escaped URLs (https:\/\/...), scheme-relative URLs and bare host names.
	 *
	 * @param string $origin The live site's scheme and host, e.g. https://playpoolnation.com.
	 * @return array{0:string,1:int} The fixed text and the number of replacements.
	 */
	public static function live_domain( string $text, string $origin ): array {
		if ( false === stripos( $text, 'hostingersite.com' ) ) {
			return [ $text, 0 ];
		}
		$host = (string) parse_url( $origin, PHP_URL_HOST );
		if ( '' === $host || preg_match( '#^' . self::TEMP_HOST . '$#i', $host ) ) {
			return [ $text, 0 ]; // The site itself runs on a temporary domain.
		}
		$origin = rtrim( $origin, '/' );
		$text = (string) preg_replace_callback(
			'#(?:https?:)?(//|\\\\/\\\\/)' . self::TEMP_HOST . '#i',
			static function ( array $m ) use ( $origin ): string {
				$url = 0 === stripos( $m[0], 'http' ) ? $origin : substr( $origin, (int) strpos( $origin, '//' ) );
				return '//' === $m[1] ? $url : str_replace( '/', '\/', $url );
			},
			$text,
			-1,
			$urls
		);
		$text = (string) preg_replace( '#' . self::TEMP_HOST . '#i', $host, $text, -1, $hosts );
		return [ $text, $urls + $hosts ];
	}

	/**
	 * Apply live_domain() inside stored values: strings, arrays and plain objects.
	 * Serialized data must be unserialized first, since lengths change.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function live_domain_deep( $value, string $origin, int &$count = 0 ) {
		if ( is_string( $value ) ) {
			[ $value, $n ] = self::live_domain( $value, $origin );
			$count += $n;
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::live_domain_deep( $v, $origin, $count );
			}
		} elseif ( $value instanceof \stdClass ) {
			foreach ( get_object_vars( $value ) as $k => $v ) {
				$value->$k = self::live_domain_deep( $v, $origin, $count );
			}
		}
		return $value;
	}

	/**
	 * My Listing prints the explore page's Vue templates as HTML, so the page as
	 * served (to crawlers, and with JavaScript off) holds "Showing 1 result", the
	 * no-results message and {{ }} placeholders. The result count is filled in from
	 * the results the theme already prefetched; Vue still replaces every value on mount.
	 */
	public static function explore( string $html ): string {
		if ( ! str_contains( $html, 'cts-explore' ) ) {
			return $html;
		}
		$found = preg_match( '#"found_posts":(\d+)#', $html, $f ) ? (int) $f[1] : 0;
		if ( $found > 0 ) {
			$per_page = preg_match( '#"posts_per_page":(\d+)#', $html, $p ) ? (int) $p[1] : $found;
			$html = self::explore_count( $html, min( $found, max( 1, $per_page ) ), $found );
			$html = self::explore_no_results( $html );
		}
		return self::outside_scripts( $html, [ __CLASS__, 'mustache_to_v_text' ] );
	}

	/** One result count, printed with the page, in place of the two template branches. */
	private static function explore_count( string $html, int $showing, int $found ): string {
		$one = "'Showing <b>' + showing + '</b> result'";
		$many = "'Showing <b>' + showing + '</b> out of <b>' + foundPosts + '</b> results'";
		$html = (string) preg_replace_callback(
			'#<span class="rslt-nr" v-if="showing === 1">.*?</span>(\s*)<span class="rslt-nr" v-else-if="showing <= foundPosts">.*?</span>#s',
			static fn( array $m ): string => sprintf(
				'<span class="rslt-nr" v-if="showing === 1" v-html="%s">%s</span>%s<span class="rslt-nr" v-else-if="showing <= foundPosts" v-html="%s">%s</span>',
				htmlspecialchars( $one, ENT_QUOTES ),
				1 === $found ? 'Showing <b>1</b> result' : '',
				$m[1],
				htmlspecialchars( $many, ENT_QUOTES ),
				1 === $found ? '' : sprintf( 'Showing <b>%s</b> out of <b>%d</b> results', 1 === $showing ? '1' : '1-' . $showing, $found )
			),
			$html
		);
		// The count is right as printed, so it no longer waits for Vue.
		return (string) preg_replace( '#(<span\b[^>]*\bclass="fl-results-no[^"]*"[^>]*?)\s+v-cloak(\s*>)#', '$1$2', $html );
	}

	/** Results exist, so the no-results message stays out of the page until Vue needs it. */
	private static function explore_no_results( string $html ): string {
		return (string) preg_replace_callback(
			'#(<div class="no-results-wrapper"[^>]*>)(.*?)(</div>)#s',
			static function ( array $m ): string {
				$inner = (string) preg_replace( '#There are no listings matching your search\.?#', 'Nothing matches your search.', $m[2] );
				return $m[1] . '<template>' . $inner . '</template>' . $m[3];
			},
			$html
		);
	}

	/**
	 * <label>{{label}}</label> becomes <label v-text="label"></label>: the same output
	 * once Vue mounts, and no raw placeholder before that. Only elements holding text
	 * alone are changed; anything with child tags or a ">" in its attributes is left.
	 */
	public static function mustache_to_v_text( string $html ): string {
		$html = (string) preg_replace_callback(
			'#<([a-z][a-z0-9-]*)(\s[^<>]*)?>([^<>]*\{\{[^<>]*?\}\}[^<>]*)</\1>#i',
			static function ( array $m ): string {
				$attrs = $m[2] ?? '';
				if ( preg_match( '#\sv-(text|html)=#', $attrs ) ) {
					return $m[0];
				}
				$parts = preg_split( '#\{\{(.*?)\}\}#s', trim( $m[3] ), -1, PREG_SPLIT_DELIM_CAPTURE );
				$expr = [];
				foreach ( $parts as $i => $part ) {
					if ( $i % 2 ) {
						$expr[] = '(' . trim( $part ) . ')';
					} elseif ( '' !== $part ) {
						$text = preg_replace( '/\s+/', ' ', html_entity_decode( $part, ENT_QUOTES | ENT_HTML5 ) );
						$expr[] = "'" . str_replace( [ '\\', "'" ], [ '\\\\', "\\'" ], $text ) . "'";
					}
				}
				$expr = 1 === count( $expr ) ? trim( $expr[0], '()' ) : implode( ' + ', $expr );
				return sprintf( '<%1$s%2$s v-text="%3$s"></%1$s>', $m[1], $attrs, htmlspecialchars( $expr, ENT_QUOTES ) );
			},
			$html
		);
		// Term cards: a count, or "No listings" for types without one.
		return str_replace( '<h6 v-else>No listings</h6>', '<h6 v-else v-text="\'None\'"></h6>', $html );
	}

	/**
	 * The pool game (ppn-pool-game plugin) loads in an iframe as soon as it nears the
	 * screen, which pulls in the whole game on the homepage. Hold the address until
	 * the visitor presses Play; assets/ppn.js loads it then.
	 */
	public static function pool_game_on_click( string $html ): string {
		if ( ! str_contains( $html, 'ppn-pg__frame' ) ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'#<div class="ppn-pg__frame">(\s*)<iframe\b([^>]*?)\ssrc="([^"]*)"([^>]*)></iframe>#',
			static fn( array $m ): string => sprintf(
				'<div class="ppn-pg__frame" data-ppn-idle>%s<iframe%s data-src="%s"%s></iframe><button type="button" class="ppn-pg__play" data-ppn-play>Play</button>',
				$m[1],
				$m[2],
				$m[3],
				$m[4]
			),
			$html
		);
	}

	/**
	 * Run a transform on the HTML outside <script>, <style> and <textarea> elements,
	 * whose contents are not markup (My Listing's map marker templates use {{ }} too).
	 *
	 * @param callable(string):string $transform
	 */
	public static function outside_scripts( string $html, callable $transform ): string {
		$pieces = preg_split( '#(<(script|style|textarea)\b.*?</\2\s*>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $pieces ) ) {
			return $html;
		}
		$out = '';
		for ( $i = 0, $n = count( $pieces ); $i < $n; $i++ ) {
			if ( 0 === $i % 3 ) {
				$out .= $transform( $pieces[ $i ] );
			} elseif ( 1 === $i % 3 ) {
				$out .= $pieces[ $i ];
			}
		}
		return $out;
	}
}
