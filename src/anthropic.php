<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const ANTHROPIC_URL     = 'https://api.anthropic.com/v1/messages';
const ANTHROPIC_VERSION = '2023-06-01';

/** The API was unreachable, misconfigured, or answered with something other than 200. */
class ClaudeException extends RuntimeException {}

/**
 * One request to the Messages API.
 *
 * Sonnet 5 rejects temperature and top_p, so neither is sent. Adaptive thinking
 * is on by default at high effort; a receptionist does not need it, and `low`
 * keeps replies fast. The model still thinks when a turn genuinely calls for it.
 *
 * Every failure path logs under the same [claude] prefix. One subsystem, one
 * prefix: that is what makes a log filter useful at 2am.
 */
function callClaude(array $messages, string $system, array $tools = []): array {
    $key = env('ANTHROPIC_API_KEY');
    if ($key === '') {
        error_log('[claude] ANTHROPIC_API_KEY is not set');
        throw new ClaudeException('missing api key');
    }

    $body = [
        'model'         => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        'max_tokens'    => 2048,
        'system'        => $system,
        'messages'      => $messages,
        'output_config' => ['effort' => 'low'],
    ];
    if ($tools !== []) $body['tools'] = $tools;

    $ch = curl_init(ANTHROPIC_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: ' . ANTHROPIC_VERSION,
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);

    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);

    if ($raw === false) {
        error_log("[claude] network failure: $err");
        throw new ClaudeException('network failure');
    }
    if ($status !== 200) {
        // The response body names the exact problem. It never reaches the browser.
        error_log("[claude] HTTP $status: $raw");
        throw new ClaudeException("api returned $status");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        error_log('[claude] malformed response body');
        throw new ClaudeException('malformed response');
    }

    return $data;
}

/** Joins the text blocks of a response. Thinking and tool_use blocks are skipped. */
function claudeText(array $response): string {
    $parts = [];
    foreach ($response['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') $parts[] = $block['text'];
    }
    return trim(implode("\n\n", $parts));
}
