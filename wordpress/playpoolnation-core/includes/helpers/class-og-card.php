<?php
/**
 * 1200x630 share card for a venue, drawn with GD from the bundled fonts.
 * Pure function of its inputs so it is unit tested.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Og_Card {

	public const WIDTH = 1200;
	public const HEIGHT = 630;

	private const PAD = 80;

	/**
	 * @param string   $name     Venue name.
	 * @param string   $subtitle e.g. "Pool hall in Sioux Falls, SD".
	 * @param string[] $facts    Up to two known facts, e.g. "18 tables".
	 * @param string   $font_dir Folder with Archivo-ExtraBold.ttf and Figtree-Medium.ttf.
	 * @return string PNG bytes, or '' when GD or the fonts are missing.
	 */
	public static function render( string $name, string $subtitle, array $facts, string $font_dir ): string {
		$display = rtrim( $font_dir, '/' ) . '/Archivo-ExtraBold.ttf';
		$text = rtrim( $font_dir, '/' ) . '/Figtree-Medium.ttf';
		if ( ! function_exists( 'imagettftext' ) || ! is_readable( $display ) || ! is_readable( $text ) ) {
			return '';
		}

		$img = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
		$felt = imagecolorallocate( $img, 0x0E, 0x3B, 0x2D );
		$felt_deep = imagecolorallocate( $img, 0x0A, 0x2A, 0x20 );
		$bone = imagecolorallocate( $img, 0xF2, 0xF4, 0xF1 );
		$muted = imagecolorallocate( $img, 0x9F, 0xB2, 0xA9 );
		$brass = imagecolorallocate( $img, 0xD9, 0xB7, 0x7A );
		$chip_line = imagecolorallocate( $img, 0x5B, 0x80, 0x72 );
		imagefilledrectangle( $img, 0, 0, self::WIDTH, self::HEIGHT, $felt );
		// Rail along the bottom edge, like the cushion of a table.
		imagefilledrectangle( $img, 0, self::HEIGHT - 22, self::WIDTH, self::HEIGHT, $felt_deep );
		imagefilledrectangle( $img, 0, self::HEIGHT - 26, self::WIDTH, self::HEIGHT - 23, $brass );

		$max_w = self::WIDTH - 2 * self::PAD;
		$y = self::PAD + 30;
		self::text( $img, 28, $text, $muted, self::PAD, $y, 'PlayPoolNation' );

		// Largest size at which the name fits in three lines.
		$name = trim( preg_replace( '/\s+/', ' ', $name ) );
		foreach ( [ 84, 72, 62, 54, 46 ] as $size ) {
			$lines = self::wrap( $name, $display, $size, $max_w );
			if ( count( $lines ) <= ( $size > 62 ? 2 : 3 ) ) {
				break;
			}
		}
		$lines = array_slice( $lines, 0, 3 );
		$line_h = (int) round( $size * 1.12 );
		$y += 40 + $line_h;
		foreach ( $lines as $line ) {
			self::text( $img, $size, $display, $bone, self::PAD, $y, $line );
			$y += $line_h;
		}

		if ( '' !== $subtitle ) {
			$y += 18;
			foreach ( array_slice( self::wrap( $subtitle, $text, 34, $max_w ), 0, 2 ) as $line ) {
				self::text( $img, 34, $text, $bone, self::PAD, $y, $line );
				$y += 46;
			}
		}

		$x = self::PAD;
		$chip_y = self::HEIGHT - 70;
		foreach ( array_slice( array_filter( array_map( 'strval', $facts ), 'strlen' ), 0, 2 ) as $fact ) {
			$w = self::width( $fact, $text, 28 ) + 48;
			if ( $x + $w > self::WIDTH - self::PAD ) {
				break;
			}
			self::pill( $img, $x, $chip_y - 66, $w, 62, $chip_line );
			self::text( $img, 28, $text, $bone, $x + 24, $chip_y - 25, $fact );
			$x += $w + 16;
		}

		ob_start();
		imagepng( $img, null, 6 );
		imagedestroy( $img );
		return (string) ob_get_clean();
	}

	/** @return string[] */
	public static function wrap( string $text, string $font, int $size, int $max_w ): array {
		$lines = [];
		$line = '';
		foreach ( explode( ' ', $text ) as $word ) {
			$try = '' === $line ? $word : $line . ' ' . $word;
			if ( '' !== $line && self::width( $try, $font, $size ) > $max_w ) {
				$lines[] = $line;
				$line = $word;
			} else {
				$line = $try;
			}
		}
		if ( '' !== $line ) {
			$lines[] = $line;
		}
		return $lines;
	}

	private static function width( string $text, string $font, int $size ): int {
		$box = imagettfbbox( $size, 0, $font, $text );
		return $box ? (int) abs( $box[2] - $box[0] ) : 0;
	}

	/** @param \GdImage $img */
	private static function text( $img, int $size, string $font, int $color, int $x, int $y, string $text ): void {
		imagettftext( $img, $size, 0, $x, $y, $color, $font, $text );
	}

	/** Outlined rounded chip. @param \GdImage $img */
	private static function pill( $img, int $x, int $y, int $w, int $h, int $color ): void {
		imagesetthickness( $img, 2 );
		$r = (int) ( $h / 2 );
		imagearc( $img, $x + $r, $y + $r, $h, $h, 90, 270, $color );
		imagearc( $img, $x + $w - $r, $y + $r, $h, $h, 270, 90, $color );
		imageline( $img, $x + $r, $y, $x + $w - $r, $y, $color );
		imageline( $img, $x + $r, $y + $h, $x + $w - $r, $y + $h, $color );
	}
}
