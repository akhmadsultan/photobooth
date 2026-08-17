# 📖 Panduan Setup & Konfigurasi Sistem Photobooth

Panduan ini berisi langkah-langkah lengkap untuk mengkonfigurasi **Google Drive API** dan **Supabase (Video Mapping)** agar sistem Photobooth dapat berjalan secara otomatis di lokasi acara.

---

## 🚀 1. Mode Simulasi (Tanpa Setup / Testing Mode)

Sistem sudah dirancang agar dapat **langsung dites secara lokal tanpa API key apapun**.
- Jika API key Google Drive / Supabase belum diisi, sistem akan otomatis menggunakan **URL lokal** (`http://localhost/photobooth/uploads/...`) dan mode simulasi.
- Seluruh UI, QR Code, Timer, dan Halaman Share (`share.php`) tetap berfungsi 100%.

---

## ☁️ 2. Panduan Setup Google Drive API

Untuk mengunggah hasil foto secara otomatis ke Google Drive, disarankan menggunakan **Service Account** (paling praktis untuk Photobooth di lokasi acara karena tidak memerlukan login browser manual).

### Langkah 1: Buat Project di Google Cloud Console
1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Buat Project Baru (misal: `Photobooth-App`).
3. Di bilah pencarian atas, cari **Google Drive API** lalu klik **Enable** (Aktifkan).

### Langkah 2: Buat Service Account & Kunci JSON
1. Masuk ke menu **IAM & Admin** $\rightarrow$ **Service Accounts**.
2. Klik **+ Create Service Account**.
3. Isi nama service account (misal: `photobooth-uploader`), lalu klik **Create and Continue** $\rightarrow$ **Done**.
4. Klik pada email Service Account yang baru dibuat.
5. Masuk ke tab **Keys** $\rightarrow$ **Add Key** $\rightarrow$ **Create new key** $\rightarrow$ Pilih **JSON**.
6. File `.json` akan terunduh. Ubah nama file tersebut menjadi `gdrive_service_account.json` dan **simpan di folder utama photobooth** (`d:/softwarekerja/laragon/www/photobooth/gdrive_service_account.json`).

### Langkah 3: Buat Folder di Google Drive & Beri Akses
1. Buka Google Drive Anda, buat folder baru bernama **Photobooth**.
2. Klik kanan folder **Photobooth** $\rightarrow$ **Share** (Bagikan).
3. Masukkan email Service Account (contoh: `photobooth-uploader@project-id.iam.gserviceaccount.com`).
4. Beri akses sebagai **Editor**, lalu simpan.
5. Buka folder **Photobooth** tersebut, perhatikan URL di browser:
   `https://drive.google.com/drive/folders/1a2b3c4d5e6f7g8h9i...`
   Salin Kode ID yang ada di akhir URL (misal: `1a2b3c4d5e6f7g8h9i`).

### Langkah 4: Edit `config.php`
Buka file `config.php` di project photobooth dan ubah bagian `gdrive`:

```php
'gdrive' => [
    'enabled'              => true, // Ubah ke true
    'client_id'            => '',
    'client_secret'        => '',
    'refresh_token'        => '',
    'service_account_json' => __DIR__ . '/gdrive_service_account.json',
    'parent_folder_id'     => 'MASUKKAN_FOLDER_ID_DISINI',
],
```

---

## ⚡ 3. Panduan Setup Supabase (Video Mapping)

Gunakan kredensial dari project Supabase yang **sudah dibuat oleh tim Video Mapping**.

### Langkah 1: Ambil URL & Key dari Supabase Dashboard
1. Minta tim Video Mapping membuka Dashboard Supabase project mereka.
2. Masuk ke **Project Settings** $\rightarrow$ **API**.
3. Salin **Project URL** (misal: `https://xyzabc.supabase.co`).
4. Salin **anon public key** atau **service_role key**.

### Langkah 2: Pastikan Tabel Database Tersedia
Pastikan tabel di Supabase (misal bernama `video_mapping`) memiliki kolom-kolom berikut:

| Nama Kolom | Tipe Data | Keterangan |
|------------|-----------|------------|
| `id` | int8 / uuid | Primary key (Auto increments) |
| `photo_strip_url` | text | Link URL Photo Strip |
| `photo1_url` | text | Link URL Foto 1 |
| `photo2_url` | text | Link URL Foto 2 |
| `photo3_url` | text | Link URL Foto 3 |
| `photo4_url` | text | Link URL Foto 4 |
| `gif_url` | text | Link URL GIF Animasi |
| `google_drive_folder_url` | text | Link Folder Google Drive |
| `created_at` | timestamptz | Tanggal & Waktu Pembuatan |
| `source` | text | Nilai default: `"photobooth"` |
| `status` | text | Nilai default: `"pending"` |

> 💡 *Jika tabel sudah ada di Supabase tim Video Mapping dengan nama lain (misal: `photos_queue`), cukup sesuaikan nama tabel di `config.php`.*

### Langkah 3: Edit `config.php`
Buka `config.php` dan perbarui bagian `supabase`:

```php
'supabase' => [
    'url'   => 'https://xyzabc.supabase.co', // Masukkan URL Project Supabase
    'key'   => 'eyJhbGciOiJKV1QiLCJhbGci...',  // Masukkan API Key
    'table' => 'video_mapping'                // Nama tabel database
]
```

---

## 🌐 4. Pengaturan IP / Domain untuk Akses HP (Scan QR)

Agar pengunjung dapat mengakses halaman hasil foto via Scan QR melalui **smartphone (Wifi Lokal Event)**:

1. Pastikan laptop/PC Photobooth dan HP pengunjung terhubung ke **jaringan Wi-Fi yang sama**.
2. Cek IP Laptop Anda di CMD/Terminal (`ipconfig`), misal: `192.168.1.100`.
3. Buka `config.php` dan sesuaikan `app_url`:

```php
'app_url' => 'http://192.168.1.100/photobooth',
```

4. Pengunjung yang melakukan scan QR akan otomatis diarahkan ke link IP lokal `http://192.168.1.100/photobooth/share.php?sid=...` dan dapat langsung mengunduh foto di HP mereka.

---

## ⏱️ 5. Pengaturan Timer Countdown

Jika ingin mengubah durasi tampilan layar QR Code sebelum otomatis kembali ke halaman utama:
Buka `config.php` dan ubah `qr_timer_seconds`:

```php
'qr_timer_seconds' => 60, // Ubah ke detik yang diinginkan (misal 45 atau 90)
```

---

## ❓ FAQ & Penanganan Kendala

- **Q: Apakah foto hilang jika Wi-Fi terputus saat sesi selesai?**  
  *A: Tidak. Foto tetap tersimpan aman di server lokal (`uploads/`). Sistem akan otomatis mencoba mengunggah ulang saat Wi-Fi kembali terhubung.*

- **Q: Tombol "Kirim ke Video Mapping" menampilkan status gagal?**  
  *A: Tekan tombol **"Coba Lagi"**. Sistem akan mengirim ulang data ke Supabase tanpa perlu mengunggah ulang file ke Google Drive.*
