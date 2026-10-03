<?php
/**
 * Elementor page content written by migrations: explore pages for tournaments and
 * leagues, the Add a Venue page, navigation, and homepage copy updates.
 *
 * Pages stay fully editable in Elementor afterwards; this only seeds them.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Pages {

	private static function uid(): string {
		return substr( md5( uniqid( '', true ) ), 0, 7 );
	}

	private static function pad( int $t, int $r, int $b, int $l ): array {
		return [ 'unit' => 'px', 'top' => (string) $t, 'right' => (string) $r, 'bottom' => (string) $b, 'left' => (string) $l, 'isLinked' => false ];
	}

	public static function heading( string $text, string $tag, int $size, string $color, array $extra = [] ): array {
		return [ 'id' => self::uid(), 'elType' => 'widget', 'widgetType' => 'heading', 'elements' => [], 'settings' => array_merge( [
			'title' => $text, 'header_size' => $tag, 'title_color' => $color,
			'typography_typography' => 'custom', 'typography_font_family' => 'Archivo', 'typography_font_weight' => '800',
			'typography_font_size' => [ 'unit' => 'px', 'size' => $size ],
			'typography_font_size_mobile' => [ 'unit' => 'px', 'size' => (int) round( $size * 0.66 ) ],
			'typography_line_height' => [ 'unit' => 'em', 'size' => 1.06 ],
			'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -round( $size / 45, 1 ) ],
		], $extra ) ];
	}

	public static function text( string $html, string $color, int $size = 18, array $extra = [] ): array {
		return [ 'id' => self::uid(), 'elType' => 'widget', 'widgetType' => 'text-editor', 'elements' => [], 'settings' => array_merge( [
			'editor' => $html, 'text_color' => $color,
			'typography_typography' => 'custom', 'typography_font_family' => 'Figtree', 'typography_font_weight' => '400',
			'typography_font_size' => [ 'unit' => 'px', 'size' => $size ],
			'typography_line_height' => [ 'unit' => 'em', 'size' => 1.55 ],
		], $extra ) ];
	}

	public static function shortcode( string $code, string $class = '' ): array {
		return [ 'id' => self::uid(), 'elType' => 'widget', 'widgetType' => 'shortcode', 'elements' => [], 'settings' => [ 'shortcode' => $code, '_css_classes' => $class ] ];
	}

	public static function button( string $text, string $url ): array {
		return [ 'id' => self::uid(), 'elType' => 'widget', 'widgetType' => 'button', 'elements' => [], 'settings' => [
			'text' => $text, 'link' => [ 'url' => $url, 'is_external' => '', 'nofollow' => '' ],
			'background_color' => '#2F6FE4', 'button_text_color' => '#FFFFFF', 'hover_color' => '#FFFFFF', 'button_background_hover_color' => '#245BC2',
			'border_radius' => [ 'unit' => 'px', 'top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6', 'isLinked' => true ],
			'text_padding' => self::pad( 16, 28, 16, 28 ),
			'typography_typography' => 'custom', 'typography_font_family' => 'Figtree', 'typography_font_weight' => '600', 'typography_font_size' => [ 'unit' => 'px', 'size' => 16 ],
		] ];
	}

	public static function container( array $settings, array $elements, bool $inner = false ): array {
		return [ 'id' => self::uid(), 'elType' => 'container', 'isInner' => $inner, 'settings' => $settings, 'elements' => $elements ];
	}

	private static function save( int $id, array $data, string $css = '' ): void {
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		$settings = get_post_meta( $id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : [];
		$settings['hide_title'] = 'yes';
		if ( $css ) {
			$settings['custom_css'] = $css;
		}
		update_post_meta( $id, '_elementor_page_settings', $settings );
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/** @param array<string,string> $actions label => URL, shown as links under the intro */
	private static function hero( string $heading, string $intro, array $actions = [] ): array {
		$elements = [
			self::heading( $heading, 'h1', 48, '#F2F4F1' ),
			self::text( '<p style="max-width:62ch;margin:0">' . esc_html( $intro ) . '</p>', '#C9D6CF', 18 ),
		];
		if ( $actions ) {
			$links = '';
			foreach ( $actions as $label => $url ) {
				$links .= '<a class="ppn-quick" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
			$elements[] = [ 'id' => self::uid(), 'elType' => 'widget', 'widgetType' => 'html', 'elements' => [], 'settings' => [ 'html' => '<nav class="ppn-quick-links" aria-label="Page actions">' . $links . '</nav>' ] ];
		}
		return self::container( [
			'content_width' => 'boxed', 'flex_gap' => [ 'size' => 12, 'unit' => 'px', 'column' => '12', 'row' => '12' ],
			'background_background' => 'classic', 'background_color' => '#0E3B2D',
			'padding' => self::pad( 52, 24, 40, 24 ), 'padding_mobile' => self::pad( 36, 20, 28, 20 ),
		], $elements );
	}

	/** Two columns on a light background: main content and an aside. */
	private static function two_columns( array $main, string $aside_html ): array {
		return self::container( [
			'content_width' => 'boxed', 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_align_items' => 'flex-start',
			'flex_gap' => [ 'size' => 48, 'unit' => 'px', 'column' => '48', 'row' => '32' ],
			'background_background' => 'classic', 'background_color' => '#F2F4F1',
			'padding' => self::pad( 48, 24, 72, 24 ), 'padding_mobile' => self::pad( 32, 20, 48, 20 ),
		], [
			self::container( [ 'content_width' => 'full', 'width' => [ 'unit' => '%', 'size' => 60 ], 'width_mobile' => [ 'unit' => '%', 'size' => 100 ], 'padding' => self::pad( 0, 0, 0, 0 ) ], $main, true ),
			self::container( [ 'content_width' => 'full', 'width' => [ 'unit' => '%', 'size' => 36 ], 'width_mobile' => [ 'unit' => '%', 'size' => 100 ], 'padding' => self::pad( 0, 0, 0, 0 ) ], [ [ 'id' => self::uid(), 'elType' => 'widget', 'widgetType' => 'html', 'elements' => [], 'settings' => [ 'html' => $aside_html ] ] ], true ),
		] );
	}

	/** Explore page for one listing type (events, leagues or instructors). */
	public static function write_explore( int $id, string $type, string $heading, string $intro, array $actions = [] ): void {
		$explore = [ 'id' => self::uid(), 'elType' => 'widget', 'widgetType' => 'case27-explore-widget', 'elements' => [], 'settings' => [
			'27_title' => $heading, '27_template' => 'explore-1', '27_mobile_view' => 'results', '27_finder_columns' => 'finder-one-columns',
			'27_explore_pagination' => 'load-more', '27_disable_isotope' => 'yes', '27_drag_search' => 'yes',
			'27_listing_types' => [ [ '_id' => self::uid(), 'type' => $type ] ], 'types_template' => 'dropdown', '27_first_load_method' => 'server',
			'27_map_skin' => 'skin3', 'cts_map_default_lat' => 39.5, 'cts_map_default_lng' => -98.35, 'cts_map_default_zoom' => 4,
			'cts_map_min_zoom' => 3, 'cts_map_max_zoom' => 18,
		] ];
		self::save( $id, [
			self::hero( $heading, $intro, $actions ),
			self::container( [ 'content_width' => 'full', 'padding' => self::pad( 0, 0, 0, 0 ), 'css_classes' => 'ppn-explore' ], [ $explore ] ),
		] );
	}

	public static function write_post_event( int $id ): void {
		$aside = '<div class="ppn-aside"><h2>Good to know</h2>'
			. '<p><strong>Own or manage the venue?</strong> Claim your venue listing and your events go live the moment you post them. Find your venue and choose <strong>Claim this venue</strong>.</p>'
			. '<p><strong>Weekly tournament?</strong> Post it once and choose how often it repeats. It shows the next date automatically.</p>'
			. '<p><strong>Where it appears:</strong> on the Events page, on the venue\'s page, and in search near the venue.</p>'
			. '<p>Need to change or cancel it later? Owners can edit events from their account under My Listings. Anyone else can use <strong>Suggest an edit</strong> on the venue page.</p></div>';
		self::save( $id, [
			self::hero( 'Post an event', 'Tournaments, league sign-ups, clinics, free pool nights and more. Tell players what is happening and where.' ),
			self::two_columns( [ self::shortcode( '[ppn_post_event]' ) ], $aside ),
		] );
	}

	/** Instructor sign-up: the theme's add-listing form, set to the instructor type. */
	public static function write_instructor_signup( int $id ): void {
		$form = [ 'id' => self::uid(), 'elType' => 'widget', 'widgetType' => 'case27-add-listing-widget', 'elements' => [], 'settings' => [
			'listing_types' => [ [ '_id' => self::uid(), 'listing_type' => Pool_Schema::INSTRUCTOR_TYPE, 'color' => '#2F6FE4' ] ],
			'size' => 'medium', 'packages_layout' => 'regular',
		] ];
		$aside = '<div class="ppn-aside"><h2>How it works</h2>'
			. '<p><strong>Free.</strong> Create an account, fill in your profile, and we check it before it goes live (usually within a few days).</p>'
			. '<p><strong>Your privacy.</strong> Players see your city, not your address. Add the venues where you teach so you also appear on their pages.</p>'
			. '<p><strong>Certifications.</strong> List any you hold. We contact the issuing body (such as the PBIA) and add a Verified badge once confirmed.</p>'
			. '<p><strong>Reviews.</strong> Students can leave reviews on your profile.</p>'
			. '<p>Already have a profile? Update it any time from your account under My Listings.</p></div>';
		self::save( $id, [
			self::hero( 'Teach pool? Create your instructor profile', 'Help players near you find lessons. Share what you teach, your certifications, lesson formats and rates.' ),
			self::two_columns( [ $form ], $aside ),
		] );
	}

	public static function write_add_venue( int $id ): void {
		$owner = get_page_by_path( 'list-your-venue' );
		$aside = '<div class="ppn-aside"><h2>Own or manage a venue?</h2>'
			. '<p>Create a free account and list your venue with full details: tables, pricing, leagues, hours and photos. You can update it any time.</p>'
			. ( $owner ? '<p><a class="ppn-button" href="' . esc_url( get_permalink( $owner ) ) . '">List your venue</a></p>' : '' )
			. '<p>Already on PlayPoolNation? Open your venue page and choose <strong>Claim this venue</strong>.</p></div>';
		self::save( $id, [
			self::hero( 'Add a venue', 'Know a place to play that is missing? Tell us about it. We check every submission before it goes on the map.' ),
			self::container( [
				'content_width' => 'boxed', 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_align_items' => 'flex-start',
				'flex_gap' => [ 'size' => 48, 'unit' => 'px', 'column' => '48', 'row' => '32' ],
				'background_background' => 'classic', 'background_color' => '#F2F4F1',
				'padding' => self::pad( 48, 24, 72, 24 ), 'padding_mobile' => self::pad( 32, 20, 48, 20 ),
			], [
				self::container( [ 'content_width' => 'full', 'width' => [ 'unit' => '%', 'size' => 60 ], 'width_mobile' => [ 'unit' => '%', 'size' => 100 ], 'padding' => self::pad( 0, 0, 0, 0 ) ], [ self::shortcode( '[ppn_add_venue]' ) ], true ),
				self::container( [ 'content_width' => 'full', 'width' => [ 'unit' => '%', 'size' => 36 ], 'width_mobile' => [ 'unit' => '%', 'size' => 100 ], 'padding' => self::pad( 0, 0, 0, 0 ) ], [ [ 'id' => self::uid(), 'elType' => 'widget', 'widgetType' => 'html', 'elements' => [], 'settings' => [ 'html' => $aside ] ] ], true ),
			] ),
		] );
	}

	/** Primary menu, header and footer links, and account menu labels. */
	public static function write_navigation( array $pages ): void {
		$menu = wp_get_nav_menu_object( 'Main Menu' );
		$explore = (int) get_option( 'options_general_explore_listings_page' );
		$items = array_filter( [
			'Find Places' => $explore,
			'Events'      => $pages['events'] ?? 0,
			'Leagues'     => $pages['leagues'] ?? 0,
			'Instructors' => $pages['instructors'] ?? 0,
			'Add a Venue' => $pages['add'] ?? 0,
		] );
		if ( $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				wp_delete_post( (int) $item->ID, true );
			}
			$pos = 1;
			foreach ( $items as $title => $page_id ) {
				wp_update_nav_menu_item( $menu->term_id, 0, [ 'menu-item-title' => $title, 'menu-item-object' => 'page', 'menu-item-object-id' => $page_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => $pos++ ] );
			}
		}
		if ( ! empty( $pages['add'] ) ) {
			update_option( 'options_header_call_to_action_label', 'Add a venue' );
			update_option( 'options_header_call_to_action_links_to', (int) $pages['add'] );
		}

		// Account menu (My Listing user menu): rename and drop paid-only items.
		$labels = [ 'Bookmarks' => 'Saved Places', 'Account details' => 'Profile', 'My Venues' => 'My Listings' ];
		foreach ( wp_get_nav_menus() as $m ) {
			foreach ( (array) wp_get_nav_menu_items( $m->term_id ) as $item ) {
				$plain = trim( preg_replace( '/\[[^\]]*\]/', '', $item->title ) );
				if ( 'Promotions' === $plain ) {
					wp_delete_post( (int) $item->ID, true );
				} elseif ( isset( $labels[ $plain ] ) ) {
					wp_update_post( [ 'ID' => (int) $item->ID, 'post_title' => str_replace( $plain, $labels[ $plain ], $item->title ) ] );
				}
			}
		}

		// Footer links (Elementor global footer).
		$footer = (int) get_option( 'ppn_footer_template', 669 );
		$data = json_decode( (string) get_post_meta( $footer, '_elementor_data', true ), true );
		if ( is_array( $data ) ) {
			$links = '';
			foreach ( $items as $title => $page_id ) {
				$links .= '<a href="' . esc_url( get_permalink( $page_id ) ) . '">' . esc_html( $title ) . '</a>';
			}
			array_walk_recursive( $data, static function ( &$value, $key ) use ( $links ) {
				if ( 'html' === $key && is_string( $value ) && str_contains( $value, 'ppn-foot-links' ) ) {
					$value = preg_replace( '#<nav class="ppn-foot-links"[^>]*>.*?</nav>#s', '<nav class="ppn-foot-links" aria-label="Footer">' . $links . '</nav>', $value );
				}
			} );
			update_post_meta( $footer, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		}
		self::write_footer_legal();
	}

	/** Terms, Privacy and Refund links plus the contact email (from the Terms page), under the footer links. */
	public static function write_footer_legal(): void {
		$links = [];
		foreach ( [ 'terms-of-service' => 'Terms of Service', 'privacy-policy' => 'Privacy Policy', 'refund-policy' => 'Refund Policy' ] as $slug => $label ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				$links[] = '<a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		$terms = get_page_by_path( 'terms-of-service' );
		if ( $terms && preg_match( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', wp_strip_all_tags( $terms->post_content ), $m ) && is_email( $m[0] ) ) {
			$links[] = '<a href="mailto:' . esc_attr( $m[0] ) . '">' . esc_html( $m[0] ) . '</a>';
		}
		$nav = $links ? '<nav class="ppn-foot-legal" aria-label="Legal">' . implode( '', $links ) . '</nav>' : '';
		$footer = (int) get_option( 'ppn_footer_template', 669 );
		$data = json_decode( (string) get_post_meta( $footer, '_elementor_data', true ), true );
		if ( ! is_array( $data ) ) {
			return;
		}
		array_walk_recursive( $data, static function ( &$value, $key ) use ( $nav ) {
			if ( 'html' !== $key || ! is_string( $value ) || ! str_contains( $value, 'ppn-foot-links' ) ) {
				return;
			}
			$value = preg_replace( '#<nav class="ppn-foot-legal"[^>]*>.*?</nav>#s', '', $value );
			$value = preg_replace( '#(<nav class="ppn-foot-links"[^>]*>.*?</nav>)#s', '$1' . $nav, $value, 1 );
		} );
		update_post_meta( $footer, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/** Homepage copy and sections per the discovery brief. Matches the widgets seeded earlier. */
	public static function write_home(): void {
		$home = (int) get_option( 'page_on_front' );
		$data = json_decode( (string) get_post_meta( $home, '_elementor_data', true ), true );
		if ( ! $home || ! is_array( $data ) ) {
			return;
		}
		$add = get_page_by_path( 'add-a-venue' );
		$add_url = $add ? get_permalink( $add ) : home_url( '/' );
		$owner = get_page_by_path( 'list-your-venue' );
		$feats = '<dl class="ppn-feats">'
			. '<div><dt>Table sizes</dt><dd>7-foot bar boxes, 8-foot, or regulation 9-foot. Know what you are walking into.</dd></div>'
			. '<div><dt>Table brands</dt><dd>Diamond, Valley, Brunswick and more, where we know them. Unknown stays unknown.</dd></div>'
			. '<div><dt>Pricing</dt><dd>Coin-op, by the hour, per game or free pool nights.</dd></div>'
			. '<div><dt>Leagues</dt><dd>APA, BCA, USAPL and local leagues, with the night they play.</dd></div>'
			. '<div><dt>Events</dt><dd>Tournaments, league sign-ups and clinics, with game, entry fee and added money.</dd></div>'
			. '<div><dt>Amenities</dt><dd>Food, full bar, age policy, parking and the rest.</dd></div></dl>';
		$community = '<p style="margin:0;max-width:56ch">Add it in a minute and we will check it. Spot something out of date? Use <strong>Suggest an edit</strong> on any venue page. Own or manage a venue? Claim it to keep it accurate'
			. ( $owner ? ', or <a href="' . esc_url( get_permalink( $owner ) ) . '" style="color:#245BC2;font-weight:600;text-decoration:underline">list your venue</a> with full details' : '' ) . '.</p>';

		$walk = static function ( array &$elements ) use ( &$walk, $feats, $community, $add_url ): void {
			$insert_after = null;
			foreach ( $elements as $i => &$el ) {
				$s = &$el['settings'];
				$w = $el['widgetType'] ?? '';
				if ( 'heading' === $w && isset( $s['title'] ) ) {
					if ( str_contains( $s['title'], 'Every pool table' ) || str_contains( $s['title'], 'Find your next' ) ) {
						$s['title'] = 'Find your next <em>place to play.</em>';
					} elseif ( str_contains( $s['title'], 'Built for the night' ) ) {
						$s['title'] = 'The details pool players <em>actually care about.</em>';
					} elseif ( str_contains( $s['title'], 'belongs on the map' ) || str_contains( $s['title'], 'missing?' ) ) {
						$s['title'] = 'Know a place we are missing?';
					}
				} elseif ( 'text-editor' === $w && isset( $s['editor'] ) ) {
					if ( str_contains( $s['editor'], 'verified pool halls and bars with tables' ) ) {
						$s['editor'] = '<p>Discover pool halls, billiards clubs, bars and other great places to play pool near you.</p>';
					} elseif ( str_contains( $s['editor'], 'Listing your venue is free' ) || str_contains( $s['editor'], 'Add it in a minute' ) ) {
						$s['editor'] = $community;
					}
				} elseif ( 'html' === $w && isset( $s['html'] ) && str_contains( $s['html'], 'ppn-feats' ) ) {
					$s['html'] = $feats;
				} elseif ( 'button' === $w && isset( $s['text'] ) && in_array( $s['text'], [ 'Add your hall', 'Add a venue' ], true ) ) {
					$s['text'] = 'Add a venue';
					$s['link']['url'] = $add_url;
				}
				unset( $s );
				if ( 'case27-basic-search-widget' === $w ) {
					$next = $elements[ $i + 1 ] ?? null;
					if ( ! $next || ( $next['settings']['shortcode'] ?? '' ) !== '[ppn_quick_links]' ) {
						$insert_after = $i;
					}
				}
				if ( ! empty( $el['elements'] ) ) {
					$walk( $el['elements'] );
				}
			}
			unset( $el );
			if ( null !== $insert_after ) {
				array_splice( $elements, $insert_after + 1, 0, [ self::shortcode( '[ppn_quick_links]', 'ppn-hero-quick' ) ] );
			}
		};
		$walk( $data );
		update_post_meta( $home, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	public static function write_my_pool( int $id ): void {
		self::save( $id, [
			self::hero( 'My Pool', 'Places near you, your saved places, and the tournaments and events coming up.' ),
			self::container( [
				'content_width' => 'boxed', 'background_background' => 'classic', 'background_color' => '#F2F4F1',
				'padding' => self::pad( 32, 24, 64, 24 ), 'padding_mobile' => self::pad( 24, 16, 48, 16 ),
			], [ self::shortcode( '[ppn_my_pool]' ) ] ),
		] );
	}

	public static function write_sign_in( int $id, bool $join ): void {
		self::save( $id, [
			self::hero( $join ? 'Create your free account' : 'Sign in', $join ? 'Save your favorite pool halls and see what is coming up near you.' : 'Welcome back. Sign in to see your places and what is coming up.' ),
			self::container( [
				'content_width' => 'boxed', 'background_background' => 'classic', 'background_color' => '#F2F4F1',
				'padding' => self::pad( 32, 24, 64, 24 ), 'padding_mobile' => self::pad( 24, 16, 48, 16 ),
			], [ self::shortcode( $join ? '[ppn_sign_in tab="register"]' : '[ppn_sign_in]' ) ] ),
		] );
	}

	/** Hero plus one shortcode on the light background (promote and advertise pages). */
	public static function write_shortcode_page( int $id, string $heading, string $intro, string $shortcode ): void {
		self::save( $id, [
			self::hero( $heading, $intro ),
			self::container( [
				'content_width' => 'boxed', 'background_background' => 'classic', 'background_color' => '#F2F4F1',
				'padding' => self::pad( 32, 24, 64, 24 ), 'padding_mobile' => self::pad( 24, 16, 48, 16 ),
			], [ self::shortcode( $shortcode ) ] ),
		] );
	}
}
