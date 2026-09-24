<?php
/**
 * php/upload_gdrive.php — Upload session files to Google Drive
 * 
 * Uploads strip.png, frame_0..3, and boomerang.gif to Google Drive inside folder Photobooth/YYYY-MM-DD_HH-mm-ss.
 * Sets public permissions for shareable links.
 * Returns public share links for all files and folder.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

@set_time_limit(180);
@ini_set('max_execution_time', 180);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$config = require __DIR__ . '/../config.php';
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

$sessionId = preg_replace('/[^A-Za-z0-9_\-]/', '', substr($_POST['session_id'] ?? $_GET['session_id'] ?? '', 0, 64));

if (empty($sessionId)) {
    echo json_encode(['success' => false, 'message' => 'Missing session_id']);
    exit;
}

$sessionDir = UPLOAD_DIR . $sessionId . '/';
if (!is_dir($sessionDir)) {
    echo json_encode(['success' => false, 'message' => 'Session directory not found on server']);
    exit;
}

$metaFile = $sessionDir . 'meta.json';
$meta = file_exists($metaFile) ? (json_decode(file_get_contents($metaFile), true) ?? []) : [];

$folderName = date('Y-m-d_H-i-s');
if (!empty($meta['timestamp'])) {
    $folderName = date('Y-m-d_H-i-s', (int)($meta['timestamp'] / 1000));
}

// ── Check if Google Drive API is enabled & configured ───────────────────────
$gdriveCfg = $config['gdrive'] ?? [];
$isGDriveConfigured = !empty($gdriveCfg['enabled']) && 
    (!empty($gdriveCfg['refresh_token']) || file_exists($gdriveCfg['service_account_json'] ?? ''));

// Build local URLs for fallback
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$baseUrl  = "{$protocol}://{$host}{$basePath}/uploads/{$sessionId}/";

$fileMap = [
    'strip.png'     => 'strip_file',
    'frame_0.jpg'   => 'photo1',
    'frame_1.jpg'   => 'photo2',
    'frame_2.jpg'   => 'photo3',
    'frame_3.jpg'   => 'photo4',
    'boomerang.gif' => 'gif'
];

// Check existing local files
$localFiles = [];
foreach ($fileMap as $filename => $key) {
    if (file_exists($sessionDir . $filename)) {
        $localFiles[$key] = $baseUrl . $filename;
    }
}

if (!$isGDriveConfigured) {
    // Return structured response with local fallback URLs
    $response = [
        'success'                 => true,
        'is_gdrive'              => false,
        'message'                 => 'Google Drive credentials not configured. Using local session URLs.',
        'folder_name'             => $folderName,
        'google_drive_folder_url' => "{$baseUrl}",
        'photo_strip_url'         => $localFiles['strip_file'] ?? ($baseUrl . 'strip.png'),
        'photo1_url'              => $localFiles['photo1']     ?? ($baseUrl . 'frame_0.jpg'),
        'photo2_url'              => $localFiles['photo2']     ?? ($baseUrl . 'frame_1.jpg'),
        'photo3_url'              => $localFiles['photo3']     ?? ($baseUrl . 'frame_2.jpg'),
        'photo4_url'              => $localFiles['photo4']     ?? ($baseUrl . 'frame_3.jpg'),
        'gif_url'                 => $localFiles['gif']        ?? ($baseUrl . 'boomerang.gif')
    ];

    // Update meta.json
    $meta['gdrive_uploaded'] = true;
    $meta['gdrive_data']     = $response;
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

    echo json_encode($response);
    exit;
}

// ── Google Drive API Upload Logic ──────────────────────────────────────────
try {
    $accessToken = _getGDriveAccessToken($gdriveCfg);
    if (!$accessToken) {
        throw new Exception('Failed to obtain Google Drive Access Token');
    }

    // 1. Create Folder on Google Drive
    $parentFolderId = trim($gdriveCfg['parent_folder_id'] ?? '');
    if (!empty($parentFolderId)) {
        if (preg_match('/folders\/([a-zA-Z0-9_\-]+)/', $parentFolderId, $m)) {
            $parentFolderId = $m[1];
        }
    } else {
        $parentFolderId = null;
    }

    $folderId = _createGDriveFolder($accessToken, $folderName, $parentFolderId);

    // If folder creation failed, token might have expired/revoked: clear cache and retry once
    if (!$folderId) {
        _clearGDriveTokenCache();
        $accessToken = _getGDriveAccessToken($gdriveCfg, true);
        if ($accessToken) {
            $folderId = _createGDriveFolder($accessToken, $folderName, $parentFolderId);
        }
    }

    if (!$folderId) {
        throw new Exception('Failed to create folder on Google Drive');
    }

    $folderWebUrl = "https://drive.google.com/drive/folders/{$folderId}";

    // 2. Parallel Upload Files & Share Folder via curl_multi
    $targetFiles = [
        'strip.png'     => 'photo_strip_url',
        'frame_0.jpg'   => 'photo1_url',
        'frame_1.jpg'   => 'photo2_url',
        'frame_2.jpg'   => 'photo3_url',
        'frame_3.jpg'   => 'photo4_url',
        'boomerang.gif' => 'gif_url'
    ];

    $mh = curl_multi_init();
    $curlHandles = [];

    // Make folder public in parallel with file uploads to save round-trip time
    $permHandle = curl_init("https://www.googleapis.com/drive/v3/files/{$folderId}/permissions");
    curl_setopt_array($permHandle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_TCP_NODELAY    => 1,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$accessToken}",
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'role' => 'reader',
            'type' => 'anyone'
        ])
    ]);
    curl_multi_add_handle($mh, $permHandle);

    // Prepare each file upload handle for parallel execution
    foreach ($targetFiles as $filename => $fieldKey) {
        $filePath = $sessionDir . $filename;
        if (file_exists($filePath)) {
            $mimeType = _getMimeType($filename);
            $ch = _initUploadCurlHandle($accessToken, $filePath, $filename, $mimeType, $folderId);
            if ($ch) {
                curl_multi_add_handle($mh, $ch);
                $curlHandles[$fieldKey] = [
                    'handle'   => $ch,
                    'filename' => $filename
                ];
            }
        }
    }

    // Execute all uploads and permission request simultaneously
    $running = null;
    do {
        $mrc = curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.05);
        }
    } while ($running > 0 && $mrc === CURLM_OK);

    // Clean up permission handle
    curl_multi_remove_handle($mh, $permHandle);
    curl_close($permHandle);

    // Collect responses for all file uploads
    $gdriveUrls = [];
    foreach ($targetFiles as $filename => $fieldKey) {
        if (isset($curlHandles[$fieldKey])) {
            $ch = $curlHandles[$fieldKey]['handle'];
            $raw = curl_multi_getcontent($ch);
            $res = json_decode($raw, true);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if (!empty($res['id'])) {
                $gdriveUrls[$fieldKey] = "https://drive.google.com/uc?export=view&id={$res['id']}";
            } else {
                $gdriveUrls[$fieldKey] = $localFiles[str_replace('_url', '', $fieldKey)] ?? "{$baseUrl}{$filename}";
            }
        } else {
            $gdriveUrls[$fieldKey] = "{$baseUrl}{$filename}";
        }
    }
    curl_multi_close($mh);

    $response = [
        'success'                 => true,
        'is_gdrive'               => true,
        'message'                 => 'Files successfully uploaded to Google Drive',
        'folder_name'             => $folderName,
        'google_drive_folder_url' => $folderWebUrl,
        'photo_strip_url'         => $gdriveUrls['photo_strip_url'] ?? "{$baseUrl}strip.png",
        'photo1_url'              => $gdriveUrls['photo1_url']      ?? "{$baseUrl}frame_0.jpg",
        'photo2_url'              => $gdriveUrls['photo2_url']      ?? "{$baseUrl}frame_1.jpg",
        'photo3_url'              => $gdriveUrls['photo3_url']      ?? "{$baseUrl}frame_2.jpg",
        'photo4_url'              => $gdriveUrls['photo4_url']      ?? "{$baseUrl}frame_3.jpg",
        'gif_url'                 => $gdriveUrls['gif_url']         ?? "{$baseUrl}boomerang.gif"
    ];

    $meta['gdrive_uploaded'] = true;
    $meta['gdrive_data']     = $response;
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Google Drive upload error: ' . $e->getMessage()
    ]);
}

// ── Google Drive API Helpers ────────────────────────────────────────────────
function _clearGDriveTokenCache(): void {
    $cacheFile = __DIR__ . '/.gdrive_token_cache.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}

function _getGDriveAccessToken(array $cfg, bool $forceRefresh = false): ?string {
    $cacheFile = __DIR__ . '/.gdrive_token_cache.json';
    if (!$forceRefresh && file_exists($cacheFile)) {
        $cache = json_decode(@file_get_contents($cacheFile), true);
        if (!empty($cache['access_token']) && !empty($cache['expires_at']) && $cache['expires_at'] > (time() + 180)) {
            return $cache['access_token'];
        }
    }

    if (!empty($cfg['service_account_json']) && file_exists($cfg['service_account_json'])) {
        // Service Account JWT token exchange
        $sa = json_decode(file_get_contents($cfg['service_account_json']), true);
        if (!$sa || empty($sa['private_key']) || empty($sa['client_email'])) return null;
        
        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = base64_encode(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now
        ]));
        
        $signatureInput = $header . '.' . $claim;
        openssl_sign($signatureInput, $signature, $sa['private_key'], 'SHA256');
        $jwt = $signatureInput . '.' . base64_encode($signature);
        
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_TCP_NODELAY    => 1,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ])
        ]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $token = $res['access_token'] ?? null;
        if ($token) {
            $expiresIn = (int)($res['expires_in'] ?? 3500);
            @file_put_contents($cacheFile, json_encode([
                'access_token' => $token,
                'expires_at'   => time() + $expiresIn
            ]));
        }
        return $token;
    }

    if (!empty($cfg['refresh_token']) && !empty($cfg['client_id']) && !empty($cfg['client_secret'])) {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_TCP_NODELAY    => 1,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'refresh_token' => $cfg['refresh_token'],
                'grant_type'    => 'refresh_token'
            ])
        ]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $token = $res['access_token'] ?? null;
        if ($token) {
            $expiresIn = (int)($res['expires_in'] ?? 3500);
            @file_put_contents($cacheFile, json_encode([
                'access_token' => $token,
                'expires_at'   => time() + $expiresIn
            ]));
        }
        return $token;
    }
    return null;
}

function _createGDriveFolder(string $token, string $folderName, ?string $parentId = null): ?string {
    $meta = ['name' => $folderName, 'mimeType' => 'application/vnd.google-apps.folder'];
    if (!empty($parentId)) $meta['parents'] = [$parentId];
    
    $ch = curl_init('https://www.googleapis.com/drive/v3/files');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_TCP_NODELAY    => 1,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS     => json_encode($meta)
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $res = json_decode($raw, true);

    // Fallback: If parent folder was invalid/not found, retry creating in root Drive
    if (($status >= 400 || empty($res['id'])) && !empty($parentId)) {
        unset($meta['parents']);
        $ch2 = curl_init('https://www.googleapis.com/drive/v3/files');
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_TCP_NODELAY    => 1,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode($meta)
        ]);
        $res = json_decode(curl_exec($ch2), true);
        curl_close($ch2);
    }

    return $res['id'] ?? null;
}

function _initUploadCurlHandle(string $token, string $filePath, string $name, string $mime, string $folderId) {
    $boundary = '-------' . md5(microtime(true) . $name);
    $meta = json_encode(['name' => $name, 'parents' => [$folderId]]);
    
    $fileData = file_get_contents($filePath);
    $body = "--{$boundary}\r\n" .
            "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
            "{$meta}\r\n" .
            "--{$boundary}\r\n" .
            "Content-Type: {$mime}\r\n\r\n" .
            "{$fileData}\r\n" .
            "--{$boundary}--";

    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_TCP_NODELAY    => 1,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            "Content-Type: multipart/related; boundary={$boundary}",
            "Content-Length: " . strlen($body)
        ],
        CURLOPT_POSTFIELDS     => $body
    ]);
    return $ch;
}

function _uploadFileToGDrive(string $token, string $filePath, string $name, string $mime, string $folderId): ?string {
    $ch = _initUploadCurlHandle($token, $filePath, $name, $mime, $folderId);
    if (!$ch) return null;
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $res['id'] ?? null;
}

function _makeGDrivePublic(string $token, string $fileOrFolderId): void {
    $ch = curl_init("https://www.googleapis.com/drive/v3/files/{$fileOrFolderId}/permissions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_TCP_NODELAY    => 1,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'role' => 'reader',
            'type' => 'anyone'
        ])
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function _getMimeType(string $filename): string {
    if (str_ends_with($filename, '.png')) return 'image/png';
    if (str_ends_with($filename, '.gif')) return 'image/gif';
    return 'image/jpeg';
}
