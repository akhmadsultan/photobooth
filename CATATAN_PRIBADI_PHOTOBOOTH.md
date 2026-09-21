# 📝 Catatan Pribadi & Panduan Operasional Photobooth Undersea

Dokumen ini berisi rangkuman teknis, konfigurasi, riwayat troubleshooting, dan panduan lengkap pengoperasian sistem **TouchFree Photobooth Undersea** untuk dokumentasi pribadi.

---

## 🚀 1. Cara Menjalankan Aplikasi (Tanpa Laragon)

Aplikasi ini menggunakan **PHP Built-in Server bawaan** sehingga tidak memerlukan Apache atau Laragon sama sekali.

### Langkah Menjalankan:
1. Buka Terminal / PowerShell di folder project:
   ```powershell
   cd photobooth
   ```
2. Jalankan server:
   ```powershell
   php -S localhost:8000
   ```
3. Buka browser di laptop Anda:
   👉 **`http://localhost:8000`**

---

## ⚙️ 2. Konfigurasi Lingkungan (`.env`)

Seluruh kredensial sensitif tersimpan aman di file `.env` (dan sudah otomatis di-exclude di `.gitignore` agar tidak bocor ke Git).

### Daftar Variabel `.env`:

| Variabel | Deskripsi | Contoh Nilai |
| :--- | :--- | :--- |
| `APP_NAME` | Nama aplikasi | `"Photobooth Undersea"` |
| `APP_URL` | URL dasar aplikasi | `"http://localhost:8000"` |
| `QR_TIMER_SECONDS` | Durasi hitung mundur popup QR | `60` |
| `GDRIVE_ENABLED` | Aktifkan upload Google Drive (`true`/`false`) | `true` |
| `GDRIVE_CLIENT_ID` | OAuth 2.0 Client ID dari Google Cloud Console | `645722380919-...apps.googleusercontent.com` |
| `GDRIVE_CLIENT_SECRET` | OAuth 2.0 Client Secret | `GOCSPX-...` |
| `GDRIVE_REFRESH_TOKEN` | Token permanen hasil otorisasi | `1//01vPZWqL...` |
| `GDRIVE_PARENT_FOLDER_ID` | ID folder utama Google Drive (wadah foto) | `1svxiGrt6M3K4DUmdYTJ1SYhDb9zov90G` |

> 💡 **Catatan untuk `GDRIVE_PARENT_FOLDER_ID`:**
> Masukkan **hanya ID folder-nya saja**, bukan seluruh link URL!
> * Contoh URL: `https://drive.google.com/drive/folders/1svxiGrt6M3K4DUmdYTJ1SYhDb9zov90G?usp=sharing`
> * ID Folder: **`1svxiGrt6M3K4DUmdYTJ1SYhDb9zov90G`**

---

## ☁️ 3. Alur Kerja Google Drive & QR Code

1. **Pengambilan Foto:**
   - Tamu mengangkat telapak tangan terbuka ✋ di depan kamera selama 1.5 detik.
   - Sistem menghitung mundur dan memotret 4 frame.
   - Sistem merender Photo Strip via Canvas dan membuat animasi GIF boomerang via `gif.js`.
2. **Proses Upload Cloud:**
   - File dikirim ke backend `php/upload_gdrive.php`.
   - Backend membuat 1 subfolder baru di dalam folder Google Drive Anda dengan format nama tanggal (misal: `2026-09-11_15-10-00`).
   - Semua 6 file diunggah ke subfolder tersebut (`strip.png`, `frame_0.jpg` s/d `frame_3.jpg`, `boomerang.gif`).
3. **Pengambilan Foto oleh Tamu:**
   - QR Code di layar **langsung mengarahkan HP tamu ke folder Google Drive** (`https://drive.google.com/drive/folders/...`).
   - Tamu bisa membuka folder tersebut menggunakan koneksi internet apa pun (kuota seluler 4G/5G atau Wi-Fi) dan langsung mengunduh foto ke galeri HP masing-masing.

---

## 🔧 4. Riwayat Masalah & Solusi Teknis (Troubleshooting Log)

Berikut rangkuman solusi penting yang telah diterapkan pada sistem ini agar tidak terulang di masa depan:

