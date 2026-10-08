<?php
/**
 * Nate Hall Insurance contact form handler (Hostinger / any PHP host).
 *
 * Emails each quote request to $TO. The visitor's email is set as Reply-To,
 * so hitting "Reply" in Gmail answers them directly.
 *
 * Responds with JSON when called from js/main.js, or redirects to
 * thanks.html when the browser posts the form without JavaScript.
 */

// ---- Settings -------------------------------------------------------------
$TO   = 'natehallinsurance@gmail.com';
// Must be an address on your own domain, or many mail servers will treat the
// message as spam. It doesn't need to be a real inbox you check.
$FROM = 'no-reply@natehallinsurance.com';
$SITE = 'Nate Hall Insurance website';
// Max submissions per visitor (by IP address) per hour, to slow down spam.
$RATE_LIMIT = 5;
// Time zone used for the "Sent" time in each email (Hartford, SD is Central).
date_default_timezone_set('America/Chicago');
// ---------------------------------------------------------------------------

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function respond($ok, $message, $status = 200)
{
    global $wantsJson;
    http_response_code($status);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
    } elseif ($ok) {
        header('Location: thanks.html', true, 303);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Message not sent</title><body style="font-family:system-ui,sans-serif;max-width:40em;margin:3em auto;padding:0 16px">'
           . '<h1>Sorry, your message wasn\'t sent</h1><p>' . htmlspecialchars($message) . '</p>'
           . '<p><a href="index.html#contact">Go back to the form</a> or call <a href="tel:+16053215367">605-321-5367</a>.</p></body>';
    }
    exit;
}

// Removes line breaks so a value can't add extra email headers.
function one_line($value, $max)
{
    $value = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function field($name, $max = 200)
{
    return isset($_POST[$name]) && is_string($_POST[$name]) ? one_line($_POST[$name], $max) : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(false, 'This address only accepts form submissions.', 405);
}

// Honeypot: real visitors never see or fill this field. Pretend it worked so bots move on.
if (!empty($_POST['bot-field'])) {
    respond(true, 'Thanks!');
}

// Simple per-IP rate limit stored in the server's temp folder.
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$rateFile = rtrim(sys_get_temp_dir(), '/') . '/nhi_form_' . md5($ip);
$now = time();
$recent = [];
if (is_readable($rateFile)) {
    foreach ((array) json_decode((string) @file_get_contents($rateFile), true) as $t) {
        if (is_int($t) && $t > $now - 3600) $recent[] = $t;
    }
}
if (count($recent) >= $RATE_LIMIT) {
    respond(false, 'Too many requests from your connection. Please try again later or give me a call.', 429);
}

$first    = field('first-name', 100);
$last     = field('last-name', 100);
$email    = field('email', 254);
$phone    = field('phone', 40);
$zip      = field('zip', 10);
$meeting  = field('meeting', 40);
$comments = isset($_POST['comments']) && is_string($_POST['comments'])
    ? trim(substr(str_replace("\r", '', $_POST['comments']), 0, 5000)) : '';

$allowed = ['Life insurance', 'Annuities & retirement', 'Medicare', 'Health insurance', 'Cancer & critical illness', 'Long-term care'];
$interests = [];
if (isset($_POST['interests']) && is_array($_POST['interests'])) {
    foreach ($_POST['interests'] as $i) {
        if (is_string($i) && in_array($i, $allowed, true)) $interests[] = $i;
    }
}
if (!in_array($meeting, ['In person', 'Phone', 'Virtual'], true)) $meeting = '';

if ($first === '' || $last === '') {
    respond(false, 'Please enter your first and last name.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', 422);
}
if ($zip !== '' && !preg_match('/^\d{5}$/', $zip)) {
    respond(false, 'Please enter a 5-digit ZIP code.', 422);
}

$name = "$first $last";
$body = "New quote request from the $SITE\n"
      . str_repeat('-', 40) . "\n"
      . "Name:       $name\n"
      . "Email:      $email\n"
      . "Phone:      " . ($phone !== '' ? $phone : '(not given)') . "\n"
      . "ZIP:        " . ($zip !== '' ? $zip : '(not given)') . "\n"
      . "Interested: " . ($interests ? implode(', ', $interests) : '(none checked)') . "\n"
      . "Meet by:    " . ($meeting !== '' ? $meeting : '(no preference)') . "\n"
      . "\nComments:\n" . ($comments !== '' ? $comments : '(none)') . "\n"
      . str_repeat('-', 40) . "\n"
      . "Sent " . date('M j, Y g:i A T') . ". Reply to this email to answer $first directly.\n";

// Keep only normal name characters in the email header (letters, spaces, . ' -).
$replyName = trim(preg_replace('/[^\p{L}\p{M} .\'-]+/u', '', $name));
if ($replyName === '') $replyName = 'Website visitor';
$subject = '=?UTF-8?B?' . base64_encode("New quote request: $name") . '?=';
$headers = [
    'From: ' . $SITE . ' <' . $FROM . '>',
    'Reply-To: ' . (preg_match('/[^\x20-\x7E]/', $replyName)
        ? '=?UTF-8?B?' . base64_encode($replyName) . '?='
        : '"' . $replyName . '"') . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP',
];

$sent = @mail($TO, $subject, $body, implode("\r\n", $headers), '-f' . $FROM);
if (!$sent) {
    respond(false, 'The server could not send your message right now. Please try again or give me a call.', 500);
}

$recent[] = $now;
@file_put_contents($rateFile, json_encode($recent), LOCK_EX);
respond(true, 'Thanks! Your request was sent.');
