<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * The service catalogue. Order matters: ids are assigned by insertion order and
 * existing databases already hold rows 1-3, so new services only ever append.
 * color_key ties each service to an appt--{key} CSS class in the frontend.
 */
const CATALOGUE = [
    ['Αρχική αξιολόγηση', 30, 30.0, 'assessment'],
    ['Φυσικοθεραπεία',    45, 40.0, 'physio'],
    ['Θεραπευτικό μασάζ', 60, 50.0, 'massage'],
    ['Αποκατάσταση',      45, 45.0, 'rehab'],
];

/**
 * Resolves DB_PATH to an absolute filesystem path. Absolute paths (Railway)
 * pass through; relative paths anchor to the project root, never to the CWD.
 */
function dbPath(): string {
    $path = env('DB_PATH', './data/app.db');
    if (str_starts_with($path, '/')) return $path;
    return dirname(__DIR__) . '/' . preg_replace('#^\./#', '', $path);
}

/** Opens the SQLite file, runs migrations, syncs the catalogue. Cached per request. */
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
    migrateCatalogue($pdo);

    return $pdo;
}

/**
 * Databases created before step 4 lack the color_key column and the fourth
 * service. CREATE TABLE IF NOT EXISTS never alters existing tables, so the
 * upgrade happens here: add the column if missing, then upsert by name.
 */
function migrateCatalogue(PDO $pdo): void {
    $cols = array_column($pdo->query('PRAGMA table_info(services)')->fetchAll(), 'name');
    if (!in_array('color_key', $cols, true)) {
        $pdo->exec("ALTER TABLE services ADD COLUMN color_key TEXT NOT NULL DEFAULT ''");
    }

    $find   = $pdo->prepare('SELECT id FROM services WHERE name = ?');
    $insert = $pdo->prepare('INSERT INTO services (name, duration_min, price_eur, color_key) VALUES (?, ?, ?, ?)');
    $update = $pdo->prepare('UPDATE services SET duration_min = ?, price_eur = ?, color_key = ? WHERE id = ?');

    foreach (CATALOGUE as [$name, $dur, $price, $key]) {
        $find->execute([$name]);
        $id = $find->fetchColumn();
        if ($id === false) {
            $insert->execute([$name, $dur, $price, $key]);
        } else {
            $update->execute([$dur, $price, $key, (int)$id]);
        }
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

/** One appointment per service, spread across the week, so every colour shows. */
function seedAppointments(PDO $pdo, string $sid): void {
    $monday = demoWeekStart();

    $stmt = $pdo->prepare(
        'INSERT INTO appointments (session_id, service_id, date, time, customer_name, customer_phone)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ([
        [0, '10:00', 2, 'Γιώργος Παπαδόπουλος', '6941234567'],
        [1, '12:00', 4, 'Νίκος Δημητρίου',      '6942223344'],
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
