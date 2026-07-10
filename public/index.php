<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

$sid = sessionId();
header('Content-Type: text/html; charset=utf-8');

/** Can this container reach the Anthropic API? */
function networkCheck(): string {
    if (!extension_loaded('curl')) return 'FAIL curl extension missing';
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
    return $code > 0 ? "reachable (HTTP $code)" : "BLOCKED - $err";
}

$checks = [
    'PHP version'       => PHP_VERSION,
    'pdo_sqlite'        => extension_loaded('pdo_sqlite') ? 'ok' : 'MISSING',
    'curl'              => extension_loaded('curl')       ? 'ok' : 'MISSING',
    'mbstring'          => extension_loaded('mbstring')   ? 'ok' : 'MISSING',
    'ANTHROPIC_API_KEY' => env('ANTHROPIC_API_KEY') !== '' ? 'set' : 'not set',
    'ANTHROPIC_MODEL'   => env('ANTHROPIC_MODEL', '(unset)'),
    'DB_PATH'           => env('DB_PATH', '(unset)'),
    'DB file (resolved)' => dbPath(),
];

try {
    $pdo = db();
    ensureSession($pdo, $sid);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM appointments WHERE session_id = ? AND status = 'confirmed'"
    );
    $stmt->execute([$sid]);

    $checks['Database']            = 'connected';
    $checks['Services seeded']     = (string)$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
    $checks['Your session']        = substr($sid, 0, 8) . '...';
    $checks['Your appointments']   = (string)$stmt->fetchColumn();
} catch (Throwable $e) {
    $checks['Database'] = 'FAIL ' . $e->getMessage();
}

$checks['Server time (Athens)'] = date('Y-m-d H:i:s') . ' - ' . date('l');
$checks['api.anthropic.com']    = networkCheck();
?>
<!doctype html>
<html lang="el">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Receptionist - health check</title>
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
  <h1>AI Receptionist - step 1 health check</h1>
  <table>
    <?php foreach ($checks as $label => $value): ?>
      <?php $bad = str_contains($value, 'MISSING')
                || str_contains($value, 'BLOCKED')
                || str_contains($value, 'FAIL'); ?>
      <tr>
        <td><?= htmlspecialchars($label) ?></td>
        <td class="<?= $bad ? 'bad' : 'ok' ?>"><?= htmlspecialchars($value) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