### A. Error SSL di Windows PHP (`unable to get local issuer certificate`)
* **Penyebab:** PHP bawaan di Windows tidak menyertakan root CA bundle untuk koneksi HTTPS keluar.
* **Solusi:** File sertifikat `cacert.pem` diunduh ke `extras/ssl/cacert.pem` dan didaftarkan pada `php.ini` (`curl.cainfo` dan `openssl.cafile`).

### B. Error Kebijakan Google Cloud (`Service account key creation is disabled`)
* **Penyebab:** Aturan *Secure by Default* Google Cloud 2024 memblokir pembuatan Service Account JSON key.
* **Solusi:** Beralih ke **OAuth 2.0 Web Client (Client ID & Secret)** melalui script `php/authorize.php`. Metode ini tidak terkena blokir kebijakan organisasi dan mengalirkan foto langsung ke akun Google pribadi.

### C. Error 403 `access_denied` saat Login Otorisasi Google
* **Penyebab:** Aplikasi berstatus "Testing" di Google Auth Platform, sehingga akun selain penguji ditolak.
* **Solusi:** Daftarkan alamat Gmail Anda ke daftar **Test Users** di Google Cloud Console $\rightarrow$ **Audience**.

### D. Error 404 Google Drive saat Mengunggah Foto
* **Penyebab:** `parent_folder_id` diisi seluruh link URL browser (`https://drive.google.com/...`).
* **Solusi:** Sistem diubah untuk membersihkan URL secara otomatis menggunakan regex `preg_match('/folders\/([a-zA-Z0-9_\-]+)/', ...)` sehingga hanya ID bersih yang dikirim ke Google API.

### E. Error `Maximum execution time of 30 seconds exceeded`
* **Penyebab:** Upload 6 file ke Google Drive melebihi batas default PHP (30 detik), dan ada panggilan izin publik per file yang berulang.
* **Solusi:** 
  1. Menambahkan `@set_time_limit(180);` di `upload_gdrive.php`.
  2. Menghapus panggilan izin publik individual per file (karena folder utama sudah publik, seluruh file di dalamnya otomatis mewarisi izin publik). Waktu upload terpangkas hingga 50%.

### F. Error `localhost:8000` saat Scan QR di HP Tamu
* **Penyebab:** QR Code sebelumnya membuat link internal `http://localhost:8000/share.php?sid=...`, yang tidak bisa diakses oleh HP tamu.
* **Solusi:** Di `js/v2-workflow.js`, link QR Code diubah agar **100% langsung mengambil link folder Google Drive** (`https://drive.google.com/drive/folders/...`), sehingga tamu di mana pun bisa membukanya.

---

## 🔄 5. Cara Menghubungkan Akun Google Baru (Jika Berganti Akun)

Jika suatu saat Anda ingin memindahkan folder foto ke akun Google lain:
1. Buka Google Cloud Console di akun baru tersebut.
2. Buat OAuth Client ID (Web Application) dengan redirect URI:
   `http://localhost:8000/php/authorize.php`
3. Tambahkan email akun tersebut ke menu **Audience $\rightarrow$ Test Users**.
4. Buka di browser:
   👉 **`http://localhost:8000/php/authorize.php`**
5. Masukkan Client ID & Client Secret baru $\rightarrow$ klik **Hubungkan Akun Google Drive**.
6. Perbarui `GDRIVE_PARENT_FOLDER_ID` di file `.env` dengan ID folder di Google Drive yang baru.

---

## 📋 6. Checklist Operasional Hari-H Event

- [ ] Nyalakan laptop dan sambungkan ke adaptor charger.
- [ ] Pastikan laptop terhubung ke jaringan internet yang stabil (Wi-Fi venue atau Tethering HP).
- [ ] Buka terminal dan jalankan `php -S localhost:8000`.
- [ ] Buka browser di `http://localhost:8000` dan tekan **F11** untuk mode layar penuh (*Fullscreen*).
- [ ] Lakukan 1 kali tes foto gesture ✋ untuk memastikan upload ke Google Drive berjalan mulus dan QR Code bisa di-scan.
- [ ] Sistem siap digunakan oleh para tamu! 🎉

---

## 📂 7. Struktur & Penjelasan Fungsi Seluruh File Project

Berikut adalah kamus lengkap seluruh file yang ada di dalam project beserta fungsi dan perannya dalam sistem:

### 🏠 A. File Utama di Root Folder

