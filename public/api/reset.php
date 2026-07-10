<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/calendar.php';

apiBoot();
requireMethod('POST');

$sid = sessionId();
$pdo = db();
resetSession($pdo, $sid);

jsonOut(weekPayload($pdo, $sid));
