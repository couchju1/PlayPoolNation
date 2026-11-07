<?php
/**
 * POST /api/submit: Add a venue, claim a venue, or suggest an edit. Goes into the
 * submissions table for a person to review; nothing on the site changes from here.
 * Spam checks: honeypot field, a minimum time on the form, size limits, same origin,
 * and at most 10 submissions per visitor per hour (counted under a salted hash of
 * the address in the system temp folder, never stored with the submission).
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
$kind = (string) ($b['kind'] ?? '');
if (!$b || !in_array($kind, ['add', 'claim', 'edit'], true)) {
	ppn_json(['error' => 'bad request'], 400);
}
// Bots fill the hidden field or submit instantly. Pretend it worked.
if (ppn_clean($b['website_url'] ?? '', 10) !== '' || (int) ($b['elapsed_ms'] ?? 0) < 3000) {
	ppn_json(['ok' => true]);
}

// Rate limit: a small counter file per visitor per hour.
$secret = (string) (ppn_config()['SUPABASE_SERVICE_KEY'] ?? 'ppn');
$bucket = sys_get_temp_dir() . '/ppn-rl-' . hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . gmdate('YmdH'), $secret);
$count = is_file($bucket) ? (int) file_get_contents($bucket) : 0;
if ($count >= 10) {
	ppn_json(['error' => 'too many submissions, try again later'], 429);
}
@file_put_contents($bucket, (string) ($count + 1), LOCK_EX);

$email = ppn_clean($b['email'] ?? '', 160);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
	ppn_json(['error' => 'check the email address'], 400);
}
if ($kind === 'claim' && $email === '') {
	ppn_json(['error' => 'an email is needed to claim'], 400);
}
if ($kind === 'add' && (ppn_clean($b['name'] ?? '', 120) === '' || ppn_clean($b['city'] ?? '', 80) === '')) {
	ppn_json(['error' => 'name and city are needed'], 400);
}
if ($kind === 'edit' && ppn_clean($b['details'] ?? '', 4000) === '') {
	ppn_json(['error' => 'say what to change'], 400);
}
$venue = ppn_clean($b['venue'] ?? '', 140);
$ok = ppn_insert('submissions', [
	'kind'       => $kind,
	'venue_slug' => preg_match('/^[a-z0-9-]{2,140}$/', $venue) ? $venue : null,
	'payload'    => [
		'name'    => ppn_clean($b['name'] ?? '', 120),
		'street'  => ppn_clean($b['street'] ?? '', 160),
		'city'    => ppn_clean($b['city'] ?? '', 80),
		'contact' => ppn_clean($b['contact'] ?? '', 200),
		'person'  => ppn_clean($b['person'] ?? '', 100),
		'role'    => ppn_clean($b['role'] ?? '', 40),
		'details' => ppn_clean($b['details'] ?? '', 4000),
		'source'  => ppn_clean($b['source'] ?? '', 300),
	],
	'email'      => $email !== '' ? $email : null,
]);
$ok ? ppn_json(['ok' => true]) : ppn_json(['error' => 'not saved'], 503);
