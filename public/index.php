<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Athens');
header('Content-Type: text/html; charset=utf-8');

/**
 * Reads an env var. Locally it comes from .env; on Railway from the dashboard.
 * FrankenPHP does not always populate getenv(), so we check all three sources.
 */
function env(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    return (string)($_ENV[$key] ?? $_SERVER[$key] ?? $default);
}

/** Minimal .env loader. No dependencies, no vendor/ directory. */
function loadEnv(string $path): void {
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        $_ENV[$k] = $v;
        putenv("$k=$v");
    }
}
loadEnv(__DIR__ . '/../.env');

/** Can this container actually reach the Anthropic API? */
function networkCheck(): string {
    if (!extension_loaded('curl')) return 'curl extension missing';
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    // Any HTTP status means the TCP+TLS handshake worked. 401/404/405 are all fine here.
    return $code > 0 ? "reachable (HTTP $code)" : "BLOCKED - $err";
}

$checks = [
    'PHP version'         => PHP_VERSION,
    'pdo_sqlite'          => extension_loaded('pdo_sqlite') ? 'ok' : 'MISSING',
    'curl'                => extension_loaded('curl')       ? 'ok' : 'MISSING',
    'mbstring'            => extension_loaded('mbstring')   ? 'ok' : 'MISSING',
    'ANTHROPIC_API_KEY'   => env('ANTHROPIC_API_KEY') !== '' ? 'set' : 'not set',
    'ANTHROPIC_MODEL'     => env('ANTHROPIC_MODEL', '(unset)'),
    'Server time (Athens)'=> date('Y-m-d H:i:s') . ' — ' . date('l'),
    'api.anthropic.com'   => networkCheck(),
];
?>
<!doctype html>
<html lang="el">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Receptionist — health check</title>
  <style>
    body { font: 15px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace;
           background: #0f0f0f; color: #e8e8e8; padding: 2rem; }
    h1 { font-size: 1.1rem; font-weight: 600; margin: 0 0 1.5rem; }
    table { border-collapse: collapse; }
    td { padding: .35rem 1.5rem .35rem 0; border-bottom: 1px solid #262626; }
    td:first-child { color: #888; }
    .ok { color: #4ade80; } .bad { color: #f87171; }
  </style>
</head>
<body>
  <h1>AI Receptionist — step 0 health check</h1>
  <table>
    <?php foreach ($checks as $label => $value): ?>
      <?php $bad = str_contains($value, 'MISSING') || str_contains($value, 'BLOCKED'); ?>
      <tr>
        <td><?= htmlspecialchars($label) ?></td>
        <td class="<?= $bad ? 'bad' : 'ok' ?>"><?= htmlspecialchars($value) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
