<?php
/**
 * How pages look when shared: the listing share menu, Open Graph tags, and a
 * generated 1200x630 card for venues without a photo.
 *
 * ThinkRank prints the Open Graph block and offers no filter for its tag list
 * (only og:type), so the site name, image and article tags are corrected in
 * that block through Markup.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Format;
use PlayPoolNation\Core\Helpers\Og_Card;

defined( 'ABSPATH' ) || exit;

final class Social {

	/** Share menu options, in order. */
	private const SHARE_NETWORKS = [ 'facebook', 'twitter', 'whatsapp', 'reddit', 'copy_link', 'mail' ];

	public const DEFAULT_IMAGE = 'ppn-brand/og-default-1200x630.jpg';
	private const CARD_DIR = 'ppn-og';

	public static function boot(): void {
		add_filter( 'mylisting\\share\\get-links', [ __CLASS__, 'share_links' ], 20 );
		add_filter( 'thinkrank_og_type', [ __CLASS__, 'og_type' ], 20 );
		add_action( 'ppn_markup', [ __CLASS__, 'markup' ] );
		add_filter( 'do_parse_request', [ __CLASS__, 'serve_card' ], 1, 2 );
		add_action( 'save_post_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'refresh_card' ], 99, 1 );
		add_action( 'before_delete_post', [ __CLASS__, 'delete_card' ] );
	}

	/* ------------------------------------------------------------ share menu */

	/** @param mixed $links network => link HTML */
	public static function share_links( $links ): array {
		$links = (array) $links;
		$out = [];
		foreach ( self::SHARE_NETWORKS as $key ) {
			$html = trim( (string) ( $links[ $key ] ?? '' ) );
			if ( '' === $html || preg_match( '/href=""/', $html ) ) {
				continue;
			}
			$out[ $key ] = 'mail' === $key ? self::fix_mail_link( $html ) : $html;
		}
		return $out;
	}

	/**
	 * The theme builds the subject from the encoded site title, so "&" arrived in mail
	 * apps as "&amp;". Rebuild the mailto with a plain "[PlayPoolNation] <title>" subject.
	 */
	public static function fix_mail_link( string $html ): string {
		return (string) preg_replace_callback( '/href="mailto:([^"]*)"/', static function ( $m ) {
			parse_str( str_replace( [ '&#038;', '&amp;' ], '&', ltrim( $m[1], '?' ) ), $q );
			$subject = html_entity_decode( html_entity_decode( (string) ( $q['subject'] ?? '' ), ENT_QUOTES ), ENT_QUOTES );
			$subject = (string) preg_replace( '/^\[[^\]]*\]\s*/', '', $subject );
			$href = 'mailto:?subject=' . rawurlencode( '[' . Seo_Meta::brand() . '] ' . $subject ) . '&body=' . rawurlencode( (string) ( $q['body'] ?? '' ) );
			return 'href="' . esc_attr( $href ) . '"';
		}, $html, 1 );
	}

	/* ------------------------------------------------------------ open graph */

	/** Listings are places, events and people, not articles. */
	public static function og_type( $type ) {
		return is_singular( Pool_Schema::POST_TYPE ) ? 'website' : $type;
	}

	public static function markup(): void {
		$image = self::page_image();
		Markup::add( static fn( string $html ) => self::open_graph( $html, Seo_Meta::brand(), $image, is_singular( Pool_Schema::POST_TYPE ) ) );
	}

	/**
	 * Image for this page's share card, or null to keep ThinkRank's.
	 *
	 * @return array{url:string,width:int,height:int,type:string,alt:string,replace_any:bool}|null
	 */
	public static function page_image(): ?array {
		if ( is_singular( Pool_Schema::POST_TYPE ) ) {
			$v = Venue::get( (int) get_queried_object_id() );
			if ( $v && Venue::is_venue( $v->id() ) ) {
				$photo = self::photo( $v );
				if ( $photo ) {
					return $photo + [ 'replace_any' => true ];
				}
				if ( self::card_available() ) {
					return [
						'url'         => self::card_url( $v ),
						'width'       => Og_Card::WIDTH,
						'height'      => Og_Card::HEIGHT,
						'type'        => 'image/png',
						'alt'         => trim( $v->name() . '. ' . self::subtitle( $v ) ),
						'replace_any' => true,
					];
				}
			}
		}
		$uploads = wp_upload_dir();
		if ( ! file_exists( trailingslashit( $uploads['basedir'] ) . self::DEFAULT_IMAGE ) ) {
			return null;
		}
		return [
			'url'         => trailingslashit( $uploads['baseurl'] ) . self::DEFAULT_IMAGE,
			'width'       => Og_Card::WIDTH,
			'height'      => Og_Card::HEIGHT,
			'type'        => 'image/jpeg',
			'alt'         => Seo_Meta::brand(),
			'replace_any' => false,
		];
	}

	/** @return array{url:string,width:int,height:int,type:string,alt:string}|null */
	private static function photo( Venue $v ): ?array {
		$url = $v->photo( 'large' );
		if ( '' === $url ) {
			return null;
		}
		$id = attachment_url_to_postid( $url );
		$meta = $id ? wp_get_attachment_image_src( $id, 'large' ) : false;
		return [
			'url'    => $url,
			'width'  => $meta ? (int) $meta[1] : 0,
			'height' => $meta ? (int) $meta[2] : 0,
			'type'   => (string) wp_check_filetype( strtok( $url, '?' ) )['type'],
			'alt'    => $v->name(),
		];
	}

	/**
	 * Correct ThinkRank's Open Graph and Twitter tags in the page head.
	 *
	 * @param array|null $image See page_image().
	 */
	public static function open_graph( string $html, string $site_name, ?array $image, bool $is_listing ): string {
		$end = stripos( $html, '</head>' );
		if ( false === $end ) {
			return $html;
		}
		$head = substr( $html, 0, $end );

		$head = self::set_meta( $head, 'property', 'og:site_name', $site_name );
		if ( $is_listing ) {
			$head = (string) preg_replace( '#[ \t]*<meta property="article:[^"]*" content="[^"]*"\s*/?>\R?#', '', $head );
		}

		if ( $image ) {
			$current = self::meta_content( $head, 'property', 'og:image' );
			// Keep a page's own image (a post's featured image); replace only the logo fallback.
			$replace = $image['replace_any'] || '' === $current || str_contains( $current, '/ppn-brand/playpoolnation-' );
			if ( $replace ) {
				$head = self::set_meta( $head, 'property', 'og:image', $image['url'] );
				$head = self::set_meta( $head, 'property', 'og:image:secure_url', $image['url'], false );
				foreach ( [ 'og:image:width' => $image['width'], 'og:image:height' => $image['height'] ] as $prop => $px ) {
					$head = $px ? self::set_meta( $head, 'property', $prop, (string) $px ) : self::remove_meta( $head, 'property', $prop );
				}
				$head = $image['type'] ? self::set_meta( $head, 'property', 'og:image:type', $image['type'] ) : self::remove_meta( $head, 'property', 'og:image:type' );
				$head = self::set_meta( $head, 'property', 'og:image:alt', $image['alt'] );
				$head = self::set_meta( $head, 'name', 'twitter:image', $image['url'], false );
				$head = self::set_meta( $head, 'name', 'twitter:image:alt', $image['alt'], false );
			}
		}
		return $head . substr( $html, $end );
	}

	public static function meta_content( string $head, string $attr, string $key ): string {
		return preg_match( '#<meta ' . $attr . '="' . preg_quote( $key, '#' ) . '" content="([^"]*)"#', $head, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : '';
	}

	/** Replace a meta tag's content, or add the tag after the last og: tag when $add is true. */
	public static function set_meta( string $head, string $attr, string $key, string $value, bool $add = true ): string {
		$tag = '<meta ' . $attr . '="' . esc_attr( $key ) . '" content="' . esc_attr( $value ) . '" />';
		$pattern = '#<meta ' . $attr . '="' . preg_quote( $key, '#' ) . '" content="[^"]*"\s*/?>#';
		if ( preg_match( $pattern, $head ) ) {
			return (string) preg_replace_callback( $pattern, static fn() => $tag, $head, 1 );
		}
		if ( ! $add ) {
			return $head;
		}
		if ( preg_match_all( '#<meta property="og:[^"]*" content="[^"]*"\s*/?>#', $head, $all, PREG_OFFSET_CAPTURE ) ) {
			$last = end( $all[0] );
			$at = $last[1] + strlen( $last[0] );
			return substr( $head, 0, $at ) . "\n" . $tag . substr( $head, $at );
		}
		return $head . $tag . "\n";
	}

	public static function remove_meta( string $head, string $attr, string $key ): string {
		return (string) preg_replace( '#[ \t]*<meta ' . $attr . '="' . preg_quote( $key, '#' ) . '" content="[^"]*"\s*/?>\R?#', '', $head );
	}

	/* ------------------------------------------------------------ venue card */

	/** "Pool hall in Sioux Falls, SD". */
	public static function subtitle( Venue $v ): string {
		$type = strtolower( $v->primary_type() );
		$type = ( '' === $type || 'other' === $type ) ? 'place to play pool' : $type;
		$where = $v->city_state();
		return ucfirst( ( 'bar' === $type ? 'bar with pool tables' : $type ) . ( $where ? ' in ' . $where : '' ) );
	}

	/**
	 * Up to two stored facts for the card. Unknown values are left out.
	 *
	 * @return string[]
	 */
	public static function card_facts( Venue $v ): array {
		$facts = [];
		$total = $v->total_tables();
		if ( $total ) {
			$facts[] = sprintf( _n( '%d table', '%d tables', $total, 'playpoolnation-core' ), $total );
		}
		$brands = $v->term_names( 'table-brand' );
		$sizes = Format::sizes_label( $v->size_slugs() );
		if ( $brands ) {
			$facts[] = Format::join_list( array_slice( $brands, 0, 2 ) );
		} elseif ( $sizes ) {
			$facts[] = $sizes . ' tables';
		}
		return array_slice( $facts, 0, 2 );
	}

	private static function card_available(): bool {
		return function_exists( 'imagettftext' ) && is_readable( PPN_CORE_DIR . '/assets/fonts/Archivo-ExtraBold.ttf' );
	}

	private static function card_path( int $id ): string {
		return trailingslashit( wp_upload_dir()['basedir'] ) . self::CARD_DIR . '/place-' . $id . '.png';
	}

	public static function card_url( Venue $v ): string {
		$version = (string) strtotime( (string) $v->post->post_modified_gmt );
		return home_url( '/og/place/' . $v->id() . '.png?v=' . $version );
	}

	/** Draw and store the card. Returns the file path, or '' when it cannot be drawn. */
	public static function build_card( int $id ): string {
		$v = Venue::get( $id );
		if ( ! $v || ! Venue::is_venue( $id ) || 'publish' !== $v->post->post_status ) {
			return '';
		}
		$png = Og_Card::render( $v->name(), self::subtitle( $v ), self::card_facts( $v ), PPN_CORE_DIR . '/assets/fonts' );
		if ( '' === $png ) {
			return '';
		}
		$path = self::card_path( $id );
		wp_mkdir_p( dirname( $path ) );
		return false === file_put_contents( $path, $png ) ? '' : $path;
	}

	public static function refresh_card( int $id ): void {
		if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) {
			return;
		}
		Venue::flush_cache( $id );
		self::build_card( $id );
	}

	public static function delete_card( int $id ): void {
		$path = self::card_path( (int) $id );
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * /og/place/<id>.png: the stored card, drawn first if it is missing or older than the listing.
	 *
	 * @param bool $parse
	 */
	public static function serve_card( $parse ) {
		$path = (string) wp_parse_url( esc_url_raw( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ) ), PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( ! preg_match( '#^' . preg_quote( rtrim( $home, '/' ), '#' ) . '/og/place/(\d+)\.png$#', $path, $m ) ) {
			return $parse;
		}
		$id = (int) $m[1];
		$file = self::card_path( $id );
		$v = Venue::get( $id );
		$stale = $v && ( ! file_exists( $file ) || filemtime( $file ) < strtotime( (string) $v->post->post_modified_gmt ) );
		if ( $stale ) {
			$file = self::build_card( $id ) ?: $file;
		}
		if ( ! $v || 'publish' !== $v->post->post_status || ! file_exists( $file ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: image/png' );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'Cache-Control: public, max-age=604800' );
		readfile( $file );
		exit;
	}
}
