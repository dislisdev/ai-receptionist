<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/calendar.php';

apiBoot();
requireMethod('GET');

$sid = sessionId();
$pdo = db();
ensureSession($pdo, $sid);

jsonOut(weekPayload($pdo, $sid));
