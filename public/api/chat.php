<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/ratelimit.php';
require_once __DIR__ . '/../../src/agent.php';

apiBoot();
requireMethod('POST');

// A booking involves several model round-trips; give the loop room to breathe.
set_time_limit(120);

$sid = sessionId();
$pdo = db();
enforceRateLimit($pdo, 'chat');
ensureSession($pdo, $sid);

$input = json_decode((string)file_get_contents('php://input'), true);
$text  = trim((string)($input['message'] ?? ''));

if ($text === '')                 jsonOut(['error' => 'empty_message'], 400);
if (mb_strlen($text) > MAX_INPUT) jsonOut(['error' => 'message_too_long'], 400);
if (userTurnCount($pdo, $sid) >= MAX_TURNS) {
    jsonOut(['error' => 'session_limit'], 429);
}

saveMessage($pdo, $sid, 'user', $text);

try {
    $reply = runAgentTurn($pdo, $sid);
} catch (ClaudeException) {
    // Already logged under [claude] with the exact cause.
    jsonOut(['error' => 'agent_unavailable'], 502);
}

if ($reply === '') $reply = 'Συγγνώμη, δεν σας κατάλαβα. Μπορείτε να το επαναλάβετε;';

saveMessage($pdo, $sid, 'assistant', $reply);

jsonOut(['reply' => $reply]);
