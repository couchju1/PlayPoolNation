<?php
/**
 * GET /api/feed: recent live Wall posts, newest first.
 *   ?v=<venue slug>   one venue        ?vs=<slug,slug,…>  several (up to 100, the player page)
 *   ?c=<state/city>   one city         none of these: everywhere
 * Last 30 days, at most 40. Never cached, so a new post shows up on the next load.
 */

declare(strict_types=1);
require __DIR__ . '/lib.inc.php';

$q = ['select=id,venue_slug,city_key,kind,name,body,cheers,created_at,photo_path,photo_status', 'status=eq.live', 'order=created_at.desc', 'limit=40',
	'created_at=gte.' . rawurlencode(gmdate('Y-m-d\TH:i:s\Z', time() - 30 * 86400))];
$v = (string) ($_GET['v'] ?? '');
$c = (string) ($_GET['c'] ?? '');
if ($v !== '') {
	if (!preg_match('/^[a-z0-9-]{2,140}$/', $v)) {
		ppn_json(['error' => 'bad venue'], 400);
	}
	$q[] = 'venue_slug=eq.' . $v;
} elseif (isset($_GET['vs'])) {
	$vs = array_values(array_filter(explode(',', (string) $_GET['vs']), fn($s) => preg_match('/^[a-z0-9-]{2,140}$/', $s)));
	if (!$vs || count($vs) > 100) {
		ppn_json(['posts' => []]);
	}
	$q[] = 'venue_slug=in.(' . implode(',', $vs) . ')';
} elseif ($c !== '') {
	if (!preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#', $c)) {
		ppn_json(['error' => 'bad city'], 400);
	}
	$q[] = 'city_key=eq.' . rawurlencode($c);
}
[$status, $rows] = ppn_rest('GET', 'posts?' . implode('&', $q));
if ($status !== 200 || !is_array($rows)) {
	ppn_json(['posts' => [], 'error' => 'feed unavailable'], 503);
}
http_response_code(200);
header('Content-Type: application/json');
// Never cached: the CDN or a browser holding an old copy would hide new posts.
header('Cache-Control: no-store');
// A photo shows only once an admin has approved it.
$rows = array_map(function ($r) {
	$r['photo'] = ($r['photo_status'] ?? null) === 'approved' ? $r['photo_path'] : null;
	unset($r['photo_path'], $r['photo_status']);
	return $r;
}, $rows);
echo json_encode(['posts' => $rows], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
