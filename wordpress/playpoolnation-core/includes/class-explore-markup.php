<?php
/**
 * Explore pages (/places/, state, city and metro pages, /events/ ...) as served:
 * the real result count instead of "Showing 1 result", no no-results message
 * above results, and no {{ }} placeholders. See Helpers\Html_Fix::explore().
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Explore_Markup {

	public static function boot(): void {
		add_action( 'ppn_markup', [ __CLASS__, 'markup' ] );
	}

	public static function markup(): void {
		if ( is_page() ) {
			Markup::add( [ Helpers\Html_Fix::class, 'explore' ] );
		}
	}
}
