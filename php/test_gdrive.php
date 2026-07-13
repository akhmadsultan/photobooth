<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/gdrive_helper.php';

echo "=== GDrive Upload Test ===\n\n";

// Step 1: Token
echo "1. Getting Access Token...\n";
$auth = gdrive_get_access_token();
if (isset($auth['error'])) { die("FAIL: " . $auth['error'] . "\n"); }
echo "   OK: " . substr($auth['access_token'], 0, 20) . "...\n\n";

// Step 2: Create folder
echo "2. Creating test folder...\n";
$folderResult = gdrive_create_folder("TEST_UPLOAD_" . time());
if (isset($folderResult['error'])) { die("FAIL: " . $folderResult['error'] . "\n"); }
$folderId = $folderResult['id'];
echo "   OK: folder ID = $folderId\n\n";

// Step 3: Share folder
echo "3. Sharing folder...\n";
$shareResult = gdrive_share_item($folderId);
if (isset($shareResult['error'])) { echo "   WARN: " . $shareResult['error'] . "\n"; }
else echo "   OK\n\n";

// Step 4: Create a small test PNG (1x1 pixel) and upload it
echo "4. Creating 1x1 test PNG...\n";
$tmpPng = tempnam(sys_get_temp_dir(), 'test_') . '.png';
// Minimal valid 1x1 red PNG in base64
$pngData = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg=='
);
file_put_contents($tmpPng, $pngData);
echo "   Created: $tmpPng (" . filesize($tmpPng) . " bytes)\n\n";

echo "5. Uploading test PNG to Drive folder...\n";
$upResult = gdrive_upload_file($tmpPng, 'test_image.png', 'image/png', $folderId);
@unlink($tmpPng);

if (isset($upResult['error'])) {
    echo "   FAIL: " . $upResult['error'] . "\n";
} else {
    echo "   OK: file ID = " . $upResult['id'] . "\n";
    echo "   View folder: https://drive.google.com/drive/folders/$folderId\n";
}
