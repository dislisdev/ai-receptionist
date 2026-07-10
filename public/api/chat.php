<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/agent.php';

apiBoot();
requireMethod('POST');

$sid = sessionId();
$pdo = db();
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
    $response = callClaude(loadHistory($pdo, $sid), systemPrompt($pdo));
} catch (ClaudeException $e) {
    error_log('[chat] ' . $e->getMessage());
    jsonOut(['error' => 'agent_unavailable'], 502);
}

$reply = claudeText($response);
if ($reply === '') $reply = 'Συγγνώμη, δεν σας κατάλαβα. Μπορείτε να το επαναλάβετε;';

saveMessage($pdo, $sid, 'assistant', $reply);

jsonOut(['reply' => $reply]);
