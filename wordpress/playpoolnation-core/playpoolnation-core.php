<?php
/**
 * Plugin Name:       PlayPoolNation Core
 * Description:       Pool-specific data, trust, community and SEO features for PlayPoolNation, built on the My Listing theme.
 * Version:     1.6.4
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            PlayPoolNation
 * License:           GPL-2.0-or-later
 * Text Domain:       playpoolnation-core
 *
 * @package PlayPoolNation\Core
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'PPN_CORE_VERSION' ) ) {
	// Already loaded (e.g. both the sandbox copy and the plugin copy are present).
	return;
}

define( 'PPN_CORE_VERSION', '1.6.4' );
define( 'PPN_CORE_DB_VERSION', 3 );
define( 'PPN_CORE_FILE', __FILE__ );
define( 'PPN_CORE_DIR', __DIR__ );
// Works whether the plugin lives in wp-content/plugins or another wp-content subfolder.
define( 'PPN_CORE_URL', str_replace( wp_normalize_path( WP_CONTENT_DIR ), content_url(), wp_normalize_path( __DIR__ ) ) );

require_once PPN_CORE_DIR . '/includes/helpers/class-address.php';
require_once PPN_CORE_DIR . '/includes/helpers/class-dedupe.php';
require_once PPN_CORE_DIR . '/includes/helpers/class-format.php';
require_once PPN_CORE_DIR . '/includes/helpers/class-og-card.php';
require_once PPN_CORE_DIR . '/includes/helpers/class-tri-state.php';
require_once PPN_CORE_DIR . '/includes/class-pool-schema.php';
require_once PPN_CORE_DIR . '/includes/class-venue.php';
require_once PPN_CORE_DIR . '/includes/class-provenance.php';
require_once PPN_CORE_DIR . '/includes/class-verification.php';
require_once PPN_CORE_DIR . '/includes/class-pool-data.php';
require_once PPN_CORE_DIR . '/includes/class-locations.php';
require_once PPN_CORE_DIR . '/includes/class-play.php';
require_once PPN_CORE_DIR . '/includes/class-events.php';
require_once PPN_CORE_DIR . '/includes/class-instructors.php';
require_once PPN_CORE_DIR . '/includes/class-my-pool.php';
require_once PPN_CORE_DIR . '/includes/class-promotions.php';
require_once PPN_CORE_DIR . '/includes/class-markup.php';
require_once PPN_CORE_DIR . '/includes/class-display.php';
require_once PPN_CORE_DIR . '/includes/class-open-status.php';
require_once PPN_CORE_DIR . '/includes/class-listing-page.php';
require_once PPN_CORE_DIR . '/includes/class-social.php';
require_once PPN_CORE_DIR . '/includes/class-schema-org.php';
require_once PPN_CORE_DIR . '/includes/class-seo.php';
require_once PPN_CORE_DIR . '/includes/class-seo-meta.php';
require_once PPN_CORE_DIR . '/includes/class-stats.php';
require_once PPN_CORE_DIR . '/includes/class-geocoder.php';
require_once PPN_CORE_DIR . '/includes/class-forms.php';
require_once PPN_CORE_DIR . '/includes/class-moderation.php';
require_once PPN_CORE_DIR . '/includes/class-claims.php';
require_once PPN_CORE_DIR . '/includes/class-importer.php';
require_once PPN_CORE_DIR . '/includes/class-listing-config.php';
require_once PPN_CORE_DIR . '/includes/class-pages.php';
require_once PPN_CORE_DIR . '/includes/class-performance.php';
require_once PPN_CORE_DIR . '/includes/class-install.php';

PlayPoolNation\Core\Install::boot();
PlayPoolNation\Core\Pool_Data::boot();
PlayPoolNation\Core\Locations::boot();
PlayPoolNation\Core\Verification::boot();
PlayPoolNation\Core\Play::boot();
PlayPoolNation\Core\Events::boot();
PlayPoolNation\Core\Instructors::boot();
PlayPoolNation\Core\My_Pool::boot();
PlayPoolNation\Core\Promotions::boot();
PlayPoolNation\Core\Markup::boot();
PlayPoolNation\Core\Display::boot();
PlayPoolNation\Core\Open_Status::boot();
PlayPoolNation\Core\Listing_Page::boot();
PlayPoolNation\Core\Social::boot();
PlayPoolNation\Core\Schema_Org::boot();
PlayPoolNation\Core\Seo::boot();
PlayPoolNation\Core\Seo_Meta::boot();
PlayPoolNation\Core\Stats::boot();
PlayPoolNation\Core\Forms::boot();
PlayPoolNation\Core\Moderation::boot();
PlayPoolNation\Core\Claims::boot();
PlayPoolNation\Core\Importer::boot();
PlayPoolNation\Core\Performance::boot();
