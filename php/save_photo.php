<?php
/**
 * save_photo.php — Save photo strip + frames
 * Accepts multipart/form-data with binary file uploads.
 *
 * $_POST fields:
 *   session_id, filter, frame_style, timestamp
 *
 * $_FILES fields:
 *   strip_file          — the PNG strip (binary)
 *   frame_file_0..3     — individual JPEG frames (binary)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Kill any accidental output that would break JSON
ob_start();

require_once __DIR__ . '/gdrive_helper.php';


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(204); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);
define('MAX_BYTES', 15 * 1024 * 1024); // 15 MB per file

// ── Sanitize POST fields ───────────────────────────────────
$sessionId  = preg_replace('/[^A-Za-z0-9_\-]/', '', substr($_POST['session_id'] ?? 'UNKNOWN', 0, 64));
$filter     = preg_replace('/[^a-z0-9_\-]/', '', substr($_POST['filter']      ?? 'none', 0, 30));
$frameStyle = preg_replace('/[^a-z0-9_\-]/', '', substr($_POST['frame_style'] ?? 'none', 0, 30));
$timestamp  = (int)($_POST['timestamp'] ?? time() * 1000);

if (empty($sessionId)) {
    _die(false, 'Missing session_id');
}

// ── Create session directory ───────────────────────────────
$dir = UPLOAD_DIR . $sessionId . '/';
if (!is_dir($dir)) {
    if (!@mkdir($dir, 0755, true)) {
        _die(false, 'Cannot create directory. Check that uploads/ folder exists and is writable (chmod 755 or 777).');
    }
}

$saved  = [];
$errors = [];

// ── Save strip file ────────────────────────────────────────
if (isset($_FILES['strip_file']) && $_FILES['strip_file']['error'] === UPLOAD_ERR_OK) {
    $r = _moveUpload($_FILES['strip_file'], $dir, 'strip.png');
    if ($r['ok']) $saved['strip'] = 'strip.png';
    else          $errors[] = 'Strip: ' . $r['error'];
} else {
    $err = $_FILES['strip_file']['error'] ?? 'not received';
    $errors[] = 'Strip file missing or upload error: ' . _uploadErrMsg($err);
}

// ── Save individual frames ─────────────────────────────────
for ($i = 0; $i < 4; $i++) {
    $key = "frame_file_{$i}";
    if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
        $r = _moveUpload($_FILES[$key], $dir, "frame_{$i}.jpg");
        if ($r['ok']) $saved["frame_{$i}"] = "frame_{$i}.jpg";
        else          $errors[] = "Frame {$i}: " . $r['error'];
    }
}

// ── Save GIF file (if uploaded) ─────────────────────────────
if (isset($_FILES['gif_file']) && $_FILES['gif_file']['error'] === UPLOAD_ERR_OK) {
    $r = _moveUpload($_FILES['gif_file'], $dir, 'boomerang.gif');
    if ($r['ok']) $saved['gif'] = 'boomerang.gif';
    else          $errors[] = 'GIF: ' . $r['error'];
}

// ── Save metadata ──────────────────────────────────────────
$meta = [
    'session_id'  => $sessionId,
    'filter'      => $filter,
    'frame_style' => $frameStyle,
    'timestamp'   => $timestamp,
    'created_at'  => date('Y-m-d H:i:s'),
    'ip'          => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'frame_count' => count(array_filter(array_keys($saved), function($k) { return strpos($k, 'frame') === 0; })),
    'gif_saved'   => isset($saved['gif']),
    'gif_size'    => isset($saved['gif']) ? $_FILES['gif_file']['size'] : 0,
];
@file_put_contents($dir . 'meta.json', json_encode($meta, JSON_PRETTY_PRINT));

// ── Upload to Google Drive ────────────────────────────────
$gdriveLink = null;
$gdriveConfigured = gdrive_is_configured();

if ($gdriveConfigured && !empty($saved['strip'])) {
    $folderResult = gdrive_create_folder($sessionId);
    if (isset($folderResult['error'])) {
        $errors[] = 'GDrive Folder: ' . $folderResult['error'];
    } else {
        $folderId = $folderResult['id'];
        gdrive_share_item($folderId);
        $gdriveLink = 'https://drive.google.com/drive/folders/' . $folderId;
        
        // Upload strip.png
        $upStrip = gdrive_upload_file($dir . 'strip.png', 'strip.png', 'image/png', $folderId);
        if (isset($upStrip['error'])) {
            $errors[] = 'GDrive Strip: ' . $upStrip['error'];
        }
        
        // Upload boomerang.gif (if uploaded)
        if (isset($saved['gif'])) {
            $upGif = gdrive_upload_file($dir . 'boomerang.gif', 'boomerang.gif', 'image/gif', $folderId);
            if (isset($upGif['error'])) {
                $errors[] = 'GDrive GIF: ' . $upGif['error'];
            }
        }
        
        // Upload frames
        for ($i = 0; $i < 4; $i++) {
            $frameName = "frame_{$i}.jpg";
            if (isset($saved["frame_{$i}"])) {
                $upFrame = gdrive_upload_file($dir . $frameName, $frameName, 'image/jpeg', $folderId);
                if (isset($upFrame['error'])) {
                    $errors[] = "GDrive Frame {$i}: " . $upFrame['error'];
                }
            }
        }

        // Update meta.json with google drive references
        $meta['gdrive_folder_id'] = $folderId;
        $meta['gdrive_link']      = $gdriveLink;
        @file_put_contents($dir . 'meta.json', json_encode($meta, JSON_PRETTY_PRINT));
    }
} elseif (!empty($saved['strip'])) {
    // Fallback demo link
    $gdriveLink = 'https://drive.google.com/drive/folders/demo_unconfigured_' . $sessionId;
}

// ── Respond ────────────────────────────────────────────────
ob_end_clean(); // discard any stray output

if (!empty($saved['strip'])) {
    echo json_encode([
        'success'           => true,
        'message'           => 'Saved',
        'id'                => $sessionId,
        'path'              => '../uploads/' . $sessionId . '/strip.png',
        'saved'             => $saved,
        'gdrive_link'       => $gdriveLink,
        'gdrive_configured' => $gdriveConfigured,
        'errors'            => $errors
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed: ' . implode('; ', $errors),
        'errors'  => $errors
    ]);
}

// ── Helpers ────────────────────────────────────────────────
function _moveUpload(array $file, string $dir, string $destName): array {
    if ($file['size'] > MAX_BYTES) {
        return ['ok'=>false, 'error'=>'File too large ('.round($file['size']/1024/1024,1).'MB)'];
    }
    // Validate MIME via finfo (more reliable than extension)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES)) {
        return ['ok'=>false, 'error'=>"Invalid type: {$mime}"];
    }
    $dest = $dir . $destName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok'=>false, 'error'=>'move_uploaded_file failed — check folder permissions'];
    }
    return ['ok'=>true, 'bytes'=>$file['size']];
}

function _uploadErrMsg($code): string {
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension',
    ];
    return $msgs[$code] ?? "Unknown error code: {$code}";
}

function _die(bool $ok, string $msg): void {
    ob_end_clean();
    echo json_encode(['success'=>$ok,'message'=>$msg]);
    exit;
}
