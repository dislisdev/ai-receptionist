<?php
declare(strict_types=1);

/** Clinic opening hours. Slots are one hour each: 09:00 .. 19:00 (the last ends at 20:00). */
const OPEN_HOUR  = 9;
const CLOSE_HOUR = 20;

/** Monday .. Friday, in the order the calendar renders them. */
const GREEK_DAYS = ['Δευτέρα', 'Τρίτη', 'Τετάρτη', 'Πέμπτη', 'Παρασκευή'];

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
        $_ENV[trim($k)] = trim($v, " \t\"'");
        putenv(trim($k) . '=' . trim($v, " \t\"'"));
    }
}

loadEnv(dirname(__DIR__) . '/.env');
date_default_timezone_set(env('APP_TIMEZONE', 'Europe/Athens'));

/**
 * The demo always shows the upcoming Monday-Friday, so every slot in the grid is
 * bookable whatever day the link is opened. If today is Monday we still jump to
 * next Monday: a half-elapsed week makes the demo look broken.
 */
function demoWeekStart(): DateTimeImmutable {
    $today     = new DateTimeImmutable('today');
    $daysAhead = (8 - (int)$today->format('N')) % 7;
    if ($daysAhead === 0) $daysAhead = 7;

    return $today->modify("+$daysAhead days");
}

/** ['09:00', '10:00', ... '19:00'] */
function slotHours(): array {
    $hours = [];
    for ($h = OPEN_HOUR; $h < CLOSE_HOUR; $h++) {
        $hours[] = sprintf('%02d:00', $h);
    }
    return $hours;
}

/**
 * Every visitor gets their own demo clinic. A random cookie is the only thing
 * tying a browser to its appointments. Must run before any output.
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
