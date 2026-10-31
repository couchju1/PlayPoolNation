<?php
/**
 * "The room": a venue's tables drawn to scale from above, grouped by size and,
 * when the stored models line adds up, by brand. Pure functions, unit tested.
 *
 * Only sourced counts are drawn. Tables in the total whose size isn't known are
 * drawn as outlines. Playing surfaces are to scale: 7-foot 78", 8-foot 88",
 * 9-foot 100", snooker 144", carom 112".
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core\Helpers;

defined( 'ABSPATH' ) || exit;

final class Table_Room {

	/** Size slug => [label, playing surface length in inches]. */
	public const SIZES = [
		'7-foot'  => [ '7-foot', 78 ],
		'8-foot'  => [ '8-foot', 88 ],
		'9-foot'  => [ '9-foot', 100 ],
		'snooker' => [ 'Snooker', 144 ],
		'carom'   => [ 'Carom', 112 ],
	];

	/** More than this and the rest are counted, not drawn. */
	public const MAX_DRAWN = 48;

	private const FEET = [ '7' => '7-foot', '8' => '8-foot', '9' => '9-foot' ];

	/**
	 * Groups to draw, biggest tables first.
	 *
	 * @param array<string,int> $size_counts Sourced counts by size slug.
	 * @param int|null          $total       Sourced total, or null.
	 * @param string            $models      Sourced models line ("12 Diamond 7', 2 Diamond 9'"), or ''.
	 * @return array<int,array{size:string,count:int,brand:string}> size '' means unknown.
	 */
	public static function groups( array $size_counts, ?int $total, string $models = '' ): array {
		$size_counts = array_filter( array_map( 'intval', array_intersect_key( $size_counts, self::SIZES ) ), static fn( $n ) => $n > 0 );
		$groups = self::from_models( $models, $size_counts );
		if ( null === $groups ) {
			$groups = [];
			foreach ( $size_counts as $size => $n ) {
				$groups[] = [ 'size' => $size, 'count' => $n, 'brand' => '' ];
			}
		}
		$known = array_sum( array_column( $groups, 'count' ) );
		if ( null !== $total && $total > $known ) {
			$groups[] = [ 'size' => '', 'count' => $total - $known, 'brand' => '' ];
		}
		usort( $groups, static function ( $a, $b ) {
			$la = '' === $a['size'] ? -1 : self::SIZES[ $a['size'] ][1];
			$lb = '' === $b['size'] ? -1 : self::SIZES[ $b['size'] ][1];
			return [ $lb, $b['count'], $a['brand'] ] <=> [ $la, $a['count'], $b['brand'] ];
		} );
		return $groups;
	}

	/**
	 * Brand groups from the models line, used only when every part reads as
	 * "<count> <brand words> <7|8|9>'" and the parts add up to each known size count.
	 *
	 * @param array<string,int> $size_counts
	 * @return array<int,array{size:string,count:int,brand:string}>|null
	 */
	public static function from_models( string $models, array $size_counts ): ?array {
		$models = trim( str_replace( [ '’', '′', '"' ], "'", $models ) );
		if ( '' === $models || ! $size_counts ) {
			return null;
		}
		$groups = [];
		$sums = [];
		foreach ( preg_split( '/\s*(?:,|;|\band\b)\s*/i', $models ) as $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( ! preg_match( "/^(\d{1,3})\s+(.+?)\s+([789])(?:'|\s?-?\s?(?:ft|foot|feet)\b\.?)$/i", $part, $m ) ) {
				return null;
			}
			$size = self::FEET[ $m[3] ];
			$brand = trim( (string) preg_replace( '/\s+/', ' ', $m[2] ) );
			$groups[] = [ 'size' => $size, 'count' => (int) $m[1], 'brand' => $brand ];
			$sums[ $size ] = ( $sums[ $size ] ?? 0 ) + (int) $m[1];
		}
		if ( ! $groups ) {
			return null;
		}
		foreach ( $sums as $size => $n ) {
			if ( ( $size_counts[ $size ] ?? null ) !== $n ) {
				return null;
			}
		}
		foreach ( $size_counts as $size => $n ) {
			if ( ! isset( $sums[ $size ] ) ) {
				$groups[] = [ 'size' => $size, 'count' => $n, 'brand' => '' ];
			}
		}
		return $groups;
	}

	/** "12 Diamond 7-foot", "2 9-foot", "3 more, size not known". */
	public static function caption( array $g, bool $alone = false ): string {
		if ( '' === $g['size'] ) {
			return $alone ? $g['count'] . ', size not known yet' : $g['count'] . ' more, size not known';
		}
		return trim( $g['count'] . ' ' . $g['brand'] . ' ' . self::SIZES[ $g['size'] ][0] );
	}

	/** One sentence for screen readers: "18 tables: 12 Diamond 7-foot, 4 Valley bar box 7-foot and 2 Diamond 9-foot." */
	public static function summary( array $groups ): string {
		$total = array_sum( array_column( $groups, 'count' ) );
		if ( 1 === count( $groups ) && '' === $groups[0]['size'] ) {
			return $total . ( 1 === $total ? ' table' : ' tables' ) . ', sizes not known yet.';
		}
		$parts = array_map( [ __CLASS__, 'caption' ], $groups );
		$list = count( $parts ) > 1 ? implode( ', ', array_slice( $parts, 0, -1 ) ) . ' and ' . end( $parts ) : (string) ( $parts[0] ?? '' );
		return $total . ( 1 === $total ? ' table' : ' tables' ) . ( count( $groups ) > 1 || '' !== $groups[0]['brand'] ? ': ' . $list : ', all ' . self::SIZES[ $groups[0]['size'] ][0] ) . '.';
	}

	/** The drawing. Each table reuses one symbol; its width is set by size in CSS. */
	public static function html( array $groups ): string {
		if ( ! $groups ) {
			return '';
		}
		$drawn = 0;
		$out = '<figure class="ppn-room" role="img" aria-label="' . self::esc( self::summary( $groups ) ) . '">'
			. '<svg width="0" height="0" class="ppn-room-defs" aria-hidden="true" focusable="false"><symbol id="ppn-room-table" viewBox="0 0 120 64">'
			. '<rect class="ppn-room-rail" x="1" y="1" width="118" height="62" rx="7"/>'
			. '<rect class="ppn-room-felt" x="8" y="8" width="104" height="48" rx="2"/>'
			. '<g class="ppn-room-pocket"><circle cx="9" cy="9" r="4"/><circle cx="60" cy="7" r="3.6"/><circle cx="111" cy="9" r="4"/><circle cx="9" cy="55" r="4"/><circle cx="60" cy="57" r="3.6"/><circle cx="111" cy="55" r="4"/></g>'
			. '<circle class="ppn-room-spot" cx="34" cy="32" r="1.6"/>'
			. '</symbol></svg>';
		foreach ( $groups as $g ) {
			$mod = '' === $g['size'] ? 'unknown' : $g['size'];
			$out .= '<div class="ppn-room-group" aria-hidden="true"><p class="ppn-room-cap">' . self::esc( self::caption( $g, 1 === count( $groups ) ) ) . '</p><div class="ppn-room-row">';
			for ( $i = 0; $i < $g['count']; $i++ ) {
				if ( $drawn >= self::MAX_DRAWN ) {
					break;
				}
				$out .= '<svg class="ppn-room-t ppn-room-t--' . self::esc( $mod ) . '" viewBox="0 0 120 64" style="--i:' . $drawn . '"><use href="#ppn-room-table"/></svg>';
				$drawn++;
			}
			$out .= '</div></div>';
		}
		$total = array_sum( array_column( $groups, 'count' ) );
		if ( $total > $drawn ) {
			$out .= '<p class="ppn-room-more" aria-hidden="true">+' . ( $total - $drawn ) . ' more not drawn</p>';
		}
		return $out . '<figcaption class="ppn-room-note">Drawn to scale by table size. Not a floor plan.</figcaption></figure>';
	}

	private static function esc( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
	}
}
