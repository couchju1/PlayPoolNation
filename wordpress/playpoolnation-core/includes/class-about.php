<?php
/**
 * Venue "About" text written from the venue's data.
 *
 * Every imported venue was given the same two-paragraph description. While a
 * venue still has that text, the About block shows sentences built from its
 * stored facts instead. A description an admin or owner writes is shown as is.
 * Counts, sizes, brands and prices are used only when they have a recorded
 * source (Provenance); anything else is treated as unknown.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\About_Text;
use PlayPoolNation\Core\Helpers\Format;

defined( 'ABSPATH' ) || exit;

final class About {

	private const META_LIMIT = 160;

	public static function boot(): void {
		add_action( 'ppn_markup', [ __CLASS__, 'markup' ] );
	}

	/** The importer's description: "<Name> is a … in …. Check the hours, call ahead, or get directions below." */
	public static function is_placeholder( string $content ): bool {
		// Paragraphs are stored back to back ("…below.</p><p>Own…"), so tags become spaces.
		$text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) preg_replace( '/<[^>]+>/', ' ', html_entity_decode( $content, ENT_QUOTES ) ) ) ) );
		if ( '' === $text ) {
			return true;
		}
		return (bool) preg_match( '/^.+ is an? .+\. Check the hours, call ahead, or get directions below\.( Own or manage .+\? Claim this listing to add [^.]+\.)?$/u', $text );
	}

	/** @return array<string,mixed> Facts for About_Text::build(). */
	public static function facts( Venue $v ): array {
		$id = $v->id();
		$sources = Provenance::field_sources( $id );
		$sourced = static fn( string $key ): bool => isset( $sources[ $key ] );
		$types = $v->term_slugs( Pool_Schema::TAX_VENUE_TYPE );
		$address = $v->address_parts();

		$counts = [];
		foreach ( Pool_Schema::COUNT_FIELDS as $field => $def ) {
			$n = $def['size'] && $sourced( $field ) ? $v->count( $field ) : null;
			if ( $n ) {
				$counts[ $def['size'] ] = $n;
			}
		}
		$sizes = array_values( array_filter( $v->size_slugs(), static fn( $s ) => $sourced( 'table-size:' . $s ) || isset( $counts[ $s ] ) ) );
		$names_if_sourced = static function ( string $taxonomy, ?array $only = null ) use ( $v, $sourced ): array {
			$out = [];
			foreach ( $v->terms( $taxonomy ) as $term ) {
				if ( $sourced( $taxonomy . ':' . $term->slug ) && ( null === $only || isset( $only[ $term->slug ] ) ) ) {
					$out[] = html_entity_decode( $term->name, ENT_QUOTES );
				}
			}
			return $out;
		};
		$pricing = array_values( array_filter( $v->term_slugs( 'pool-pricing' ), static fn( $s ) => $sourced( 'pool-pricing:' . $s ) ) );

		return [
			'name'        => $v->name(),
			'type'        => (string) ( $types[0] ?? '' ),
			'street'      => (string) $address['street'],
			'city_state'  => $v->city_state(),
			'tables'      => $sourced( 'number-of-tables' ) ? $v->count( 'number-of-tables' ) : null,
			'size_counts' => $counts,
			'sizes'       => $sizes,
			'brands'      => $names_if_sourced( 'table-brand' ),
			'games'       => $names_if_sourced( Pool_Schema::TAX_AMENITY, Pool_Schema::AMENITY_GROUPS['Other games'] ),
			'pricing'     => $pricing,
			'hourly_rate' => $sourced( 'hourly-rate' ) ? Format::money( $v->field( 'hourly-rate' ) ) : '',
			'game_price'  => $sourced( 'game-price' ) ? Format::money( $v->field( 'game-price' ) ) : '',
			'leagues'     => (bool) Play::leagues( $id ),
			'photo'       => '' !== $v->photo(),
			'claimed'     => $v->is_claimed(),
			'hours'       => $v->hour_ranges(),
			'status'      => $v->status(),
		];
	}

	public static function html( Venue $v ): string {
		$text = About_Text::build( self::facts( $v ) );
		$html = '<p>' . esc_html( implode( ' ', $text['sentences'] ) ) . '</p>';
		$claim_url = $text['claim'] ? Claims::claim_url( $v->id() ) : '';
		if ( $claim_url ) {
			$html .= '<p class="ppn-claim-line">' . esc_html( $text['claim']['lead'] ) . ' <a href="' . esc_url( $claim_url ) . '">' . esc_html( $text['claim']['action'] ) . '</a>' . esc_html( $text['claim']['rest'] ) . '</p>';
		}
		return $html;
	}

	/** Meta description: the About text's opening sentences, then what the page offers. */
	public static function summary( Venue $v ): string {
		$sentences = About_Text::build( self::facts( $v ) )['sentences'];
		$text = (string) array_shift( $sentences );
		foreach ( $sentences as $s ) {
			if ( str_starts_with( $s, 'We don' ) || mb_strlen( $text . ' ' . $s ) > self::META_LIMIT - 10 ) {
				break;
			}
			$text .= ' ' . $s;
		}
		$tail = ' Hours, phone and directions on PlayPoolNation.';
		return mb_strlen( $text . $tail ) <= self::META_LIMIT ? $text . $tail : $text;
	}

	public static function markup(): void {
		if ( ! is_singular( Pool_Schema::POST_TYPE ) ) {
			return;
		}
		$v = Venue::get( (int) get_queried_object_id() );
		if ( ! $v || ! Venue::is_venue( $v->id() ) || ! self::is_placeholder( (string) $v->post->post_content ) ) {
			return;
		}
		$about = self::html( $v );
		Markup::add( static fn( string $html ) => self::replace_block( $html, $about ) );
	}

	/** Swap the body of the page's About block. */
	public static function replace_block( string $html, string $about ): string {
		return (string) preg_replace_callback(
			'#(<div class="element content-block[^"]*">\s*<div class="pf-head">\s*<div class="title-style-1">\s*<i[^>]*></i>\s*<h5>About</h5>\s*</div>\s*</div>\s*<div class="pf-body">)(?:(?!</div>).)*(</div>)#s',
			static fn( $m ) => $m[1] . $about . $m[2],
			$html
		);
	}
}
