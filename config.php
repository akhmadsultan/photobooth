<?php
/**
 * config.php — Central Configuration for Photobooth System
 * Configure Google Drive API and application parameters.
 */

// ── Auto-load .env if file exists ────────────────────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k);
            $v = trim(trim($v), '"\'');
            if (!array_key_exists($k, $_ENV)) {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
            }
        }
    }
}

function _env(string $key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

return [
    // ── Application Settings ─────────────────────────────────
    'app_name'         => _env('APP_NAME', 'Photobooth Undersea'),
    'app_url'          => _env('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8443')),
    'qr_timer_seconds' => (int)_env('QR_TIMER_SECONDS', 60),

    // ── Google Drive API Settings ───────────────────────────
    'gdrive' => [
        'enabled'              => filter_var(_env('GDRIVE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'client_id'            => _env('GDRIVE_CLIENT_ID'),
        'client_secret'        => _env('GDRIVE_CLIENT_SECRET'),
        'refresh_token'        => _env('GDRIVE_REFRESH_TOKEN'),
        'service_account_json' => __DIR__ . '/gdrive_service_account.json',
        'parent_folder_id'     => _env('GDRIVE_PARENT_FOLDER_ID', '1svxiGrt6M3K4DUmdYTJ1SYhDb9zov90G'),
    ]
];
