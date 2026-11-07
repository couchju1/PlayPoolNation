<?php
/**
 * Shared by /api/event and /api/submit on Hostinger: sends rows to Supabase.
 *
 * The Supabase settings never live in the repository or in public_html. They are
 * read from environment variables, or else from ppn-secrets.php one level above
 * public_html (domains/playpoolnation.com/ppn-secrets.php), which returns
 *   ['SUPABASE_URL' => 'https://<ref>.supabase.co', 'SUPABASE_SERVICE_KEY' => '...']
 */

declare(strict_types=1);

function ppn_config(): array {
	static $config = null;
	if ($config !== null) {
		return $config;
	}
	$config = [];
	$file = getenv('PPN_SECRETS_FILE') ?: dirname(__DIR__, 2) . '/ppn-secrets.php';
	if (is_readable($file)) {
		$loaded = include $file;
		if (is_array($loaded)) {
			$config = $loaded;
		}
	}
	foreach (['SUPABASE_URL', 'SUPABASE_SERVICE_KEY'] as $key) {
		$env = getenv($key);
		if ($env !== false && $env !== '') {
			$config[$key] = $env;
		}
	}
	return $config;
}

/** Insert one row with the service key. False when Supabase isn't configured or refused it. */
function ppn_insert(string $table, array $row): bool {
	$c = ppn_config();
	if (empty($c['SUPABASE_URL']) || empty($c['SUPABASE_SERVICE_KEY'])) {
		return false;
	}
	$ch = curl_init(rtrim($c['SUPABASE_URL'], '/') . '/rest/v1/' . $table);
	curl_setopt_array($ch, [
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => json_encode($row, JSON_UNESCAPED_SLASHES),
		CURLOPT_HTTPHEADER     => [
			'apikey: ' . $c['SUPABASE_SERVICE_KEY'],
			'Authorization: Bearer ' . $c['SUPABASE_SERVICE_KEY'],
			'Content-Type: application/json',
			'Prefer: return=minimal',
		],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 3,
		CURLOPT_TIMEOUT        => 5,
	]);
	curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);
	return $status >= 200 && $status < 300;
}

function ppn_json(array $body, int $status = 200): never {
	http_response_code($status);
	header('Content-Type: application/json');
	header('Cache-Control: no-store');
	echo json_encode($body);
	exit;
}

function ppn_no_content(): never {
	http_response_code(204);
	header('Cache-Control: no-store');
	exit;
}

function ppn_is_bot(): bool {
	return (bool) preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|pagespeed|facebookexternalhit|curl|wget|python|axios|httpclient/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

/** No Origin header (same-origin beacons may omit it) or our own host. */
function ppn_same_origin(): bool {
	$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
	if ($origin === '') {
		return true;
	}
	$host = strtolower((string) parse_url($origin, PHP_URL_HOST));
	$ours = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
	return $host !== '' && ($host === $ours || $host === 'www.' . $ours || 'www.' . $host === $ours);
}

/** The JSON body, or null. Capped at 16 KB. */
function ppn_body(): ?array {
	$raw = file_get_contents('php://input', false, null, 0, 16384);
	$data = json_decode((string) $raw, true);
	return is_array($data) ? $data : null;
}

function ppn_clean($value, int $max): string {
	$s = is_scalar($value) ? (string) $value : '';
	$s = preg_replace('/[\x00-\x1f\x7f]/u', ' ', $s) ?? '';
	return mb_substr(trim($s), 0, $max);
}
