# TouchFree Photobooth System
## Implementasi Sistem Photobooth Touchless Berbasis Jaringan Multimedia
### Menggunakan WebRTC dan Hand Gesture Recognition

---

## 📋 Deskripsi Proyek

Sistem Photobooth Touchless berbasis web yang mengintegrasikan:
- **WebRTC** (getUserMedia) untuk live camera streaming
- **MediaPipe Hands** untuk real-time hand gesture recognition
- **HTML5 Canvas** untuk photo processing dan strip generation
- **PHP + Apache** backend untuk server gallery
- **gif.js** untuk boomerang GIF generation

**Gesture tunggal:** Open Palm ✋ → tahan 1.5 detik → countdown otomatis → capture foto

---

## 🗂️ Struktur Proyek

```
photobooth/
├── index.php               # Main app (entry point)
├── .htaccess               # Apache config
│
├── css/
│   ├── main.css            # Core styles & layout
│   ├── photobooth.css      # Camera viewport & controls
│   ├── animations.css      # Keyframes & animation utils
│   └── filters.css         # Photo filter previews & CSS filters
│
├── js/
│   ├── utils.js            # Shared utilities (EventEmitter, FPS, filters)
│   ├── webrtc.js           # WebRTC / getUserMedia manager
│   ├── gesture.js          # MediaPipe Hands gesture engine
│   ├── filters.js          # Filter state management
│   ├── strip.js            # Photo strip canvas generation
│   ├── gif-generator.js    # GIF/boomerang generation (gif.js)
│   ├── api.js              # PHP backend API client
│   ├── ui.js               # UI state, toasts, DOM helpers
│   └── app.js              # Main orchestrator / boot sequence
│
├── php/
│   ├── ping.php            # Health check endpoint
│   ├── save_photo.php      # Save strip + frames to server
│   ├── save_gif.php        # Save GIF boomerang
│   ├── get_gallery.php     # Fetch gallery items (paginated)
│   └── delete_photo.php    # Delete a gallery session
│
├── gallery/
│   └── index.php           # Gallery viewer page
│
└── uploads/                # Saved sessions (auto-created)
    └── PB_YYYYMMDD_XXXXXXXX/
        ├── strip.png
        ├── frame_0.jpg ... frame_3.jpg
        ├── boomerang.gif   (optional)
        └── meta.json
```

---

## ⚙️ Requirements

### Server
| Requirement | Version |
|-------------|---------|
| PHP         | ≥ 7.4   |
| Apache      | ≥ 2.4   |
| mod_rewrite | enabled |
| mod_headers | enabled |
| GD / Imagick| (optional, for server-side processing) |

### Browser (Client)
| Browser | Min Version |
|---------|-------------|
| Chrome  | 80+         |
| Firefox | 75+         |
| Edge    | 80+         |
| Safari  | 14+         |

> **WAJIB: HTTPS atau localhost** — WebRTC getUserMedia membutuhkan secure context.

---

## 🚀 Setup & Instalasi

### 1. Siapkan Server

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install apache2 php8.1 libapache2-mod-php8.1

# Aktifkan modul Apache
sudo a2enmod rewrite headers expires deflate
sudo systemctl restart apache2
```

### 2. Deploy Proyek

```bash
# Copy ke web root
sudo cp -r photobooth/ /var/www/html/photobooth

# Set permissions
sudo chown -R www-data:www-data /var/www/html/photobooth
sudo chmod -R 755 /var/www/html/photobooth
sudo chmod -R 777 /var/www/html/photobooth/uploads
```

### 3. Buat direktori uploads

```bash
mkdir -p /var/www/html/photobooth/uploads
chmod 777 /var/www/html/photobooth/uploads
```

### 4. Konfigurasi Apache VirtualHost (opsional)

```apache
<VirtualHost *:80>
    ServerName photobooth.local
    DocumentRoot /var/www/html/photobooth
    
    <Directory /var/www/html/photobooth>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirect HTTP → HTTPS (untuk WebRTC production)
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>
```

### 5. Akses Aplikasi

```
http://localhost/photobooth/        # Local development
https://yourdomain.com/photobooth/  # Production (HTTPS required)
```

---

## 💻 Local Development (XAMPP/WAMP)

### XAMPP
1. Copy folder `photobooth/` ke `C:\xampp\htdocs\`
2. Start Apache dari XAMPP Control Panel
3. Buka `http://localhost/photobooth/`

### PHP Built-in Server (untuk testing)
```bash
cd photobooth/
php -S localhost:8080
# Buka: http://localhost:8080
```

> ⚠️ PHP built-in server sudah mendukung `localhost` sebagai secure context untuk WebRTC.

---

## 🤚 Cara Penggunaan

### Gesture Control
1. Izinkan akses kamera saat diminta browser
2. Tunggu sistem selesai loading (loading screen)
3. **Tunjukkan Open Palm** ✋ menghadap kamera
4. Tahan selama **1.5 detik** → countdown 3 detik dimulai
5. **Tersenyum!** → Foto diambil otomatis
6. Ulangi 3 kali lagi → total **4 foto = 1 strip**
7. Download strip PNG atau generate GIF boomerang

