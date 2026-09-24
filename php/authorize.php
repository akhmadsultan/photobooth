<?php
/**
 * php/authorize.php — Easy OAuth 2.0 Authorization Code Flow script.
 * Open this script in your browser (e.g. http://localhost/photobooth/php/authorize.php)
 * to authorize your Google Drive account and generate credentials.
 */

define('CREDENTIALS_FILE', __DIR__ . '/gdrive_credentials.json');
define('ENV_FILE', dirname(__DIR__) . '/.env');

// Protocol detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? 'https' : 'http';
$redirectUri = $protocol . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');

// Load existing credentials
$clientId = '';
$clientSecret = '';
$parentFolder = '';
$refreshToken = '';

if (file_exists(CREDENTIALS_FILE)) {
    $creds = json_decode(file_get_contents(CREDENTIALS_FILE), true) ?: [];
    $clientId     = $creds['client_id'] ?? '';
    $clientSecret = $creds['client_secret'] ?? '';
    $parentFolder = $creds['parent_folder_id'] ?? '';
    $refreshToken = $creds['refresh_token'] ?? '';
}

// Check if .env has values that override
$config = file_exists(dirname(__DIR__) . '/config.php') ? (require dirname(__DIR__) . '/config.php') : [];
if (!empty($config['gdrive']['client_id']))     $clientId     = $config['gdrive']['client_id'];
if (!empty($config['gdrive']['client_secret'])) $clientSecret = $config['gdrive']['client_secret'];
if (!empty($config['gdrive']['parent_folder_id'])) $parentFolder = $config['gdrive']['parent_folder_id'];
if (!empty($config['gdrive']['refresh_token'])) $refreshToken = $config['gdrive']['refresh_token'];

$isConnected = !empty($refreshToken);

// ── Step 2: Handle Google Redirect Back with ?code= ──────────────────────
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
    $curlErr  = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Hasil Otorisasi — Photobooth</title>';
    echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#07142a;color:#eaf6ff;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px;}';
    echo '.card{background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.18);backdrop-filter:blur(16px);border-radius:16px;padding:36px;max-width:560px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,0.4);text-align:center;}';
    echo 'h1{font-size:24px;margin-top:0;} p{line-height:1.6;color:#9bc5df;} .btn{display:inline-block;background:#2ecfb0;color:#07142a;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:8px;margin:8px;transition:0.2s;} .btn:hover{background:#38efcb;} .btn-sec{background:rgba(255,255,255,0.15);color:#fff;} pre{background:#040c1a;padding:15px;border-radius:8px;text-align:left;overflow-x:auto;color:#ff8787;font-size:12px;}</style></head><body><div class="card">';

    if ($status >= 400 || empty($data['refresh_token'])) {
        echo '<h1 style="color:#ff6b6b;">Otorisasi Belum Berhasil</h1>';
        echo '<p>Google tidak mengembalikan <code>refresh_token</code>.</p>';
        if ($curlErr) {
            echo '<p style="color:#ff6b6b;">cURL Error: ' . htmlspecialchars($curlErr) . '</p>';
        }
        if (!empty($data['error_description'])) {
            echo '<p style="color:#ff6b6b;">Pesan Google: <b>' . htmlspecialchars($data['error_description']) . '</b></p>';
        }
        echo '<pre>' . htmlspecialchars($response ?: 'Tidak ada respon dari Google') . '</pre>';
        echo '<p style="font-size:13px;text-align:left;">Tips:<br>1. Pastikan Client ID & Secret sudah sama dengan di Google Cloud Console.<br>2. Pada OAuth Consent Screen, pastikan email Anda sudah ditambahkan di daftar <b>Test Users</b>.<br>3. Jika sebelumnya pernah menghubungkan, buka <a href="https://myaccount.google.com/permissions" target="_blank" style="color:#2ecfb0;">Google Account Permissions</a> dan cabut izin aplikasi ini, lalu coba klik Hubungkan lagi.</p>';
        echo '<p><a href="authorize.php" class="btn">Coba Lagi</a></p>';
    } else {
        // Save back to JSON
        $newCreds = [
            'client_id'        => $clientId,
            'client_secret'    => $clientSecret,
            'parent_folder_id' => $parentFolder,
            'refresh_token'    => $data['refresh_token']
        ];
        file_put_contents(CREDENTIALS_FILE, json_encode($newCreds, JSON_PRETTY_PRINT));
        
        // Also sync or create .env
        _syncToEnv($newCreds);

        echo '<h1 style="color:#2ecfb0;">Berhasil Terhubung!</h1>';
        echo '<p>Google Drive Anda berhasil dihubungkan ke sistem Photobooth. Refresh token sudah tersimpan otomatis.</p>';
        echo '<div style="margin-top:24px;">';
        echo '<a href="test_gdrive.php" class="btn btn-sec" target="_blank">Uji Upload ke Drive</a>';
        echo '<a href="../index.php" class="btn">Buka Photobooth</a>';
        echo '</div>';
    }
    echo '</div></body></html>';
    exit;
}

