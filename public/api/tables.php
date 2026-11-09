<?php
/**
 * POST /api/tables: a player's answer to "How many tables?" on a venue page.
 * Saved in submissions (kind 'edit', payload.type 'tables') for a person to check;
 * nothing on the site changes from here. `npm run player-reports` turns answers that
 * agree into suggested facts for `npm run review`.
 * Spam checks: same origin, honeypot, minimum time on the card, known venue, and at most
 * 6 answers per visitor per hour (counted under a salted hash of the address, never stored).
 */

declare(strict_types=1);
require __DIR__ . '/lib.inc.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	ppn_json(['error' => 'POST only'], 405);
}
if (!ppn_same_origin()) {
	ppn_json(['error' => 'bad origin'], 403);
}
$b = ppn_body();
if (!$b) {
	ppn_json(['error' => 'bad request'], 400);
}
// Bots fill the hidden field, answer instantly or say they are bots. Pretend it worked.
if (ppn_clean($b['website_url'] ?? '', 10) !== '' || (int) ($b['elapsed_ms'] ?? 0) < 2500 || ppn_is_bot()) {
	ppn_json(['ok' => true]);
}
$venue = ppn_clean($b['venue'] ?? '', 140);
if (!preg_match('/^[a-z0-9-]{2,140}$/', $venue) || !array_key_exists($venue, ppn_venues())) {
	ppn_json(['error' => 'unknown venue'], 400);
}

$count = $b['count'] ?? null;
$count = (is_int($count) || (is_string($count) && ctype_digit($count))) ? (int) $count : null;
if ($count !== null && ($count < 1 || $count > 200)) {
	ppn_json(['error' => 'pick a number from 1 to 200'], 400);
}
$sizes = array_values(array_unique(array_intersect(
	array_map('strval', is_array($b['sizes'] ?? null) ? $b['sizes'] : []),
	['7ft', '8ft', '9ft', 'snooker', 'carom']
)));
$brands = [];
foreach (array_slice(is_array($b['brands'] ?? null) ? $b['brands'] : [], 0, 6) as $x) {
	$x = ppn_clean(is_string($x) ? $x : '', 40);
	if ($x !== '' && !in_array(strtolower($x), array_map('strtolower', $brands), true)) {
		$brands[] = $x;
	}
}
if ($count === null && !$sizes && !$brands) {
	ppn_json(['error' => 'tell us a number, a size or a brand'], 400);
}
$when = in_array($b['when'] ?? '', ['tonight', 'recently', 'while'], true) ? $b['when'] : 'recently';
$note = ppn_clean($b['note'] ?? '', 300);
$user = ppn_clean($b['user'] ?? '', 40);

if (!ppn_rate_ok('tables', 6, 3600)) {
	ppn_json(['error' => 'too many answers, try again later'], 429);
}

$labels = ['7ft' => '7-ft', '8ft' => '8-ft', '9ft' => '9-ft', 'snooker' => 'snooker', 'carom' => 'carom'];
$details = trim(implode('. ', array_filter([
	$count !== null ? "$count tables" : 'Count not sure',
	$sizes ? 'Sizes: ' . implode(', ', array_map(fn($s) => $labels[$s], $sizes)) : '',
	$brands ? 'Brands: ' . implode(', ', $brands) : '',
	['tonight' => 'Seen today', 'recently' => 'Seen this month', 'while' => 'Seen a while ago'][$when],
	$note,
])));

$ok = ppn_insert('submissions', [
	'kind'       => 'edit',
	'venue_slug' => $venue,
	'payload'    => [
		'type'    => 'tables',
		'count'   => $count,
		'sizes'   => $sizes,
		'brands'  => $brands,
		'when'    => $when,
		'note'    => $note,
		'user'    => preg_match('/^[0-9a-f-]{36}$/', $user) ? $user : null,
		'details' => $details,
	],
]);
$ok ? ppn_json(['ok' => true]) : ppn_json(['error' => 'not saved'], 503);
