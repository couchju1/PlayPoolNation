<?php
/**
 * City page template (City_Page): the theme's header and footer around the page.
 *
 * @package PlayPoolNation\Core
 */

defined( 'ABSPATH' ) || exit;

get_header();
echo PlayPoolNation\Core\City_Page::html(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
get_footer();
