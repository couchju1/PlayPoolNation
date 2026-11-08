<?php
/**
 * POST /api/notify-signup {id}: emails the site owner when a new account is confirmed.
 * Called by a database trigger in Supabase (see db/schema.sql, notify_signup). The body is
 * only a user id; nothing in it is trusted. This script asks Supabase (with the service key)
 * whether that user exists and confirmed in the last 15 minutes, sends one email per user
 * (remembered in the system temp folder), and always answers 204.
 *
 * Recipient: NOTIFY_TO in ppn-secrets.php, else info@playpoolnation.com.
 */

declare(strict_types=1);
require __DIR__ . '/lib.inc.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	ppn_json(['error' => 'POST only'], 405);
}
$b = ppn_body();
$id = (string) ($b['id'] ?? '');
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id) || !ppn_rate_ok('notify', 120, 3600)) {
	ppn_no_content();
}
$secret = (string) (ppn_config()['SUPABASE_SERVICE_KEY'] ?? 'ppn');
$sent = sys_get_temp_dir() . '/ppn-notified-' . hash_hmac('sha256', $id, $secret);
if (is_file($sent)) {
	ppn_no_content();
}

// Ask Supabase about this user (Auth admin API, service key only).
$c = ppn_config();
if (empty($c['SUPABASE_URL']) || empty($c['SUPABASE_SERVICE_KEY'])) {
	ppn_no_content();
}
$ch = curl_init(rtrim($c['SUPABASE_URL'], '/') . '/auth/v1/admin/users/' . $id);
curl_setopt_array($ch, [
	CURLOPT_HTTPHEADER     => ['apikey: ' . $c['SUPABASE_SERVICE_KEY'], 'Authorization: Bearer ' . $c['SUPABASE_SERVICE_KEY']],
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_CONNECTTIMEOUT => 3,
	CURLOPT_TIMEOUT        => 5,
]);
$user = json_decode((string) curl_exec($ch), true);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$confirmed = strtotime((string) ($user['email_confirmed_at'] ?? '')) ?: 0;
if ($status !== 200 || !is_array($user) || !$confirmed || time() - $confirmed > 900) {
	ppn_no_content();
}

$email = (string) ($user['email'] ?? '');
$name = ppn_clean($user['user_metadata']['display_name'] ?? '', 40);
// Accounts so far (one row each in profiles; fine at this size).
[, $all] = ppn_rest('GET', 'profiles?select=id');
$total = is_array($all) ? count($all) : 0;

$to = (string) ($c['NOTIFY_TO'] ?? 'info@playpoolnation.com');
$when = gmdate('D, M j, Y g:i A', $confirmed) . ' UTC';
$subject = 'New PlayPoolNation sign-up: ' . ($name !== '' ? $name : $email);
$text = "A new player just confirmed their account.\n\n"
	. "Name:   " . ($name !== '' ? $name : '(none)') . "\n"
	. "Email:  $email\n"
	. "Joined: $when\n\n"
	. "Total accounts: $total\n\n"
	. "Reply to this email to write to them.\n"
	. "Their reviews (once they post one): open any venue page on https://playpoolnation.com\n";
$headers = [
	'From: PlayPoolNation <info@playpoolnation.com>',
	'Reply-To: ' . (filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'info@playpoolnation.com'),
	'Content-Type: text/plain; charset=UTF-8',
	'X-PPN: signup-notice',
];

// For local testing, write the email to a file instead of sending it.
$log = getenv('PPN_MAIL_LOG');
$ok = $log ? (bool) file_put_contents($log, "To: $to\nSubject: $subject\n" . implode("\n", $headers) . "\n\n$text\n---\n", FILE_APPEND)
	: mail($to, $subject, $text, implode("\r\n", $headers), '-finfo@playpoolnation.com');
if ($ok) {
	@file_put_contents($sent, (string) time());
}
ppn_no_content();
