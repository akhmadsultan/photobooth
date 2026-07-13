<?php
/**
 * php/gdrive_helper.php — Standalone Google Drive API Client for PHP (No External Dependencies)
 * Handles Service Account JWT Authentication and Google Drive API v3 operations.
 */

// Define the parent folder ID where session subfolders will be created (Optional)
// Set this to your Google Drive shared folder ID.
define('GDRIVE_PARENT_FOLDER_ID', '1zpfn1W9HZpUejCCb8sAKt0J7orWrBZMj'); 

// Path to Google Service Account JSON credentials
define('GDRIVE_CREDENTIALS_FILE', __DIR__ . '/gdrive_credentials.json');

/**
 * Checks if the Google Drive credentials file exists.
 */
function gdrive_is_configured() {
    return file_exists(GDRIVE_CREDENTIALS_FILE);
}

/**
 * Encodes data to Base64Url format.
 */
function gdrive_base64url_encode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

/**
 * Obtains an OAuth2 access token from Google.
 * Supports both OAuth 2.0 Refresh Token (User Account) and Service Account JWT flows.
 */
function gdrive_get_access_token() {
    static $cached = null;
    if ($cached !== null) return $cached;

    if (!file_exists(GDRIVE_CREDENTIALS_FILE)) {
        return ['error' => 'Credentials file not found at ' . GDRIVE_CREDENTIALS_FILE];
    }

    $creds = json_decode(file_get_contents(GDRIVE_CREDENTIALS_FILE), true);
    if (!$creds) {
        return ['error' => 'Invalid credentials JSON format'];
    }

    // Check if OAuth 2.0 Client Credentials (User Account) are provided
    if (isset($creds['refresh_token']) && isset($creds['client_id']) && isset($creds['client_secret'])) {
        $url = 'https://oauth2.googleapis.com/token';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id'     => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'refresh_token' => $creds['refresh_token'],
            'grant_type'    => 'refresh_token'
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => 'OAuth token request curl error: ' . $err];
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            return ['error' => 'OAuth failed to obtain access token: ' . ($data['error_description'] ?? $response)];
        }

        $cached = ['access_token' => $data['access_token']];
        return $cached;
    } else {
        // Fallback to Service Account JWT Flow
        if (!isset($creds['private_key']) || !isset($creds['client_email'])) {
            return ['error' => 'Missing private_key, client_email or refresh_token in credentials JSON'];
        }

        $private_key  = $creds['private_key'];
        $client_email = $creds['client_email'];

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $claim = json_encode([
            'iss'   => $client_email,
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now
        ]);

        $header_b64 = gdrive_base64url_encode($header);
        $claim_b64  = gdrive_base64url_encode($claim);

        $signature = '';
        $success = openssl_sign($header_b64 . '.' . $claim_b64, $signature, $private_key, OPENSSL_ALGO_SHA256);
        if (!$success) {
            return ['error' => 'Signing JWT failed: ' . openssl_error_string()];
        }

        $sig_b64 = gdrive_base64url_encode($signature);
        $jwt = $header_b64 . '.' . $claim_b64 . '.' . $sig_b64;

        // Call Token Endpoint
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => 'Token request curl error: ' . $err];
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            return ['error' => 'Failed to obtain access token: ' . ($data['error_description'] ?? $response)];
        }

        $cached = ['access_token' => $data['access_token']];
        return $cached;
    }
}

/**
 * Creates a folder inside Google Drive.
 */
function gdrive_create_folder($folderName, $parentFolderId = null) {
    $auth = gdrive_get_access_token();
    if (isset($auth['error'])) return $auth;
    $accessToken = $auth['access_token'];

    $url = 'https://www.googleapis.com/drive/v3/files';
    $metadata = [
        'name'     => $folderName,
        'mimeType' => 'application/vnd.google-apps.folder'
    ];
    
    // Use configured parent folder if not explicitly overridden
    $parent = ($parentFolderId !== null) ? $parentFolderId : GDRIVE_PARENT_FOLDER_ID;
    if (!empty($parent)) {
        $metadata['parents'] = [$parent];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['error' => 'Create folder curl error: ' . $err];
    }
    
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($status >= 400 || !isset($data['id'])) {
        return ['error' => "Folder creation failed ($status): " . $response];
    }

    return ['id' => $data['id']];
}

/**
 * Uploads a file to Google Drive using a binary-safe multipart upload.
 * Files are uploaded INTO the shared parent folder (owned by your account),
 * so the storage quota is charged to the folder owner, not the Service Account.
 */
function gdrive_upload_file($filePath, $fileName, $mimeType, $parentFolderId) {
    if (!file_exists($filePath)) {
        return ['error' => 'File not found at ' . $filePath];
    }

    $auth = gdrive_get_access_token();
    if (isset($auth['error'])) return $auth;
    $accessToken = $auth['access_token'];

    $boundary = 'GDRIVEBOUNDARY' . md5(uniqid('', true));

    $metadata = json_encode(['name' => $fileName, 'parents' => [$parentFolderId]]);
    $fileData = file_get_contents($filePath);

    // Build binary-safe multipart body
    $bodyParts =
        "--{$boundary}\r\n" .
        "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
        $metadata . "\r\n" .
        "--{$boundary}\r\n" .
        "Content-Type: {$mimeType}\r\n\r\n" .
        $fileData . "\r\n" .
        "--{$boundary}--\r\n";

    // Write to temp file so curl can read exact bytes (avoids mbstring issues)
    $tmpFile = tempnam(sys_get_temp_dir(), 'gdrvup_');
    file_put_contents($tmpFile, $bodyParts);
    $bodySize = filesize($tmpFile);
    $fh = fopen($tmpFile, 'rb');

    // supportsAllDrives=true allows writing to Shared Drives too
    $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_READFUNCTION, function($ch, $fd, $length) {
        return fread($fd, $length);
    });
    // Use POSTFIELDS with the raw string body — simpler and works for POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyParts);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: multipart/related; boundary=' . $boundary,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_errno($ch) ? curl_error($ch) : null;
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fh);
    @unlink($tmpFile);

    if ($curlErr) {
        return ['error' => 'Upload curl error: ' . $curlErr];
    }

    $data = json_decode($response, true);
    if ($status >= 400 || !isset($data['id'])) {
        return ['error' => "File upload failed ({$status}) for {$fileName}: " . $response];
    }

    return ['id' => $data['id']];
}

/**
 * Grants public read permissions to any file/folder ("anyone with link can view").
 */
function gdrive_share_item($itemId) {
    $auth = gdrive_get_access_token();
    if (isset($auth['error'])) return $auth;
    $accessToken = $auth['access_token'];

    $url = "https://www.googleapis.com/drive/v3/files/{$itemId}/permissions";
    $body = json_encode([
        'role' => 'reader',
        'type' => 'anyone'
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['error' => 'Share item curl error: ' . $err];
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 400) {
        return ['error' => "Sharing failed ($status): " . $response];
    }

    return ['success' => true];
}
