<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once '/usr/local/emhttp/plugins/dynamix/include/Helpers.php';

const DASH_PLUGIN_NAME = 'hakomonitor';

/** @return array<string, string> */
function dashLoadConfig(): array
{
    $defaults = [
        'API_HOST'      => 'http://127.0.0.1:8080',
        'API_KEY'       => '',
        'POLL_INTERVAL' => '5',
        'DASH'          => 'enable',
    ];
    $saved  = parse_plugin_cfg(DASH_PLUGIN_NAME);
    $saved  = is_array($saved) ? $saved : [];
    $result = [];
    foreach ($defaults as $k => $default) {
        $v          = $saved[$k] ?? $default;
        $result[$k] = is_string($v) ? $v : $default;
    }
    return $result;
}

/**
 * @return array{ok: bool, status: int, body: mixed}
 */
function dashApiGet(string $host, string $apiKey, string $path): array
{
    $url = rtrim($host, '/') . $path;
    $ch  = curl_init($url);

    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => null];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: ' . $apiKey,
            'Accept: application/json',
        ],
    ]);

    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err !== '') {
        return ['ok' => false, 'status' => $status, 'body' => null];
    }

    $decoded = json_decode((string)$raw, true);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $decoded];
}

$cfg    = dashLoadConfig();
$host   = $cfg['API_HOST'];
$apiKey = $cfg['API_KEY'];

if ($apiKey === '') {
    echo json_encode(['success' => false, 'message' => 'API key not configured.']);
    exit;
}

$svcResult = dashApiGet($host, $apiKey, '/api/v1/status');

if ( ! $svcResult['ok']) {
    echo json_encode(['success' => false, 'message' => 'Could not reach HakoFoundry.']);
    exit;
}

$fansResult = dashApiGet($host, $apiKey, '/api/v1/fans/status');
$pbResult   = dashApiGet($host, $apiKey, '/api/v1/powerboards');

$fans        = $fansResult['ok'] && is_array($fansResult['body']) ? $fansResult['body'] : null;
$powerboards = $pbResult['ok']   && is_array($pbResult['body']) && isset($pbResult['body']['powerboards'])
    ? $pbResult['body']['powerboards']
    : null;

echo json_encode([
    'success'     => true,
    'service'     => $svcResult['body'],
    'fans'        => $fans,
    'powerboards' => $powerboards,
]);