| Nama File | Fungsi & Deskripsi |
| :--- | :--- |
| `index.php` | **Halaman Utama Photobooth**. Menampilkan viewfinder kamera WebRTC, overlay gesture AI, hitung mundur (countdown), filter visual, panel kontrol stiker, dan modal pop-up QR Code. |
| `share.php` | **Halaman Unduh Foto Khusus Mobile/Tamu**. Halaman responsif estetis bertema laut tempat tamu melihat Photo Strip, 4 foto individual, dan animasi GIF serta tombol download masing-masing file. |
| `config.php` | **Pusat Konfigurasi Sistem**. Membaca file `.env` secara otomatis dan mendistribusikan konfigurasi aplikasi serta Google Drive API ke seluruh skrip backend. |
| `.env` | **Variabel Lingkungan Rahasia**. Menyimpan kredensial OAuth 2.0 Google Drive (Client ID, Client Secret, Refresh Token, dan Folder ID) secara lokal agar tidak terekspos. |
| `.env.example` | **Template Kredensial**. Salinan bersih file konfigurasi tanpa data rahasia untuk dokumentasi atau deployment ke komputer lain. |
| `.htaccess` | **Konfigurasi Server Apache**. Mengatur header keamanan, kompresi gzip, izin CORS, dan pembatasan akses ke folder sensitif jika dijalankan di Apache/cPanel. |
| `.gitignore` | **Daftar Pengecualian Git**. Memastikan file rahasia (`.env`, kredensial JSON) dan folder foto lokal (`uploads/`) tidak ikut ter-upload ke repositori Git publik. |
| `favicon.ico` | **Ikon Browser**. Ikon tab browser bertema photobooth. |

---

### ⚙️ B. Folder `php/` (Backend & Integrasi API)

| Nama File | Fungsi & Deskripsi |
| :--- | :--- |
| `php/upload_gdrive.php` | **Backend Uploader Google Drive**. Mengambil seluruh file sesi dari folder lokal laptop, membuat subfolder baru di Google Drive, mengunggah semua foto & GIF, lalu mengembalikan link publik Google Drive. |
| `php/authorize.php` | **Skrip Otorisasi OAuth 2.0**. Antarmuka web praktis untuk menghubungkan akun Google pribadi, menangani pertukaran kode otentikasi Google, dan menyimpan `refresh_token` otomatis. |
| `php/gdrive_helper.php` | **Pustaka Helper API Google Drive**. Berisi kumpulan fungsi murni PHP (tanpa library vendor luar) untuk melakukan request HTTP cURL ke Google Drive API v3 (token refresh, create folder, multipart upload, share permission). |
| `php/save_photo.php` | **Penyimpan Foto Lokal**. Menerima data Base64 photo strip dan frame foto dari frontend, membuat folder unik `uploads/PB_YYYYMMDD_XXXXXX`, dan menyimpannya sebagai file gambar serta `meta.json`. |
| `php/save_gif.php` | **Penyimpan Animasi GIF**. Menerima binary Blob GIF boomerang dari frontend dan menyimpannya sebagai `boomerang.gif` di dalam folder sesi terkait. |
| `php/test_gdrive.php` | **Diagnostik Koneksi Google Drive**. Skrip pengujian mandiri untuk memastikan token Google aktif, folder dapat dibuat, dan file uji coba 1x1 pixel berhasil ter-upload. |
| `php/get_gallery.php` | **API Galeri Foto**. Membaca seluruh sesi foto yang tersimpan di folder `uploads/` dan menyajikannya dalam format JSON berhalaman (*paginated*) untuk halaman galeri. |
| `php/delete_photo.php` | **Penghapus Sesi Foto**. Endpoint untuk menghapus folder sesi tertentu dari disk laptop jika diperlukan pembersihan data. |
| `php/session_info.php` | **Info Sesi Spesifik**. Mengambil metadata sesi tunggal (`meta.json`) berdasarkan `session_id` yang diminta. |
| `php/ping.php` | **Health Check**. Endpoint ringan yang mengembalikan status server `{ "status": "ok" }` untuk mendeteksi apakah backend PHP sedang aktif. |

---

### 🎨 C. Folder `js/` (Logika Frontend & AI Engine)

