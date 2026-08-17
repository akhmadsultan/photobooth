<?php
/**
 * get_gallery.php — Return paginated gallery items as JSON
 * GET params: page, per_page
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '../uploads/');

$page    = max(1, (int)($_GET['page']     ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));

// Scan upload directory for session folders
$sessions = [];

if (is_dir(UPLOAD_DIR)) {
    $dirs = glob(UPLOAD_DIR . 'PB*/');
    if ($dirs) {
        // Sort newest first
        usort($dirs, fn($a, $b) => filemtime($b) <=> filemtime($a));

        foreach ($dirs as $dir) {
            $metaFile = $dir . 'meta.json';
            $stripFile = $dir . 'strip.png';

            if (!file_exists($stripFile)) continue;

            $meta = [];
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true) ?? [];
            }

            $sessionId = basename($dir);
            $sessions[] = [
                'id'         => $sessionId,
                'session_id' => $meta['session_id']  ?? $sessionId,
                'strip_url'  => UPLOAD_URL . $sessionId . '/strip.png',
                'strip_path' => $dir . 'strip.png',
                'created_at' => $meta['created_at']  ?? date('Y-m-d H:i:s', filemtime($dir)),
                'filter'     => $meta['filter']      ?? 'none',
                'frame_style'=> $meta['frame_style'] ?? 'none',
                'gesture_count' => $meta['gesture_count'] ?? 0,
                'frame_count'   => $meta['frame_count']   ?? 0,
                'frames'     => _getFrameURLs($dir, $sessionId, $meta['files'] ?? [])
            ];
        }
    }
}

// Paginate
$total  = count($sessions);
$offset = ($page - 1) * $perPage;
$items  = array_slice($sessions, $offset, $perPage);

echo json_encode([
    'success'   => true,
    'total'     => $total,
    'page'      => $page,
    'per_page'  => $perPage,
    'pages'     => max(1, ceil($total / $perPage)),
    'items'     => $items
]);

function _getFrameURLs(string $dir, string $sessionId, array $files): array {
    $frames = [];
    for ($i = 0; $i < 4; $i++) {
        $file = $dir . "frame_{$i}.jpg";
        if (file_exists($file)) {
            $frames[] = UPLOAD_URL . $sessionId . "/frame_{$i}.jpg";
        }
    }
    return $frames;
}
