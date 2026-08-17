<?php
/**
 * session_info.php — Get info for a specific session
 * GET param: id (session_id)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '../uploads/');

$id = preg_replace('/[^A-Za-z0-9_\-]/', '', substr($_GET['id'] ?? '', 0, 64));

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit;
}

$dir      = UPLOAD_DIR . $id . '/';
$metaFile = $dir . 'meta.json';

if (!is_dir($dir)) {
    echo json_encode(['success' => false, 'message' => 'Session not found']);
    exit;
}

$meta = [];
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true) ?? [];
}

// Collect file info
$files = [];
foreach (['strip.png', 'frame_0.jpg','frame_1.jpg','frame_2.jpg','frame_3.jpg','boomerang.gif'] as $f) {
    $fp = $dir . $f;
    if (file_exists($fp)) {
        $files[$f] = [
            'url'  => UPLOAD_URL . $id . '/' . $f,
            'size' => filesize($fp),
            'mtime'=> filemtime($fp)
        ];
    }
}

echo json_encode([
    'success'    => true,
    'session_id' => $id,
    'meta'       => $meta,
    'files'      => $files,
    'dir_size'   => array_sum(array_column($files, 'size'))
]);
