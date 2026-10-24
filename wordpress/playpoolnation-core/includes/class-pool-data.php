<?php
/**
 * Pool data writes: Yes/No/Unknown editing in wp-admin, size-term sync from counts,
 * and provenance for owner/admin edits.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

use PlayPoolNation\Core\Helpers\Format;
use PlayPoolNation\Core\Helpers\Tri_State;

defined( 'ABSPATH' ) || exit;

final class Pool_Data {

	private const NONCE = 'ppn_pool_data';

	public static function boot(): void {
		add_action( 'add_meta_boxes_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'add_meta_box' ] );
		// Run after My Listing saved its own fields (it hooks save_post at priority 1 and saves fields at 20-40).
		add_action( 'save_post_' . Pool_Schema::POST_TYPE, [ __CLASS__, 'save_meta_box' ], 50, 2 );
		add_action( 'mylisting/admin/save-listing-data', [ __CLASS__, 'after_listing_save' ], 60, 1 );
		add_action( 'mylisting/submission/save-listing-data', [ __CLASS__, 'after_frontend_save' ], 60, 1 );
	}

	/* ------------------------------------------------------------------ API */

	/** Set Yes/No/Unknown for one term on a listing. */
	public static function set_state( int $post_id, string $taxonomy, string $slug, string $state ): void {
		if ( ! Tri_State::is_valid( $state ) ) {
			return;
		}
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $term ) {
			return;
		}
		$known_no = get_post_meta( $post_id, '_ppn_known_no', true );
		$known_no = is_array( $known_no ) ? $known_no : [];
		$list = array_values( array_diff( $known_no[ $taxonomy ] ?? [], [ $slug ] ) );

		if ( Tri_State::YES === $state ) {
			wp_add_object_terms( $post_id, (int) $term->term_id, $taxonomy );
		} else {
			wp_remove_object_terms( $post_id, (int) $term->term_id, $taxonomy );
			if ( Tri_State::NO === $state ) {
				$list[] = $slug;
			}
		}

		if ( $list ) {
			$known_no[ $taxonomy ] = $list;
		} else {
			unset( $known_no[ $taxonomy ] );
		}
		$known_no ? update_post_meta( $post_id, '_ppn_known_no', $known_no ) : delete_post_meta( $post_id, '_ppn_known_no' );
	}

	/**
	 * Keep table-size terms consistent with per-size counts:
	 * count > 0 => yes, count = 0 => no, unknown count => leave as is
	 * (a size can be known without knowing how many tables there are).
	 */
	public static function sync_size_terms( int $post_id ): void {
		foreach ( Pool_Schema::COUNT_FIELDS as $field => $def ) {
			if ( ! $def['size'] ) {
				continue;
			}
			$state = Tri_State::from_count( get_post_meta( $post_id, '_' . $field, true ) );
			if ( Tri_State::UNKNOWN !== $state ) {
				self::set_state( $post_id, 'table-size', $def['size'], $state );
			}
		}
		self::sync_card_fields( $post_id );
	}

	/**
	 * Short strings for preview cards ("12 tables", "Diamond, 7' & 9'").
	 * Empty when unknown, so My Listing hides the line.
	 */
	public static function sync_card_fields( int $post_id ): void {
		clean_object_term_cache( $post_id, Pool_Schema::POST_TYPE );
		$venue = Venue::get( $post_id );
		if ( ! $venue ) {
			return;
		}
		$total = $venue->total_tables();
		$tables = $total ? sprintf( _n( '%d table', '%d tables', $total, 'playpoolnation-core' ), $total ) : '';
		$equipment = implode( ', ', array_filter( [
			Format::join_list( $venue->term_names( 'table-brand' ) ),
			Format::sizes_label( $venue->size_slugs() ),
		] ) );
		foreach ( [ '_card-tables' => $tables, '_card-equipment' => $equipment ] as $key => $value ) {
			'' === $value ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, $value );
		}
	}

	public static function after_listing_save( $post_id ): void {
		$post_id = (int) $post_id;
		if ( Venue::is_venue( $post_id ) ) {
			self::sync_size_terms( $post_id );
		}
	}

	/** Front-end edits come from the listing owner once a claim is approved. */
	public static function after_frontend_save( $post_id ): void {
		$post_id = (int) $post_id;
		if ( ! Venue::is_venue( $post_id ) ) {
			return;
		}
		self::sync_size_terms( $post_id );
		$source = get_post_meta( $post_id, '_claimed', true ) ? 'owner' : 'community';
		foreach ( array_merge( array_keys( Pool_Schema::COUNT_FIELDS ), array_keys( Pool_Schema::TEXT_FIELDS ), array_keys( Pool_Schema::PRICE_FIELDS ) ) as $field ) {
			if ( '' !== (string) get_post_meta( $post_id, '_' . $field, true ) ) {
				Provenance::record_field( $post_id, $field, $source );
			}
		}
	}

	/* ------------------------------------------------------------- Admin UI */

	public static function add_meta_box( \WP_Post $post ): void {
		if ( ! Venue::is_venue( (int) $post->ID ) && 'auto-draft' !== $post->post_status ) {
			return;
		}
		add_meta_box( 'ppn-pool-data', 'Pool details: Yes / No / Unknown', [ __CLASS__, 'render_meta_box' ], null, 'normal', 'high' );
		add_meta_box( 'ppn-verification', 'Verification & sources', [ __CLASS__, 'render_verification_box' ], null, 'side', 'high' );
	}

	public static function render_meta_box( \WP_Post $post ): void {
		$venue = Venue::get( (int) $post->ID );
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<p>Unknown is the default. Only choose <strong>No</strong> when you know the venue does not have it; Unknown values never match a filter.</p>';
		echo '<style>.ppn-tri td,.ppn-tri th{padding:4px 8px;text-align:left}.ppn-tri th{font-weight:600}.ppn-tri h4{margin:16px 0 4px}.ppn-tri label{margin-right:10px}</style><div class="ppn-tri">';
		foreach ( Pool_Schema::tri_state_taxonomies() as $taxonomy => $label ) {
			$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name' ] );
			if ( is_wp_error( $terms ) || ! $terms ) {
				continue;
			}
			printf( '<h4>%s</h4><table><tbody>', esc_html( $label ) );
			foreach ( $terms as $term ) {
				$state = $venue ? $venue->state_of( $taxonomy, $term->slug ) : Tri_State::UNKNOWN;
				$name = sprintf( 'ppn_tri[%s][%s]', $taxonomy, $term->slug );
				echo '<tr><th>' . esc_html( html_entity_decode( $term->name ) ) . '</th><td>';
				foreach ( [ Tri_State::YES => 'Yes', Tri_State::NO => 'No', Tri_State::UNKNOWN => 'Unknown' ] as $value => $text ) {
					printf(
						'<label><input type="radio" name="%s" value="%s" %s> %s</label>',
						esc_attr( $name ),
						esc_attr( $value ),
						checked( $state, $value, false ),
						esc_html( $text )
					);
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p class="description">Table sizes also update automatically from the per-size table counts.</p></div>';
	}

	public static function render_verification_box( \WP_Post $post ): void {
		$v = Verification::get( (int) $post->ID );
		echo '<p><label for="ppn_verification_status"><strong>Status</strong></label><br><select id="ppn_verification_status" name="ppn_verification_status">';
		foreach ( Verification::STATUSES as $key => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $v['status'], $key, false ), esc_html( $label ) );
		}
		echo '</select></p>';
		printf( '<p><label for="ppn_verified_at"><strong>Verified on</strong></label><br><input type="date" id="ppn_verified_at" name="ppn_verified_at" value="%s"></p>', esc_attr( $v['date'] ) );
		printf( '<p><label for="ppn_verification_source"><strong>How it was verified</strong></label><br><input type="text" class="widefat" id="ppn_verification_source" name="ppn_verification_source" value="%s" placeholder="e.g. Called the venue"></p>', esc_attr( $v['source'] ) );

		$checked = (string) get_post_meta( $post->ID, '_ppn_last_checked', true );
		$source = (string) get_post_meta( $post->ID, '_ppn_source', true );
		$ids = Provenance::external_ids( (int) $post->ID );
		echo '<hr><p><strong>Record source:</strong> ' . esc_html( $source ?: 'not recorded' ) . '</p>';
		if ( $checked ) {
			echo '<p><strong>Last checked:</strong> ' . esc_html( $checked ) . '</p>';
		}
		foreach ( $ids as $provider => $id ) {
			echo '<p><strong>' . esc_html( $provider ) . ' ID:</strong> <code>' . esc_html( $id ) . '</code></p>';
		}
		$fields = Provenance::field_sources( (int) $post->ID );
		if ( $fields ) {
			echo '<details><summary>Field sources</summary><ul>';
			foreach ( $fields as $field => $info ) {
				echo '<li><code>' . esc_html( $field ) . '</code>: ' . esc_html( ( $info['source'] ?? '' ) . ' ' . ( $info['at'] ?? '' ) );
				if ( ! empty( $info['url'] ) ) {
					echo ' <a href="' . esc_url( $info['url'] ) . '" target="_blank" rel="noopener">source</a>';
				}
				echo '</li>';
			}
			echo '</ul></details>';
		}
	}

	public static function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$allowed = array_keys( Pool_Schema::tri_state_taxonomies() );
		$input = isset( $_POST['ppn_tri'] ) && is_array( $_POST['ppn_tri'] ) ? wp_unslash( $_POST['ppn_tri'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per value below.
		foreach ( $input as $taxonomy => $states ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( ! in_array( $taxonomy, $allowed, true ) || ! is_array( $states ) ) {
				continue;
			}
			foreach ( $states as $slug => $state ) {
				$slug = sanitize_title( $slug );
				$state = sanitize_key( $state );
				$venue = Venue::get( $post_id );
				if ( $venue && $venue->state_of( $taxonomy, $slug ) !== $state ) {
					self::set_state( $post_id, $taxonomy, $slug, $state );
					Provenance::record_field( $post_id, $taxonomy . ':' . $slug, 'admin' );
				}
			}
		}
		self::sync_size_terms( $post_id );

		if ( isset( $_POST['ppn_verification_status'] ) && current_user_can( 'manage_options' ) ) {
			$status = sanitize_key( wp_unslash( $_POST['ppn_verification_status'] ) );
			$date = isset( $_POST['ppn_verified_at'] ) ? sanitize_text_field( wp_unslash( $_POST['ppn_verified_at'] ) ) : '';
			$source = isset( $_POST['ppn_verification_source'] ) ? sanitize_text_field( wp_unslash( $_POST['ppn_verification_source'] ) ) : '';
			$current = Verification::get( $post_id );
			if ( $status !== $current['status'] || $date !== $current['date'] || $source !== $current['source'] ) {
				Verification::set( $post_id, $status, $source, $date ?: null );
			}
		}
	}
}
