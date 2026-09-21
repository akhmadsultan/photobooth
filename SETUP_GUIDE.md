# 📖 Panduan Setup & Konfigurasi Sistem Photobooth

Panduan ini berisi langkah-langkah lengkap untuk mengkonfigurasi **Google Drive API** agar sistem Photobooth dapat berjalan secara otomatis mengunggah foto ke Google Drive dan menghasilkan QR Code langsung ke folder Drive.

---

## 🚀 1. Mode Simulasi (Tanpa Setup / Testing Mode)

Sistem sudah dirancang agar dapat **langsung dites secara lokal tanpa API key apapun**.
- Jika kredensial Google Drive belum diisi, sistem akan otomatis menggunakan **URL lokal** (`uploads/...`) dan mode simulasi.
- Seluruh UI, QR Code, dan kamera tetap berfungsi 100%.

---

## ☁️ 2. Panduan Setup Google Drive API (OAuth 2.0)

Untuk mengunggah hasil foto secara otomatis ke Google Drive:

### Langkah 1: Buat OAuth Client ID di Google Cloud Console
1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Buat Project Baru atau pilih project yang ada.
3. Masuk ke **APIs & Services** $\rightarrow$ **Library**, cari **Google Drive API** lalu klik **Enable** (Aktifkan).
4. Masuk ke **APIs & Services** $\rightarrow$ **OAuth consent screen**:
   - Pilih **External**.
   - Masukkan App name dan Email.
   - Tambahkan scope `https://www.googleapis.com/auth/drive`.
   - **PENTING**: Pada bagian **Test users**, tambahkan email Google yang akan digunakan untuk login.
5. Masuk ke **APIs & Services** $\rightarrow$ **Credentials**:
   - Klik **Create Credentials** $\rightarrow$ **OAuth client ID**.
   - Application type: **Web application**.
   - Authorized redirect URIs: `http://localhost:8000/php/authorize.php`.
   - Salin **Client ID** dan **Client Secret**.

### Langkah 2: Buat Folder di Google Drive
1. Buka Google Drive Anda, buat folder baru bernama **Photobooth Undersea**.
2. Klik kanan folder $\rightarrow$ **Share** $\rightarrow$ General access: ubah ke **Anyone with the link** (Viewer).
3. Buka folder tersebut dan salin Folder ID dari URL (kode acak setelah `/folders/`).

### Langkah 3: Konfigurasi File `.env`
Buka file `.env` di folder project dan isi:
```ini
GDRIVE_CLIENT_ID=client_id_anda
GDRIVE_CLIENT_SECRET=client_secret_anda
GDRIVE_PARENT_FOLDER_ID=folder_id_anda
```

### Langkah 4: Otorisasi Akun Google
1. Jalankan server lokal: `php -S localhost:8000`.
2. Buka browser: `http://localhost:8000/php/authorize.php`.
3. Klik tombol **Hubungkan Google Drive**, login dengan akun Google Anda, dan izinkan akses.
4. Refresh token akan tersimpan otomatis.
5. Uji upload mandiri melalui: `http://localhost:8000/php/test_gdrive.php`.

---

## ⏱️ 3. Pengaturan Timer & Sesi

Setelah foto dan video strip selesai diproses, sistem akan menampilkan pop-up QR Code langsung ke link folder Google Drive. Tamu cukup memindai QR Code di layar atau menekan tombol tutup (&times;) untuk memulai sesi foto baru.

---

## ❓ FAQ & Penanganan Kendala

- **Q: Apakah foto hilang jika Wi-Fi terputus saat sesi selesai?**  
  *A: Tidak. Foto tetap tersimpan aman di server lokal laptop (`uploads/`). Sistem akan otomatis mencoba mengunggah ulang saat koneksi internet kembali stabil.*

- **Q: Bagaimana cara memulai sesi foto tanpa menyentuh layar?**  
  *A: Arahkan telapak tangan tegak menghadap kamera (**Palm Up ✋**). Sistem AI MediaPipe akan mendeteksi gesture dan memulai hitung mundur 3 detik secara otomatis.*
