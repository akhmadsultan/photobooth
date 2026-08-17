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
    $parentFolderId = $gdriveCfg['parent_folder_id'] ?? null;
    $folderId = _createGDriveFolder($accessToken, $folderName, $parentFolderId);
    if (!$folderId) {
        throw new Exception('Failed to create folder on Google Drive');
    }

    // 2. Make Folder Public
    _makeGDrivePublic($accessToken, $folderId);
    $folderWebUrl = "https://drive.google.com/drive/folders/{$folderId}";

    // 3. Upload files to created folder
    $gdriveUrls = [];
    $targetFiles = [
        'strip.png'     => 'photo_strip_url',
        'frame_0.jpg'   => 'photo1_url',
        'frame_1.jpg'   => 'photo2_url',
        'frame_2.jpg'   => 'photo3_url',
        'frame_3.jpg'   => 'photo4_url',
        'boomerang.gif' => 'gif_url'
    ];

    foreach ($targetFiles as $filename => $fieldKey) {
        $filePath = $sessionDir . $filename;
        if (file_exists($filePath)) {
            $mimeType = _getMimeType($filename);
            $fileId = _uploadFileToGDrive($accessToken, $filePath, $filename, $mimeType, $folderId);
            if ($fileId) {
                _makeGDrivePublic($accessToken, $fileId);
                // Web view link or direct view URL
                $gdriveUrls[$fieldKey] = "https://drive.google.com/uc?export=view&id={$fileId}";
            } else {
                $gdriveUrls[$fieldKey] = $localFiles[str_replace('_url','',$fieldKey)] ?? "{$baseUrl}{$filename}";
            }
        } else {
            $gdriveUrls[$fieldKey] = "{$baseUrl}{$filename}";
        }
    }

    $response = [
        'success'                 => true,
        'is_gdrive'              => true,
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
function _getGDriveAccessToken(array $cfg): ?string {
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
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt
        ]));
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $res['access_token'] ?? null;
    }

    if (!empty($cfg['refresh_token']) && !empty($cfg['client_id']) && !empty($cfg['client_secret'])) {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'refresh_token' => $cfg['refresh_token'],
            'grant_type'    => 'refresh_token'
        ]));
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $res['access_token'] ?? null;
    }
    return null;
}

function _createGDriveFolder(string $token, string $folderName, ?string $parentId = null): ?string {
    $meta = ['name' => $folderName, 'mimeType' => 'application/vnd.google-apps.folder'];
    if ($parentId) $meta['parents'] = [$parentId];
    
    $ch = curl_init('https://www.googleapis.com/drive/v3/files');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($meta));
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $res['id'] ?? null;
}

function _uploadFileToGDrive(string $token, string $filePath, string $name, string $mime, string $folderId): ?string {
    $boundary = '-------' . md5(time());
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
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        "Content-Type: multipart/related; boundary={$boundary}"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $res['id'] ?? null;
}

function _makeGDrivePublic(string $token, string $fileOrFolderId): void {
    $ch = curl_init("https://www.googleapis.com/drive/v3/files/{$fileOrFolderId}/permissions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'role' => 'reader',
        'type' => 'anyone'
    ]));
    curl_exec($ch);
    curl_close($ch);
}

function _getMimeType(string $filename): string {
    if (str_ends_with($filename, '.png')) return 'image/png';
    if (str_ends_with($filename, '.gif')) return 'image/gif';
    return 'image/jpeg';
}