| Nama File | Fungsi & Deskripsi |
| :--- | :--- |
| `js/v2-app.js` / `js/app.js` | **Orkestrator Utama Frontend**. Mengatur inisialisasi awal (*booting*), event listener tombol kamera, transisi status aplikasi, dan audio efek suara (SFX). |
| `js/v2-workflow.js` | **Pipeline Otomatis Pasca-Foto**. Mengatur alur setelah 4 foto selesai: merender strip $\rightarrow$ membuat GIF $\rightarrow$ memanggil upload Google Drive $\rightarrow$ men-generate QR Code Google Drive $\rightarrow$ menampilkan popup QR. |
| `js/v2-gesture.js` / `js/gesture.js` | **AI Hand Gesture Engine (Palm Up Only)**. Menggunakan **MediaPipe Hands** untuk melacak orientasi telapak tangan secara real-time, dikhususkan hanya untuk gesture **Palm Up ✋** (telapak tangan tegak ke atas menghadap kamera) untuk memicu hitung mundur foto otomatis tanpa gesture lain yang mengganggu. |
| `js/v2-strip.js` / `js/strip.js` | **Mesin Perender Photo Strip (HTML5 Canvas)**. Menggabungkan 4 frame foto ke dalam format strip vertikal, menambahkan bingkai/frame tema Undersea, tanggal/waktu, watermark logo, dan stiker. |
| `js/v2-gif.js` / `js/gif-generator.js` | **Generator GIF Boomerang**. Mengolah frame-frame foto berurutan maju-mundur (looping boomerang) menggunakan library `gif.js` menjadi file animasi `.gif`. |
| `js/v2-filters.js` / `js/filters.js` | **Filter Visual Foto**. Mengatur pemilihan filter warna (Normal, Vintage, B&W, Warm, Cool, Undersea Glow, dsb) baik pada live video maupun hasil akhir foto. |
| `js/v2-stickers.js` | **Stiker Interaktif**. Mengelola penambahan stiker bertema bawah laut (ikan, karang, gelembung) pada kanvas foto strip. |
| `js/v2-api.js` / `js/api.js` | **Klien HTTP API Frontend**. Fungsi wrapper `fetch()` untuk mengirimkan data strip, GIF, dan memicu proses upload ke skrip-skrip PHP. |
| `js/v2-ui.js` / `js/ui.js` | **Manajemen Tampilan & DOM**. Mengontrol animasi countdown ring, toast pemberitahuan, efek flash kamera, dan pop-up modal. |
| `js/webrtc.js` | **WebRTC Camera Streamer**. Menghubungkan webcam laptop via `navigator.mediaDevices.getUserMedia`, mengatur resolusi video kamera HD, dan rotasi/mirroring kamera. |
| `js/utils.js` | **Pustaka Pembantu (Utility)**. Event emitter pub/sub, penghitung FPS real-time, dan fungsi manipulasi string/waktu. |

---

### 🎨 D. Folder `css/` (Desain & Tampilan Visual)

| Nama File | Fungsi & Deskripsi |
| :--- | :--- |
| `css/main.css` | **Desain Sistem Global**. Variabel warna tema laut (*teal, navy, cyan*), font Google Fonts, reset layout, dan styling komponen dasar. |
| `css/v2.css` / `css/photobooth.css` | **Styling Viewport Photobooth**. Tata letak bingkai kamera, indikator HUD deteksi tangan, lingkaran hitung mundur animasi, dan modal pop-up QR Code. |
| `css/animations.css` | **Efek Gerak & Animasi**. Animasi gelembung laut mengambang (*floating bubbles*), efek kedip lampu kilat (*camera flash*), dan denyut cahaya neon (*pulse glow*). |
| `css/filters.css` | **CSS Photo Filters**. Definisi CSS filter visual real-time (`grayscale`, `sepia`, `contrast`, `hue-rotate`, `saturate`) untuk preview kamera. |

---

### 🖼️ E. Folder `gallery/` & `uploads/`

| Folder / File | Fungsi & Deskripsi |
| :--- | :--- |
| `gallery/index.php` | **Halaman Galeri Koleksi**. Halaman khusus untuk operator/penyelenggara melihat katalog seluruh sesi foto tamu yang telah tersimpan. |
| `uploads/` | **Penyimpanan Foto Lokal**. Folder otomatis yang menampung subfolder per sesi (`PB_...`) yang berisi `strip.png`, `frame_0.jpg` s/d `frame_3.jpg`, `boomerang.gif`, dan metadata JSON lokal. |
