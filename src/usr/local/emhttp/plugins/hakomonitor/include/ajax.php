<?php

declare(strict_types=1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

function postStr(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? null;
    return is_string($v) ? $v : $default;
}

function jsonResponse(bool $success, string $message): never
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

$csrfPost   = $_POST['csrf_token']          ?? '';
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfCookie = $_COOKIE['csrf_token']        ?? '';

$csrfToken = $csrfHeader !== '' ? $csrfHeader : $csrfPost;

if ($csrfToken === '') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token invalid.']);
    exit;
}

if ($csrfCookie !== '' && $csrfToken !== $csrfCookie) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token invalid.']);
    exit;
}

const PLUGIN_NAME = 'hakomonitor';
const CFG_DIR     = '/boot/config/plugins/hakomonitor';
const CFG_FILE    = CFG_DIR . '/hakomonitor.cfg';

require_once '/usr/local/emhttp/plugins/dynamix/include/Helpers.php';

/** @return array<string, string> */
function loadConfig(): array
{
    $defaults = [
        'API_HOST'      => 'http://127.0.0.1:8080',
        'API_KEY'       => '',
        'POLL_INTERVAL' => '5',
    ];
    $saved  = parse_plugin_cfg(PLUGIN_NAME);
    $saved  = is_array($saved) ? $saved : [];
    $result = [];
    foreach ($defaults as $k => $default) {
        $v          = $saved[$k] ?? $default;
        $result[$k] = is_string($v) ? $v : $default;
    }
    return $result;
}

/** @param array<string, string> $data */
function saveConfig(array $data): void
{
    if ( ! is_dir(CFG_DIR)) {
        mkdir(CFG_DIR, 0755, true);
    }
    $lines = [];
    foreach ($data as $key => $val) {
        $escaped = str_replace('"', '\\"', (string)$val);
        $lines[] = "{$key}=\"{$escaped}\"";
    }
    file_put_contents(CFG_FILE, implode("\n", $lines) . "\n");
}

/**
 * Make a GET request to the Hako Foundry API.
 *
 * @return array{ok: bool, status: int, body: mixed}
 */
function apiGet(string $host, string $apiKey, string $path): array
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

$action = postStr('action');

switch ($action) {
    case 'fetch_status':
        $cfg    = loadConfig();
        $host   = $cfg['API_HOST'];
        $apiKey = $cfg['API_KEY'];

        if ($apiKey === '') {
            jsonResponse(false, 'API key is not configured. Set it in the settings above.');
        }

        $svcResult = apiGet($host, $apiKey, '/api/v1/status');
        if ( ! $svcResult['ok']) {
            $body   = $svcResult['body'];
            $detail = is_array($body) && isset($body['detail']) && is_string($body['detail'])
                ? $body['detail']
                : 'Could not reach the Hako Foundry API.';
            jsonResponse(false, $detail);
        }

        $fansResult = apiGet($host, $apiKey, '/api/v1/fans/status');
        $pbResult   = apiGet($host, $apiKey, '/api/v1/powerboards');

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
        exit;

    case 'save_config':
        $host = trim(postStr('API_HOST', 'http://127.0.0.1:8080'));
        if ($host === '' || ! preg_match('#^https?://#', $host)) {
            jsonResponse(false, 'API host must start with http:// or https://');
        }

        $apiKey = postStr('API_KEY');

        $pollInterval = filter_var(
            $_POST['POLL_INTERVAL'] ?? '5',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 2, 'max_range' => 60]]
        );
        if ($pollInterval === false) {
            $pollInterval = 5;
        }

        saveConfig([
            'API_HOST'      => $host,
            'API_KEY'       => $apiKey,
            'POLL_INTERVAL' => (string)$pollInterval,
        ]);

        echo json_encode([
            'success'       => true,
            'message'       => 'Settings saved.',
            'poll_interval' => $pollInterval,
        ]);
        exit;

    default:
        jsonResponse(false, 'Unknown action: ' . htmlspecialchars($action));
}
