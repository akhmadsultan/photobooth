<?php
/**
 * php/supabase_sync.php — Send Photo Record to Supabase (Video Mapping Integration)
 * 
 * Accepts JSON or POST with photo URLs and inserts a new record into Supabase table.
 * Record fields:
 *  - photo_strip_url
 *  - photo1_url
 *  - photo2_url
 *  - photo3_url
 *  - photo4_url
 *  - gif_url
 *  - google_drive_folder_url
 *  - created_at
 *  - source = "photobooth"
 *  - status = "pending"
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
$supabaseCfg = $config['supabase'] ?? [];

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

$photoStripUrl   = filter_var($input['photo_strip_url']   ?? '', FILTER_SANITIZE_URL);
$photo1Url       = filter_var($input['photo1_url']        ?? '', FILTER_SANITIZE_URL);
$photo2Url       = filter_var($input['photo2_url']        ?? '', FILTER_SANITIZE_URL);
$photo3Url       = filter_var($input['photo3_url']        ?? '', FILTER_SANITIZE_URL);
$photo4Url       = filter_var($input['photo4_url']        ?? '', FILTER_SANITIZE_URL);
$gifUrl          = filter_var($input['gif_url']           ?? '', FILTER_SANITIZE_URL);
$gdriveFolderUrl = filter_var($input['google_drive_folder_url'] ?? '', FILTER_SANITIZE_URL);

if (empty($photoStripUrl)) {
    echo json_encode(['success' => false, 'message' => 'Missing photo_strip_url']);
    exit;
}

$supabaseUrl   = rtrim($supabaseCfg['url'] ?? '', '/');
$supabaseKey   = $supabaseCfg['key'] ?? '';
$tableName     = $supabaseCfg['table'] ?? 'video_mapping';

$record = [
    'photo_strip_url'         => $photoStripUrl,
    'photo1_url'              => $photo1Url,
    'photo2_url'              => $photo2Url,
    'photo3_url'              => $photo3Url,
    'photo4_url'              => $photo4Url,
    'gif_url'                 => $gifUrl,
    'google_drive_folder_url' => $gdriveFolderUrl,
    'created_at'              => date('Y-m-d\TH:i:s.v\Z'),
    'source'                  => 'photobooth',
    'status'                  => 'pending'
];

// Check if Supabase URL is placeholder or valid
$isConfigured = !empty($supabaseUrl) && 
    strpos($supabaseUrl, 'YOUR_PROJECT_ID') === false && 
    !empty($supabaseKey) && 
    strpos($supabaseKey, 'YOUR_SUPABASE_ANON') === false;

if (!$isConfigured) {
    // Return mock success with warning when placeholder is still in config
    echo json_encode([
        'success'    => true,
        'mock'       => true,
        'message'    => 'Data dikirim (Mode Simulasi Supabase — silakan masukkan URL & Key Supabase di config.php)',
        'data'       => $record
    ]);
    exit;
}

// ── Send to Supabase REST API ───────────────────────────────────────────────
$endpoint = "{$supabaseUrl}/rest/v1/{$tableName}";

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: {$supabaseKey}",
    "Authorization: Bearer {$supabaseKey}",
    'Content-Type: application/json',
    'Prefer: return=representation'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($record));
$responseBody = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError    = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'Gagal terhubung ke Supabase: ' . $curlError]);
    exit;
}

if ($httpCode >= 200 && $httpCode < 300) {
    $inserted = json_decode($responseBody, true);
    echo json_encode([
        'success' => true,
        'message' => 'Berhasil dikirim ke Video Mapping.',
        'data'    => $inserted
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => "Gagal mengirim ke Video Mapping (HTTP {$httpCode}): " . substr($responseBody, 0, 200)
    ]);
}
