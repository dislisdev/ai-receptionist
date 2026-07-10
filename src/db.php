<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Resolves DB_PATH to an absolute filesystem path.
 *
 * Absolute paths (Railway: /data/app.db) are used as-is. Relative paths are
 * anchored to the project root, never to the process's working directory --
 * that differs between `php -S` (which chdirs into the docroot) and the CLI.
 */
function dbPath(): string {
    $path = env('DB_PATH', './data/app.db');
    if (str_starts_with($path, '/')) return $path;

    return dirname(__DIR__) . '/' . preg_replace('#^\./#', '', $path);
}

/** Opens the SQLite file, runs migrations, seeds the service catalogue. Cached per request. */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $path = dbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    seedServices($pdo);

    return $pdo;
}

/** The clinic's service catalogue. Shared by every visitor, inserted once. */
function seedServices(PDO $pdo): void {
    if ((int)$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn() > 0) return;

    $stmt = $pdo->prepare('INSERT INTO services (name, duration_min, price_eur) VALUES (?, ?, ?)');
    foreach ([
        ['Αρχική αξιολόγηση', 30, 30.0],
        ['Φυσικοθεραπεία',    45, 40.0],
        ['Θεραπευτικό μασάζ', 60, 50.0],
    ] as $service) {
        $stmt->execute($service);
    }
}

/** First time we see this browser, give it a demo week with a few appointments already booked. */
function ensureSession(PDO $pdo, string $sid): void {
    $stmt = $pdo->prepare('SELECT 1 FROM sessions WHERE session_id = ?');
    $stmt->execute([$sid]);
    if ($stmt->fetchColumn()) return;

    $pdo->prepare('INSERT INTO sessions (session_id) VALUES (?)')->execute([$sid]);
    seedAppointments($pdo, $sid);
}

function seedAppointments(PDO $pdo, string $sid): void {
    $monday = demoWeekStart();

    $stmt = $pdo->prepare(
        'INSERT INTO appointments (session_id, service_id, date, time, customer_name, customer_phone)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ([
        [0, '10:00', 2, 'Γιώργος Παπαδόπουλος', '6941234567'],
        [2, '17:00', 3, 'Ελένη Νικολάου',       '6947654321'],
        [3, '12:00', 1, 'Δημήτρης Αντωνίου',    '6949876543'],
    ] as [$offset, $time, $serviceId, $name, $phone]) {
        $day = $monday->modify("+$offset day")->format('Y-m-d');
        $stmt->execute([$sid, $serviceId, $day, $time, $name, $phone]);
    }
}

/** Wipes this visitor's demo and rebuilds it. Touches nobody else's data. */
function resetSession(PDO $pdo, string $sid): void {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM appointments  WHERE session_id = ?')->execute([$sid]);
    $pdo->prepare('DELETE FROM conversations WHERE session_id = ?')->execute([$sid]);
    $pdo->prepare('DELETE FROM sessions      WHERE session_id = ?')->execute([$sid]);
    $pdo->commit();

    ensureSession($pdo, $sid);
}
