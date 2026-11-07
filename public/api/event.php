<?php
/**
 * POST /api/event: one click on Call, Directions, Website, Claim or Suggest an edit,
 * or one venue page view. This is the data owners get shown ("we sent you X calls").
 * Stored: venue, event, where on the site, page path, city, device, traffic source
 * bucket and the hour. Never stored: IP address, cookies, user agent, user ID or
 * the full referrer. Bots are dropped. Always answers 204 so a click never waits.
 */

declare(strict_types=1);
require __DIR__ . '/lib.inc.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	ppn_json(['error' => 'POST only'], 405);
}
if (ppn_is_bot() || !ppn_same_origin()) {
	ppn_no_content();
}
$b = ppn_body();
$events = ['call', 'directions', 'website', 'claim', 'suggest_edit', 'view'];
if (!$b || !in_array($b['e'] ?? '', $events, true) || !preg_match('/^[a-z0-9-]{2,140}$/', (string) ($b['v'] ?? ''))) {
	ppn_no_content();
}
$where = ['venue_page', 'city_page', 'hub_page', 'home_tonight', 'search', 'state_page', ''];
$sources = ['google', 'bing', 'facebook', 'reddit', 'ai', 'direct', 'other'];
$w = (string) ($b['w'] ?? '');
$c = (string) ($b['c'] ?? '');
$d = (string) ($b['d'] ?? '');
$s = (string) ($b['s'] ?? '');

ppn_insert('click_events', [
	'venue_slug' => $b['v'],
	'event'      => $b['e'],
	'placement'  => in_array($w, $where, true) ? $w : 'other',
	'path'       => mb_substr(explode('?', (string) ($b['p'] ?? ''))[0], 0, 200),
	'city_key'   => preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#', $c) ? $c : null,
	'device'     => in_array($d, ['mobile', 'desktop'], true) ? $d : null,
	'source'     => in_array($s, $sources, true) ? $s : 'other',
	'hour_utc'   => gmdate('Y-m-d\TH:00:00\Z'),
]);
ppn_no_content();