// ── Step 1: User Submits Client ID & Secret Form ─────────────────────────
if (isset($_POST['submit'])) {
    $clientId     = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');
    $rawFolder    = trim($_POST['parent_folder_id'] ?? '');

    // Extract ID if full Google Drive URL was pasted
    $folderId = $rawFolder;
    if (preg_match('/folders\/([a-zA-Z0-9_\-]+)/', $rawFolder, $m)) {
        $folderId = $m[1];
    }

    // Save temporary credentials
    $tempCreds = [
        'client_id'        => $clientId,
        'client_secret'    => $clientSecret,
        'parent_folder_id' => $folderId,
        'refresh_token'    => $refreshToken
    ];
    file_put_contents(CREDENTIALS_FILE, json_encode($tempCreds, JSON_PRETTY_PRINT));
    _syncToEnv($tempCreds);

    // Redirect to Google Consent Screen
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'scope'         => 'https://www.googleapis.com/auth/drive',
        'access_type'   => 'offline',
        'prompt'        => 'select_account consent',
        'response_type' => 'code',
        'redirect_uri'  => $redirectUri,
        'client_id'     => $clientId
    ]);

    header('Location: ' . $authUrl);
    exit;
}

// Helper to write to .env
function _syncToEnv(array $creds): void {
    $envPath = ENV_FILE;
    $content = file_exists($envPath) ? file_get_contents($envPath) : '';
    
    $updates = [
        'GDRIVE_ENABLED'          => 'true',
        'GDRIVE_CLIENT_ID'        => '"' . addslashes($creds['client_id'] ?? '') . '"',
        'GDRIVE_CLIENT_SECRET'    => '"' . addslashes($creds['client_secret'] ?? '') . '"',
        'GDRIVE_REFRESH_TOKEN'    => '"' . addslashes($creds['refresh_token'] ?? '') . '"',
        'GDRIVE_PARENT_FOLDER_ID' => '"' . addslashes($creds['parent_folder_id'] ?? '') . '"'
    ];

    foreach ($updates as $k => $v) {
        if (preg_match("/^{$k}=.*$/m", $content)) {
            $content = preg_replace("/^{$k}=.*$/m", "{$k}={$v}", $content);
        } else {
            $content = rtrim($content) . "\n{$k}={$v}\n";
        }
    }
    @file_put_contents($envPath, trim($content) . "\n");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otorisasi Google Drive — Photobooth Undersea</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&family=DM+Mono:wght@400;500&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07142a;
            --card-bg: rgba(255, 255, 255, 0.07);
            --card-border: rgba(255, 255, 255, 0.15);
            --teal: #2ecfb0;
            --teal-hover: #38efcb;
            --text: #eaf6ff;
            --text-sub: #9bc5df;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 30px 15px;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            width: 100%;
            max-width: 680px;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 32px;
            backdrop-filter: blur(14px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        h1 {
            font-family: 'Syne', sans-serif;
            font-size: 24px;
            margin-top: 0;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .badge-connected { background: rgba(46, 207, 176, 0.15); border: 1px solid #2ecfb0; color: #2ecfb0; }
        .badge-disconnected { background: rgba(255, 107, 107, 0.15); border: 1px solid #ff6b6b; color: #ff6b6b; }
        
        ol {
            padding-left: 20px;
            line-height: 1.6;
            color: var(--text-sub);
            font-size: 14px;
        }
        li { margin-bottom: 8px; }
        li b { color: #fff; }
        a { color: var(--teal); text-decoration: none; }
        a:hover { text-decoration: underline; }
        code {
            background: rgba(0,0,0,0.4);
            color: var(--teal);
            padding: 3px 6px;
            border-radius: 4px;
            font-family: 'DM Mono', monospace;
            font-size: 13px;
            word-break: break-all;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 6px;
            color: #d6ecff;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: #fff;
            font-family: 'DM Mono', monospace;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus {
            border-color: var(--teal);
        }
        .hint {
            font-size: 12px;
            color: #7fa4bf;
            margin-top: 4px;
        }
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        button {
            flex: 1;
            min-width: 200px;
            background: var(--teal);
            color: #07142a;
            border: none;
            padding: 13px 20px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-family: 'Syne', sans-serif;
            transition: all 0.2s;
        }
        button:hover {
            background: var(--teal-hover);
            transform: translateY(-1px);
        }
        .btn-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.1);
            color: #fff;
            padding: 13px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-link:hover {
            background: rgba(255,255,255,0.2);
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Hubungkan Google Drive (OAuth 2.0)</h1>

        <?php if ($isConnected): ?>
            <div class="badge-status badge-connected">
                ● Akun Google Drive Terhubung & Siap Digunakan
            </div>
        <?php else: ?>
            <div class="badge-status badge-disconnected">
                ○ Belum Terhubung ke Google Drive
            </div>
        <?php endif; ?>

        <p style="color:var(--text-sub); font-size:14px; line-height:1.6;">
            Hubungkan akun Google pribadi Anda agar foto photobooth (strip, 4 frame individual, dan boomerang GIF) otomatis diunggah ke Google Drive dan tamu dapat langsung scan QR code menuju folder mereka.
        </p>

        <h3 style="font-size:15px; margin-top:20px; margin-bottom:8px; color:#fff;">Langkah Singkat Pengaturan:</h3>
        <ol>
            <li>Buka <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> & buat / pilih project.</li>
            <li>Di <b>APIs & Services &gt; Library</b>, cari <b>Google Drive API</b> lalu klik <b>Enable</b>.</li>
            <li>Di <b>APIs & Services &gt; OAuth consent screen</b>: Pilih <b>External</b>, isi nama aplikasi &amp; email, lalu di bagian <b>Test users</b> tambahkan email Google yang akan Anda pakai.</li>
            <li>Di <b>APIs & Services &gt; Credentials</b>: Klik <b>Create Credentials &gt; OAuth client ID</b> (Type: <b>Web application</b>).</li>
            <li>Pada kolom <b>Authorized redirect URIs</b>, tambahkan URI berikut:<br>
                <code><?= htmlspecialchars($redirectUri) ?></code>
            </li>
            <li>Salin <b>Client ID</b> dan <b>Client Secret</b> ke form di bawah:</li>
        </ol>

        <form method="POST">
            <div class="form-group">
                <label>OAuth Client ID:</label>
                <input type="text" name="client_id" value="<?= htmlspecialchars($clientId) ?>" required placeholder="Contoh: 123456789-abc.apps.googleusercontent.com">
            </div>

            <div class="form-group">
                <label>OAuth Client Secret:</label>
                <input type="text" name="client_secret" value="<?= htmlspecialchars($clientSecret) ?>" required placeholder="Contoh: GOCSPX-xxxxxxxxxxxx">
            </div>

            <div class="form-group">
                <label>Google Drive Parent Folder ID atau Link (Opsional):</label>
                <input type="text" name="parent_folder_id" value="<?= htmlspecialchars($parentFolder) ?>" placeholder="Contoh: 1svxiGrt6M3K4DUmdYTJ1SYhDb9zov90G atau link folder Google Drive">
                <div class="hint">Jika dikosongkan, folder sesi foto akan dibuat langsung di folder utama (Root) Google Drive Anda. Pastikan folder induk sudah diatur sharing: "Anyone with the link (Viewer)".</div>
            </div>

            <div class="actions">
                <button type="submit" name="submit">
                    <?= $isConnected ? 'Hubungkan Ulang Akun Google' : 'Hubungkan Akun Google Drive' ?>
                </button>
                <?php if ($isConnected): ?>
                    <a href="test_gdrive.php" class="btn-link" target="_blank">Uji Koneksi</a>
                <?php endif; ?>
                <a href="../index.php" class="btn-link">Kembali</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
