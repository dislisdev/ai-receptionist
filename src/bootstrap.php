<?php
declare(strict_types=1);

/** Clinic opening hours. Slots are one hour each: 09:00 .. 19:00 (last one ends 20:00). */
const OPEN_HOUR  = 9;
const CLOSE_HOUR = 20;

/** ISO-8601 weekdays: Monday = 1 ... Friday = 5. */
const WORK_DAYS = [1, 2, 3, 4, 5];

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

loadEnv(dirname(__DIR__) . '/.env');
date_default_timezone_set(env('APP_TIMEZONE', 'Europe/Athens'));

/**
 * Every visitor gets their own demo clinic. A random cookie is the only thing
 * tying a browser to its appointments. No accounts, no login, no cross-talk.
 * Must be called before any output is sent.
 */
function sessionId(): string {
    if (!empty($_COOKIE['sid']) && preg_match('/^[a-f0-9]{32}$/', $_COOKIE['sid'])) {
        return $_COOKIE['sid'];
    }
    $sid = bin2hex(random_bytes(16));
    setcookie('sid', $sid, [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['sid'] = $sid;
    return $sid;
}
