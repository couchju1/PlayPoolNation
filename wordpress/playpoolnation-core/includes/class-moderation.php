<?php
/**
 * Moderation: edit suggestions (private post type) and the admin hub that
 * gathers everything waiting for review.
 *
 * Suggestions never change a venue by themselves. A moderator reads the suggestion,
 * edits the venue if it is right, and marks the suggestion Approved or Rejected.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Moderation {

	public const CPT = 'ppn_suggestion';
	public const CAP = 'edit_others_posts'; // Editors and administrators.
	public const MENU = 'ppn-moderation';

	public static function boot(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column' ], 10, 2 );
		add_filter( 'post_row_actions', [ __CLASS__, 'row_actions' ], 10, 2 );
		add_action( 'admin_post_ppn_suggestion_status', [ __CLASS__, 'set_status' ] );
		add_action( 'add_meta_boxes_' . self::CPT, [ __CLASS__, 'meta_boxes' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'filter_admin_list' ] );
	}

	/** Lets review-queue links open the listings screen filtered to one listing type (?ppn_type=). */
	public static function filter_admin_list( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || Pool_Schema::POST_TYPE !== $q->get( 'post_type' ) || empty( $_GET['ppn_type'] ) ) {
			return;
		}
		$type = sanitize_key( wp_unslash( $_GET['ppn_type'] ) );
		$meta = (array) $q->get( 'meta_query' );
		$meta[] = [ 'key' => '_case27_listing_type', 'value' => $type ];
		$q->set( 'meta_query', $meta );
	}

	private static function pending_of_type( string $type ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_case27_listing_type' AND t.meta_value = %s
			 WHERE p.post_type = %s AND p.post_status = 'pending'",
			$type,
			Pool_Schema::POST_TYPE
		) );
	}

	private static function list_url( string $type, string $status = 'pending' ): string {
		return admin_url( 'edit.php?post_type=' . Pool_Schema::POST_TYPE . '&post_status=' . $status . '&ppn_type=' . $type );
	}

	public static function register(): void {
		register_post_type( self::CPT, [
			'labels'          => [
				'name'          => 'Edit Suggestions',
				'singular_name' => 'Edit Suggestion',
				'menu_name'     => 'Edit Suggestions',
				'edit_item'     => 'Review suggestion',
				'all_items'     => 'Edit Suggestions',
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => self::MENU,
			'show_in_rest'    => false,
			'supports'        => [ 'title', 'editor' ],
			'map_meta_cap'    => true,
			'capability_type' => 'post',
			'capabilities'    => [
				'create_posts'       => 'do_not_allow',
				'edit_posts'         => self::CAP,
				'edit_others_posts'  => self::CAP,
				'publish_posts'      => self::CAP,
				'read_private_posts' => self::CAP,
				'delete_posts'       => self::CAP,
			],
		] );
		register_post_status( 'ppn_approved', [ 'label' => 'Approved', 'internal' => true, 'show_in_admin_status_list' => true, 'show_in_admin_all_list' => true, 'label_count' => _n_noop( 'Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>' ) ] );
		register_post_status( 'ppn_rejected', [ 'label' => 'Rejected', 'internal' => true, 'show_in_admin_status_list' => true, 'show_in_admin_all_list' => true, 'label_count' => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>' ) ] );
	}

	public static function create_suggestion( array $data ): int {
		$venue_title = get_the_title( (int) $data['venue_id'] );
		$id = wp_insert_post( [
			'post_type'    => self::CPT,
			'post_status'  => 'pending',
			'post_title'   => sprintf( '%s: %s', Forms::SUGGEST_REASONS[ $data['reason'] ] ?? 'Suggestion', $venue_title ),
			'post_content' => $data['details'],
			'post_author'  => get_current_user_id(),
		] );
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_ppn_venue_id', (int) $data['venue_id'] );
		update_post_meta( $id, '_ppn_reason', $data['reason'] );
		update_post_meta( $id, '_ppn_name', $data['name'] );
		update_post_meta( $id, '_ppn_email', $data['email'] );
		self::notify( 'New edit suggestion for ' . $venue_title, admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		return (int) $id;
	}

	/** Short email to the site admin. Kept to one line of context and a link. */
	public static function notify( string $subject, string $link ): void {
		wp_mail( (string) get_option( 'admin_email' ), '[PlayPoolNation] ' . $subject, "Review it here:\n" . $link );
	}

	/* --------------------------------------------------------- admin list */

	public static function columns( array $cols ): array {
		return [
			'cb'          => $cols['cb'] ?? '',
			'title'       => 'Suggestion',
			'ppn_venue'   => 'Venue',
			'ppn_from'    => 'From',
			'ppn_status'  => 'Status',
			'date'        => 'Submitted',
		];
	}

	public static function column( string $col, int $post_id ): void {
		if ( 'ppn_venue' === $col ) {
			$venue = (int) get_post_meta( $post_id, '_ppn_venue_id', true );
			if ( $venue ) {
				printf( '<a href="%s">%s</a> &middot; <a href="%s" target="_blank" rel="noopener">view</a>', esc_url( get_edit_post_link( $venue ) ?: '' ), esc_html( get_the_title( $venue ) ), esc_url( get_permalink( $venue ) ) );
			}
		} elseif ( 'ppn_from' === $col ) {
			$name = (string) get_post_meta( $post_id, '_ppn_name', true );
			$email = (string) get_post_meta( $post_id, '_ppn_email', true );
			echo esc_html( $name ?: 'Anonymous' );
			if ( $email ) {
				echo '<br><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
			}
		} elseif ( 'ppn_status' === $col ) {
			$obj = get_post_status_object( (string) get_post_status( $post_id ) );
			echo esc_html( $obj ? $obj->label : '' );
		}
	}

	private static function status_url( int $post_id, string $status ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=ppn_suggestion_status&post=' . $post_id . '&status=' . $status ), 'ppn_suggestion_' . $post_id );
	}

	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( self::CPT !== $post->post_type || ! current_user_can( self::CAP ) ) {
			return $actions;
		}
		$actions = [ 'edit' => $actions['edit'] ?? '' ];
		if ( 'ppn_approved' !== $post->post_status ) {
			$actions['ppn_approve'] = '<a href="' . esc_url( self::status_url( (int) $post->ID, 'ppn_approved' ) ) . '">Mark approved</a>';
		}
		if ( 'ppn_rejected' !== $post->post_status ) {
			$actions['ppn_reject'] = '<a href="' . esc_url( self::status_url( (int) $post->ID, 'ppn_rejected' ) ) . '">Reject</a>';
		}
		return array_filter( $actions );
	}

	public static function set_status(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		check_admin_referer( 'ppn_suggestion_' . $post_id );
		if ( ! current_user_can( self::CAP ) || self::CPT !== get_post_type( $post_id ) || ! in_array( $status, [ 'ppn_approved', 'ppn_rejected', 'pending' ], true ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'playpoolnation-core' ), 403 );
		}
		wp_update_post( [ 'ID' => $post_id, 'post_status' => $status ] );
		update_post_meta( $post_id, '_ppn_reviewed_by', get_current_user_id() );
		update_post_meta( $post_id, '_ppn_reviewed_at', gmdate( 'Y-m-d H:i:s' ) );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::CPT ) );
		exit;
	}

	public static function meta_boxes( \WP_Post $post ): void {
		add_meta_box( 'ppn-suggestion-info', 'Suggestion details', static function () use ( $post ) {
			$venue = (int) get_post_meta( $post->ID, '_ppn_venue_id', true );
			echo '<p><strong>Venue:</strong> ' . ( $venue ? '<a href="' . esc_url( get_edit_post_link( $venue ) ?: '' ) . '">' . esc_html( get_the_title( $venue ) ) . '</a>' : '' ) . '</p>';
			echo '<p><strong>Reason:</strong> ' . esc_html( Forms::SUGGEST_REASONS[ (string) get_post_meta( $post->ID, '_ppn_reason', true ) ] ?? '' ) . '</p>';
			foreach ( [ 'ppn_approved' => 'Mark approved', 'ppn_rejected' => 'Reject' ] as $status => $label ) {
				echo '<a class="button" style="margin-right:6px" href="' . esc_url( self::status_url( (int) $post->ID, $status ) ) . '">' . esc_html( $label ) . '</a>';
			}
			echo '<p class="description">Approving records the decision. Edit the venue itself to apply the change.</p>';
		}, null, 'side', 'high' );
	}

	/* ------------------------------------------------------------- hub */

	public static function menu(): void {
		add_menu_page( 'PlayPoolNation', 'PlayPoolNation', self::CAP, self::MENU, [ __CLASS__, 'hub' ], 'dashicons-location-alt', 25 );
		add_submenu_page( self::MENU, 'Review queue', 'Review queue', self::CAP, self::MENU, [ __CLASS__, 'hub' ] );
	}

	public static function counts(): array {
		global $wpdb;
		$pending_venues = self::pending_of_type( Pool_Schema::VENUE_TYPE );
		$pending_events = self::pending_of_type( Pool_Schema::TOURNAMENT_TYPE );
		$pending_instructors = self::pending_of_type( Pool_Schema::INSTRUCTOR_TYPE );
		// Published instructors listing a certification that has not been checked yet.
		$unchecked = 0;
		$with_creds = get_posts( [ 'post_type' => Pool_Schema::POST_TYPE, 'post_status' => [ 'publish', 'pending' ], 'fields' => 'ids', 'posts_per_page' => 200, 'meta_key' => '_case27_listing_type', 'meta_value' => Pool_Schema::INSTRUCTOR_TYPE, 'tax_query' => [ [ 'taxonomy' => 'instructor-credential', 'operator' => 'EXISTS' ] ] ] );
		foreach ( $with_creds as $iid ) {
			if ( in_array( '', wp_list_pluck( Instructors::credentials( (int) $iid ), 'verified' ), true ) ) {
				$unchecked++;
			}
		}
		$dupes = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_ppn_possible_duplicate_of'
			 WHERE p.post_type = %s AND p.post_status = 'pending'",
			Pool_Schema::POST_TYPE
		) );
		$claims = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_status' AND m.meta_value = 'pending'
			 WHERE p.post_type = 'claim' AND p.post_status = 'publish'"
		);
		$suggestions = (int) ( wp_count_posts( self::CPT )->pending ?? 0 );
		return compact( 'pending_venues', 'dupes', 'claims', 'suggestions', 'pending_events', 'pending_instructors', 'unchecked' );
	}

	public static function hub(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$c = self::counts();
		$rows = [
			[ 'Venues waiting for approval', $c['pending_venues'], self::list_url( Pool_Schema::VENUE_TYPE ), 'New submissions from players and owners. Check the details, then publish.' ],
			[ '…of which may be duplicates', $c['dupes'], self::list_url( Pool_Schema::VENUE_TYPE ), 'Each has a "Possible duplicate of" note on its edit screen.' ],
			[ 'Events waiting for approval', $c['pending_events'], self::list_url( Pool_Schema::TOURNAMENT_TYPE ), 'Tournaments and events posted by players and organizers. Owners of claimed venues publish their own events without review.' ],
			[ 'Instructor profiles waiting for approval', $c['pending_instructors'], self::list_url( Pool_Schema::INSTRUCTOR_TYPE ), 'New instructor sign-ups. Check that the profile is a real instructor, then publish.' ],
			[ 'Instructor certifications to check', $c['unchecked'], self::list_url( Pool_Schema::INSTRUCTOR_TYPE, 'all' ), 'Certifications listed by instructors but not yet checked. Tick them on the profile once confirmed with the PBIA or issuing body.' ],
			[ 'Ownership claims', $c['claims'], admin_url( 'edit.php?post_type=claim' ), 'Approving a claim gives the claimant control of the listing and marks it Owner Verified.' ],
			[ 'Edit suggestions', $c['suggestions'], admin_url( 'edit.php?post_type=' . self::CPT . '&post_status=pending' ), 'Corrections reported by visitors. Nothing changes until you edit the venue.' ],
		];
		echo '<div class="wrap"><h1>PlayPoolNation review queue</h1><table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $rows as [ $label, $count, $url, $help ] ) {
			printf( '<tr><td style="width:60px;font-size:20px;font-weight:600">%d</td><td><a href="%s"><strong>%s</strong></a><br><span class="description">%s</span></td></tr>', (int) $count, esc_url( $url ), esc_html( $label ), esc_html( $help ) );
		}
		echo '</tbody></table></div>';
	}
}
