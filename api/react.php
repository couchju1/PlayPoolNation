<?php
/** POST /api/react {id, a: 'cheer' | 'report'}: cheer a Wall post, or report it (3 reports hide it). */

declare(strict_types=1);
require __DIR__ . '/lib.inc.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	ppn_json(['error' => 'POST only'], 405);
}
if (ppn_is_bot() || !ppn_same_origin()) {
	ppn_no_content();
}
$b = ppn_body();
$id = (int) ($b['id'] ?? 0);
$a = (string) ($b['a'] ?? '');
if ($id < 1 || !in_array($a, ['cheer', 'report'], true) || !ppn_rate_ok('react', 60, 3600)) {
	ppn_no_content();
}
ppn_rest('POST', 'rpc/post_react', ['p_id' => $id, 'p_action' => $a]);
ppn_no_content();
