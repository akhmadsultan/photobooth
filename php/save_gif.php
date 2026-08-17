<?php
/**
 * save_gif.php — Save GIF boomerang to server
 * POST: session_id (string), gif_file (file upload)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_GIF_MB', 8);

$sessionId = preg_replace('/[^A-Za-z0-9_\-]/', '', substr($_POST['session_id'] ?? 'UNKNOWN', 0, 64));
$sessionDir = UPLOAD_DIR . $sessionId . '/';

if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0755, true);
}

if (!isset($_FILES['gif_file']) || $_FILES['gif_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No GIF file received', 'error' => $_FILES['gif_file']['error'] ?? 'missing']);
    exit;
}

$tmpPath  = $_FILES['gif_file']['tmp_name'];
$fileSize = $_FILES['gif_file']['size'];

if ($fileSize > MAX_GIF_MB * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'GIF file too large']);
    exit;
}

// Validate it's actually a GIF (check magic bytes)
$fh = fopen($tmpPath, 'rb');
$magic = fread($fh, 6);
fclose($fh);

if (!in_array($magic, ['GIF87a', 'GIF89a'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid GIF file']);
    exit;
}

$destPath = $sessionDir . 'boomerang.gif';
if (!move_uploaded_file($tmpPath, $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save GIF']);
    exit;
}

// Update meta.json
$metaFile = $sessionDir . 'meta.json';
$meta = [];
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true) ?? [];
}
$meta['gif_saved']  = true;
$meta['gif_size']   = $fileSize;
$meta['gif_saved_at'] = date('Y-m-d H:i:s');
file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

echo json_encode([
    'success'  => true,
    'message'  => 'GIF saved',
    'path'     => '../uploads/' . $sessionId . '/boomerang.gif',
    'size'     => $fileSize
]);
