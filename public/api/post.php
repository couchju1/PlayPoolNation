<?php
/**
 * POST /api/post: a check-in or update on a venue's Wall. No accounts: a display name,
 * a kind, and up to 280 characters. Refused: links, a short list of slurs, unknown venues,
 * more than 3 posts per visitor per 10 minutes or 15 per day. The hidden field and a
 * minimum time on the page catch simple bots. Answers with the saved post.
 *
 * A post can carry one photo (multipart field "photo", JPEG/PNG/WebP up to 6 MB, at most 5 per
 * visitor per day). It's re-encoded here, which drops location and camera data, stored in the
 * private bucket wall-pending, and stays hidden until an admin approves it on /admin/.
 * The owner gets an email when one is waiting (NOTIFY_TO, else info@playpoolnation.com).
 */

declare(strict_types=1);
require __DIR__ . '/lib.inc.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	ppn_json(['error' => 'POST only'], 405);
}
if (ppn_is_bot() || !ppn_same_origin()) {
	ppn_json(['error' => 'bad origin'], 403);
}
$b = str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data') ? $_POST : ppn_body();
if (!$b) {
	ppn_json(['error' => 'bad request'], 400);
}
// Bots fill the hidden field or post instantly. Pretend it worked.
if (ppn_clean($b['hp'] ?? '', 10) !== '' || (int) ($b['elapsed_ms'] ?? 0) < 1500) {
	ppn_json(['ok' => true, 'post' => null]);
}
$venue = (string) ($b['v'] ?? '');
$venues = ppn_venues();
if (!preg_match('/^[a-z0-9-]{2,140}$/', $venue) || !array_key_exists($venue, $venues)) {
	ppn_json(['error' => 'unknown venue'], 400);
}
$kinds = ['checkin', 'tables_open', 'wait', 'league', 'tournament', 'note'];
$kind = (string) ($b['k'] ?? '');
if (!in_array($kind, $kinds, true)) {
	ppn_json(['error' => 'pick what you are posting'], 400);
}
$name = ppn_clean($b['n'] ?? '', 30);
$body = ppn_clean($b['b'] ?? '', 280);
if ($name === '') {
	ppn_json(['error' => 'add a name (a nickname is fine)'], 400);
}
if ($kind === 'note' && $body === '') {
	ppn_json(['error' => 'say something'], 400);
}
$text = $name . ' ' . $body;
if (preg_match('~https?://|www\.|\b[a-z0-9-]+\.(com|net|org|io|co|ly|me|gg|xyz|info|biz|us)\b~i', $text)) {
	ppn_json(['error' => 'links are not allowed on the Wall'], 400);
}
// A short blocklist for the worst of it; reports catch the rest.
if (preg_match('~\b(n[i1]gg(a|er)s?|f[a@]gg?(ot)?s?|k[i1]kes?|sp[i1]cs?|ch[i1]nks?|tr[a@]nn(y|ies)|retards?)\b~i', $text)) {
	ppn_json(['error' => 'that post was not saved'], 400);
}
if (!ppn_rate_ok('post10m', 3, 600) || !ppn_rate_ok('postday', 15, 86400)) {
	ppn_json(['error' => 'slow down a little, then post again'], 429);
}
$photo = null;
$file = $_FILES['photo'] ?? null;
if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
	if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 6 * 1024 * 1024) {
		ppn_json(['error' => 'that photo is too big (6 MB at most)'], 400);
	}
	$info = @getimagesize($file['tmp_name']);
	if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
		ppn_json(['error' => 'photos must be JPEG, PNG or WebP'], 400);
	}
	if (!ppn_rate_ok('photoday', 5, 86400)) {
		ppn_json(['error' => 'that\'s enough photos for today, post again tomorrow'], 429);
	}
	$jpeg = ppn_clean_jpeg($file['tmp_name']);
	if ($jpeg === null) {
		ppn_json(['error' => 'that photo could not be read'], 400);
	}
	$photo = $venue . '/' . gmdate('Ymd') . '-' . bin2hex(random_bytes(8)) . '.jpg';
	if (!ppn_storage_put('wall-pending', $photo, $jpeg)) {
		ppn_json(['error' => 'the photo could not be saved, try again'], 503);
	}
}
[$status, $rows] = ppn_rest('POST', 'posts?select=id,venue_slug,city_key,kind,name,body,cheers,created_at', [
	'venue_slug' => $venue,
	'city_key'   => $venues[$venue],
	'kind'       => $kind,
	'name'       => $name,
	'body'       => $body,
	'photo_path'   => $photo,
	'photo_status' => $photo ? 'pending' : null,
], ['Prefer: return=representation']);
if ($status < 200 || $status >= 300 || !is_array($rows) || !isset($rows[0])) {
	ppn_json(['error' => 'not saved, try again'], 503);
}
if ($photo) {
	$c = ppn_config();
	$to = (string) ($c['NOTIFY_TO'] ?? 'info@playpoolnation.com');
	$place = $GLOBALS['ppn_venue_names'][$venue] ?? $venue;
	$text = "$name posted a photo on the Wall at $place. It's hidden until you approve it.\n\nReview it: https://playpoolnation.com/admin/\n";
	if (!getenv('PPN_MAIL_LOG')) {
		@mail($to, 'Photo waiting for approval: ' . ($GLOBALS['ppn_venue_names'][$venue] ?? $venue), $text, "From: PlayPoolNation <info@playpoolnation.com>\r\nContent-Type: text/plain; charset=UTF-8", '-finfo@playpoolnation.com');
	} else {
		file_put_contents(getenv('PPN_MAIL_LOG'), "To: $to\nSubject: Photo waiting for approval\n\n$text---\n", FILE_APPEND);
	}
}
ppn_json(['ok' => true, 'post' => $rows[0] + ['photo_pending' => (bool) $photo]]);
