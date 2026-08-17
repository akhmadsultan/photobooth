<?php
/**
 * php/authorize.php — Easy OAuth 2.0 Authorization Code Flow script.
 * Open this script in your browser (http://localhost/photoboothtest/php/authorize.php)
 * to authorize your personal Google Drive account and generate client credentials.
 */

define('CREDENTIALS_FILE', __DIR__ . '/gdrive_credentials.json');

// We use http://localhost/photoboothtest/php/authorize.php as the redirect URI
$redirectUri = 'http://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');

// If credentials file already exists, let's prefill client ID and client secret
$clientId = '';
$clientSecret = '';
if (file_exists(CREDENTIALS_FILE)) {
    $creds = json_decode(file_get_contents(CREDENTIALS_FILE), true);
    $clientId = $creds['client_id'] ?? '';
    $clientSecret = $creds['client_secret'] ?? '';
}

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Exchange Auth Code for Refresh Token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code'          => $code,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    echo "<h1>Hasil Otorisasi</h1>";
    if ($status >= 400 || !isset($data['refresh_token'])) {
        echo "<p style='color:red; font-weight:bold;'>Gagal menukar kode otorisasi:</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        echo "<p>Pastikan Client ID & Client Secret di file <code>php/gdrive_credentials.json</code> sudah benar dan sama dengan yang Anda gunakan untuk login.</p>";
    } else {
        // Save back to JSON
        $newCreds = [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $data['refresh_token']
        ];
        file_put_contents(CREDENTIALS_FILE, json_encode($newCreds, JSON_PRETTY_PRINT));
        
        echo "<p style='color:green; font-weight:bold;'>Sukses! Akun Google Drive berhasil dihubungkan.</p>";
        echo "<p>Refresh Token telah disimpan ke <code>php/gdrive_credentials.json</code>.</p>";
        echo "<p><a href='../index.php'>Kembali ke Photobooth</a></p>";
    }
    exit;
}

if (isset($_POST['submit'])) {
    $clientId = trim($_POST['client_id']);
    $clientSecret = trim($_POST['client_secret']);
    
    // Save temporary credentials
    $tempCreds = [
        'client_id'     => $clientId,
        'client_secret' => $clientSecret
    ];
    file_put_contents(CREDENTIALS_FILE, json_encode($tempCreds, JSON_PRETTY_PRINT));
    
    // Redirect to Google Consent Screen
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'scope'         => 'https://www.googleapis.com/auth/drive',
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'response_type' => 'code',
        'redirect_uri'  => $redirectUri,
        'client_id'     => $clientId
    ]);
    
    header('Location: ' . $authUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Otorisasi Google Drive</title>
    <style>
        body { font-family: sans-serif; background: #eaf6ff; color: #1e3a5f; padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        h1 { margin-top: 0; font-size: 24px; }
        input[type="text"] { width: 100%; padding: 10px; margin: 10px 0 20px; border: 2px solid #bcd; border-radius: 6px; box-sizing: border-box; }
        button { background: #3aafde; color: white; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; font-size: 14px; }
        button:hover { background: #1e7ab8; }
        ol { padding-left: 20px; line-height: 1.6; }
    </style>
</head>
<body>
<div class="card">
    <h1>Hubungkan Google Drive Personal (OAuth 2.0)</h1>
    <p>Karena akun Google Drive personal tidak memiliki kuota untuk Service Account, Anda harus menghubungkan akun Google Anda sendiri menggunakan Client ID dan Client Secret OAuth 2.0.</p>
    
    <h2>Cara mendapatkan Client ID & Client Secret:</h2>
    <ol>
        <li>Buka <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.</li>
        <li>Buka menu <b>APIs & Services > Credentials</b>.</li>
        <li>Klik <b>Create Credentials > OAuth client ID</b>. (Jika diminta configure consent screen terlebih dahulu, pilih External, lalu isi nama aplikasi bebas).</li>
        <li>Pilih Application Type: <b>Web application</b>.</li>
        <li>Di bagian <b>Authorized redirect URIs</b>, tambahkan URI berikut:<br>
            <code><?= htmlspecialchars($redirectUri) ?></code>
        </li>
        <li>Klik Create, lalu salin Client ID & Client Secret ke form di bawah:</li>
    </ol>

    <form method="POST">
        <label><b>Client ID:</b></label>
        <input type="text" name="client_id" value="<?= htmlspecialchars($clientId) ?>" required placeholder="Masukkan OAuth Client ID Anda">

        <label><b>Client Secret:</b></label>
        <input type="text" name="client_secret" value="<?= htmlspecialchars($clientSecret) ?>" required placeholder="Masukkan OAuth Client Secret Anda">

        <button type="submit" name="submit">Hubungkan Akun Google Drive 🚀</button>
    </form>
</div>
</body>
</html>
