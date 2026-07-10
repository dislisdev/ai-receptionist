<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';

/**
 * Two windows guard the API key's balance.
 *
 * The per-IP window stops the curious. It is not airtight: behind a proxy the
 * client controls X-Forwarded-For, so a determined caller can rotate the value.
 *
 * The global window is the one that actually holds. No header can forge past a
 * count of every request the service has served today.
 *
 * Neither is the last line. That is prepaid credits with auto-reload disabled:
 * the worst case is a spent balance, never a charged card.
 */
const RL_PER_IP_PER_HOUR = 30;
const RL_GLOBAL_PER_DAY  = 200;

function clientIp(): string {
    $fwd = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($fwd !== '') return trim(explode(',', $fwd)[0]);
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/** Never store raw addresses. A salted hash is enough to count, useless to identify. */
function ipHash(): string {
    return hash('sha256', env('APP_SECRET', 'ai-receptionist-demo') . '|' . clientIp());
}

/** Called on every limited request; keeps the table from growing without bound. */
function pruneCalls(PDO $pdo): void {
    $pdo->exec("DELETE FROM api_calls WHERE created_at < datetime('now', '-2 days')");
}

/**
 * Records the call and enforces both windows. Emits 429 and stops the request
 * when either is exceeded -- the counters do not care which endpoint asked.
 */
function enforceRateLimit(PDO $pdo, string $endpoint): void {
    pruneCalls($pdo);

    $hash = ipHash();

    $perIp = $pdo->prepare(
        "SELECT COUNT(*) FROM api_calls WHERE ip_hash = ? AND created_at > datetime('now', '-1 hour')"
    );
    $perIp->execute([$hash]);

    $global = $pdo->query(
        "SELECT COUNT(*) FROM api_calls WHERE created_at > datetime('now', '-1 day')"
    );

    $ipCount     = (int)$perIp->fetchColumn();
    $globalCount = (int)$global->fetchColumn();

    if ($ipCount >= RL_PER_IP_PER_HOUR) {
        error_log("[ratelimit] per-ip window exhausted on $endpoint");
        jsonOut(['error' => 'rate_limited'], 429);
    }
    if ($globalCount >= RL_GLOBAL_PER_DAY) {
        error_log("[ratelimit] global daily budget exhausted on $endpoint");
        jsonOut(['error' => 'demo_budget_spent'], 429);
    }

    $pdo->prepare('INSERT INTO api_calls (ip_hash, endpoint) VALUES (?, ?)')
        ->execute([$hash, $endpoint]);
}

/** For the health page. Cheap enough to run on a diagnostic route. */
function rateLimitStatus(PDO $pdo): array {
    $day = $pdo->query("SELECT COUNT(*) FROM api_calls WHERE created_at > datetime('now', '-1 day')");
    return ['today' => (int)$day->fetchColumn(), 'budget' => RL_GLOBAL_PER_DAY];
}