### Manual Capture (Fallback)
- Klik tombol **Capture** (ikon kamera) di tengah bawah
- Atau tekan **Spacebar**

### Keyboard Shortcuts
| Key         | Aksi |
|-------------|------|
| `Space`     | Manual capture |
| `Ctrl+R`    | Reset sesi |
| `Esc`       | Tutup lightbox (gallery) |

---

## 🎨 Filter Foto

| Filter     | Efek |
|------------|------|
| Original   | Tanpa filter |
| Grayscale  | Hitam-putih |
| Sepia      | Efek antik coklat |
| Vintage    | Sepia + faded |
| Cool       | Tone biru sejuk |
| Warm       | Tone merah-kuning hangat |
| Neon       | Saturasi tinggi + kontras |
| Noir       | High-contrast B&W gelap |

---

## 🔧 Konfigurasi

Edit `index.php` bagian `window.PHOTOBOOTH_CONFIG`:

```javascript
window.PHOTOBOOTH_CONFIG = {
    sessionId:        'PB_YYYYMMDD_XXXXXXXX',
    uploadDir:        'uploads/',
    apiBase:          'php/',
    maxPhotos:        4,           // Jumlah foto per strip
    countdownSeconds: 3,           // Durasi countdown (detik)
    gestureHoldMs:    1500,        // Durasi tahan gesture (ms)
};
```

Edit `js/gesture.js` bagian `CONFIG`:
```javascript
const CONFIG = {
    holdDurationMs:   1500,   // Hold duration sebelum trigger
    cooldownMs:       3000,   // Jeda antar gesture
    minDetectionConf: 0.72,   // Threshold confidence MediaPipe
};
```

---

## 🐛 Troubleshooting

### Kamera tidak muncul
- Pastikan browser mendapat izin kamera (ikon kunci di address bar)
- Gunakan HTTPS atau localhost
- Coba refresh dan izinkan kembali

### Gesture tidak terdeteksi
- Pastikan pencahayaan cukup terang
- Tunjukkan telapak tangan terbuka menghadap langsung ke kamera
- Jarak optimal: 30-60 cm dari kamera
- Gunakan tombol Manual Capture sebagai fallback

### Gallery kosong / error save
- Periksa permission folder `uploads/` (harus writable: 755 atau 777)
- Cek PHP error log: `tail -f /var/log/apache2/error.log`
- Pastikan `post_max_size` dan `upload_max_filesize` cukup besar di php.ini

### GIF tidak ter-generate
- Pastikan koneksi internet (gif.js worker diload dari CDN)
- Minimal 1 foto harus ada sebelum generate GIF

---

## 🏗️ Arsitektur Teknis

```
┌──────────────────────────────────────────────────────────┐
│                    BROWSER CLIENT                        │
│                                                          │
│  ┌──────────┐    ┌───────────────┐    ┌───────────────┐ │
│  │  WebRTC  │───▶│  MediaPipe    │───▶│  App.js       │ │
│  │getUserMe │    │  Hands (WASM) │    │  Orchestrator │ │
│  │  dia()   │    │  Gesture Eng. │    │               │ │
│  └──────────┘    └───────────────┘    └───────┬───────┘ │
│       │                                       │         │
│       ▼                                       ▼         │
│  ┌──────────┐    ┌───────────────┐    ┌───────────────┐ │
│  │  Video   │───▶│  Canvas API   │───▶│  Strip.js     │ │
│  │  Stream  │    │  (Filter/Cap) │    │  4-frame strip│ │
│  └──────────┘    └───────────────┘    └───────┬───────┘ │
│                                               │         │
│                                       ┌───────▼───────┐ │
│                                       │  GIF.js       │ │
│                                       │  Boomerang    │ │
│                                       └───────┬───────┘ │
└───────────────────────────────────────────────┼─────────┘
                                                │ fetch/XHR
┌───────────────────────────────────────────────▼─────────┐
│                    PHP + APACHE SERVER                   │
│                                                          │
│  save_photo.php  ──▶  uploads/{session}/                 │
│  get_gallery.php ──▶  JSON response                      │
│  save_gif.php    ──▶  boomerang.gif                      │
│  delete_photo.php──▶  rm session dir                     │
└──────────────────────────────────────────────────────────┘
```

---

## 👨‍💻 Teknologi yang Digunakan

| Teknologi | Versi | Fungsi |
|-----------|-------|--------|
| HTML5 / CSS3 / JS (ES2020) | - | Frontend |
| WebRTC getUserMedia | W3C | Camera streaming |
| MediaPipe Hands | 0.4.x | Hand landmark detection |
| gif.js | 0.2.0 | Client-side GIF encoding |
| HTML5 Canvas API | - | Photo processing & strip |
| PHP | 7.4+ | Backend API |
| Apache | 2.4+ | Web server |

---

## 📄 Lisensi

Project ini dibuat sebagai **Final Project Jaringan Multimedia** — untuk keperluan akademik.

---

*TouchFree Photobooth v2.0.0 — WebRTC × MediaPipe Gesture Recognition*
