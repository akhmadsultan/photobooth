<?php
/**
 * delete_photo.php — Delete a gallery session
 * POST: { id: session_id }
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/');

$input = json_decode(file_get_contents('php://input'), true);
$id    = preg_replace('/[^A-Za-z0-9_\-]/', '', substr($input['id'] ?? '', 0, 64));

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit;
}

$dir = UPLOAD_DIR . $id . '/';

if (!is_dir($dir)) {
    echo json_encode(['success' => false, 'message' => 'Session not found']);
    exit;
}

// Delete all files in session dir then the dir itself
$files = glob($dir . '*');
foreach ($files as $f) {
    if (is_file($f)) unlink($f);
}
rmdir($dir);

echo json_encode(['success' => true, 'message' => "Session {$id} deleted"]);
