<?php
/**
 * Review form copy on listings, in the site's voice instead of WordPress's
 * blog-comment defaults. Blog posts and pages keep the defaults.
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Review_Form {

	public static function boot(): void {
		add_filter( 'comment_form_defaults', [ __CLASS__, 'defaults' ], 20 );
		add_filter( 'comment_form_fields', [ __CLASS__, 'fields' ], 20 );
		add_filter( 'gettext_my-listing', [ __CLASS__, 'theme_strings' ], 10, 2 );
	}

	private static function on_listing(): bool {
		return ! is_admin() && is_singular( Pool_Schema::POST_TYPE );
	}

	public static function defaults( array $defaults ): array {
		if ( ! self::on_listing() ) {
			return $defaults;
		}
		$defaults['title_reply'] = 'Write a review';
		$defaults['title_reply_to'] = 'Reply to %s';
		$defaults['comment_notes_before'] = '<p class="comment-notes">We never show your email. Name and email are required.</p>';
		return $defaults;
	}

	/**
	 * The cookie checkbox is WordPress's opt-in for remembering a guest's name and
	 * email, so it stays; only its label changes (there is no website field).
	 */
	public static function fields( array $fields ): array {
		if ( ! self::on_listing() || empty( $fields['cookies'] ) ) {
			return $fields;
		}
		$fields['cookies'] = (string) preg_replace(
			'#(<label for="wp-comment-cookies-consent">).*?(</label>)#s',
			'$1Remember my name and email on this device.$2',
			$fields['cookies']
		);
		return $fields;
	}

	public static function theme_strings( string $translation, string $text ): string {
		if ( 'No comments yet.' === $text && self::on_listing() ) {
			return 'No reviews yet. Played here? Be the first.';
		}
		return $translation;
	}
}
