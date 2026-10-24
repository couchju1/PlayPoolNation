<?php
/**
 * Where venue data came from, and whether a new source may overwrite it.
 *
 * Meta used on listings:
 *  _ppn_ext_{provider}  External ID per provider (one meta key each, so lookups use the meta index).
 *  _ppn_source          Origin of the record (google_places, community, owner, admin, import:<name>, ...).
 *  _ppn_source_url      Optional URL of the origin.
 *  _ppn_imported_at     When the record was first created from that source (Y-m-d H:i:s, UTC).
 *  _ppn_last_checked    When the record was last checked against a source (Y-m-d).
 *  _ppn_field_sources   field => {source, at, url} for fields with known provenance.
 *
 * PlayPoolNation's own identity is the WordPress post ID; provider IDs are only references.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Provenance {

	/** Higher rank wins. Unknown sources rank as the lowest. */
	public const RANKS = [
		'admin'            => 50,
		'owner'            => 40,
		'official_website' => 30,
		'community'        => 20,
		'web_research'     => 15,
		'google_places'    => 10,
		'openstreetmap'    => 10,
		'import'           => 5,
	];

	public static function rank( string $source ): int {
		$base = explode( ':', $source, 2 )[0];
		return self::RANKS[ $base ] ?? 0;
	}

	public static function provider_key( string $provider ): string {
		return '_ppn_ext_' . sanitize_key( $provider );
	}

	public static function set_external_id( int $post_id, string $provider, string $external_id ): void {
		if ( '' === $external_id ) {
			return;
		}
		update_post_meta( $post_id, self::provider_key( $provider ), sanitize_text_field( $external_id ) );
	}

	/** @return array<string,string> provider => id */
	public static function external_ids( int $post_id ): array {
		$out = [];
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( str_starts_with( $key, '_ppn_ext_' ) && '' !== (string) $values[0] ) {
				$out[ substr( $key, 9 ) ] = (string) $values[0];
			}
		}
		return $out;
	}

	public static function find_by_external_id( string $provider, string $external_id ): ?int {
		global $wpdb;
		if ( '' === $external_id ) {
			return null;
		}
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s AND p.post_status <> 'trash' LIMIT 1",
			self::provider_key( $provider ),
			$external_id,
			Pool_Schema::POST_TYPE
		) );
		return $id ? (int) $id : null;
	}

	public static function set_origin( int $post_id, string $source, string $url = '' ): void {
		if ( '' === (string) get_post_meta( $post_id, '_ppn_source', true ) ) {
			update_post_meta( $post_id, '_ppn_source', sanitize_text_field( $source ) );
			update_post_meta( $post_id, '_ppn_imported_at', gmdate( 'Y-m-d H:i:s' ) );
			if ( $url ) {
				update_post_meta( $post_id, '_ppn_source_url', esc_url_raw( $url ) );
			}
		}
	}

	public static function touch_checked( int $post_id, ?string $date = null ): void {
		update_post_meta( $post_id, '_ppn_last_checked', $date ?: gmdate( 'Y-m-d' ) );
	}

	/** @return array<string,array{source:string,at:string,url?:string}> */
	public static function field_sources( int $post_id ): array {
		$value = get_post_meta( $post_id, '_ppn_field_sources', true );
		return is_array( $value ) ? $value : [];
	}

	public static function record_field( int $post_id, string $field, string $source, string $url = '' ): void {
		$all = self::field_sources( $post_id );
		$all[ $field ] = array_filter( [
			'source' => sanitize_text_field( $source ),
			'at'     => gmdate( 'Y-m-d' ),
			'url'    => $url ? esc_url_raw( $url ) : '',
		] );
		update_post_meta( $post_id, '_ppn_field_sources', $all );
	}

	/**
	 * Whether a value from `$source` may replace the current value of `$field`.
	 * Empty fields can always be filled; known fields are only replaced by an equal or better source.
	 */
	public static function can_overwrite( int $post_id, string $field, string $source, bool $current_is_empty ): bool {
		if ( $current_is_empty ) {
			return true;
		}
		$current = self::field_sources( $post_id )[ $field ]['source'] ?? '';
		if ( '' === $current ) {
			// Existing value of unknown provenance: treat as owner/admin-entered and keep it.
			return self::rank( $source ) >= self::RANKS['owner'];
		}
		return self::rank( $source ) >= self::rank( $current );
	}
}
