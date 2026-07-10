<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Sent on every HTML response. The CSP is the meaningful one: the agent writes
 * customer names into the calendar, so even if an escaping bug ever let markup
 * through, the browser refuses to execute it.
 */
function securityHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self'; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src https://fonts.gstatic.com; " .
        "img-src 'self' data:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
    );
}

/**
 * An API endpoint must emit JSON and nothing else. A PHP warning printed before
 * the opening brace breaks JSON.parse() on the client, and the bug then looks
 * like it lives in the frontend. So: warnings become exceptions, exceptions
 * become a 500 with a generic body, and the details go to the log.
 */
function apiBoot(): void {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    set_error_handler(
        fn(int $no, string $msg, string $file, int $line)
            => throw new ErrorException($msg, 0, $no, $file, $line)
    );

    set_exception_handler(function (Throwable $e): void {
        error_log(sprintf('[api] %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
        jsonOut(['error' => 'server_error'], 500);
    });
}

function jsonOut(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireMethod(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        jsonOut(['error' => 'method_not_allowed'], 405);
    }
}
