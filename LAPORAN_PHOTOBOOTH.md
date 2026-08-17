# 📸 Laporan Projek — Aplikasi Photobooth Undersea

## 1. Pendahuluan

**Photobooth Undersea** adalah aplikasi web photobooth bertema bawah laut (_undersea_) yang berjalan langsung di browser. Aplikasi ini memanfaatkan teknologi **WebRTC** untuk mengakses kamera perangkat dan **MediaPipe Hand Gesture Recognition** untuk mendeteksi gerakan tangan sehingga pengguna dapat mengoperasikan seluruh aplikasi **tanpa menyentuh layar** (touchless/gesture-based). Hasil akhir berupa **photo strip** (4 foto dalam 1 frame) yang dapat diunduh sebagai PNG maupun di-ekspor menjadi **Boomerang GIF**.

---

## 2. Daftar Fitur Aplikasi

### 2.1 Start Page dengan Video Background

- Halaman pembuka menampilkan video latar bertema bawah laut yang diputar secara otomatis (autoplay, loop, muted).
- Terdapat logo **Photobooth Undersea** dan tombol **Start** untuk memulai sesi.

### 2.2 Loading Screen

- Setelah menekan Start, muncul loading screen dengan progress bar yang menunjukkan proses inisialisasi:
  - Memeriksa dukungan kamera
  - Memuat frame preset bawah laut
  - Menyalakan kamera
  - Memuat model gesture recognition
  - Mengecek koneksi server

### 2.3 Live Camera Preview (WebRTC)

- Menggunakan `getUserMedia` untuk mengakses webcam pengguna.
- Video ditampilkan secara live di area tengah aplikasi.
- Terdapat penanganan error yang informatif dalam Bahasa Indonesia:
  - Izin kamera ditolak
  - Kamera tidak ditemukan
  - Kamera sedang digunakan aplikasi lain
  - Masalah protokol keamanan (HTTP vs HTTPS)

### 2.4 Gesture Recognition (Navigasi Tanpa Sentuh)

Menggunakan **MediaPipe Hands** untuk mendeteksi gerakan tangan secara real-time:

| Gesture                             | Ikon | Fungsi                                                          |
| ----------------------------------- | ---- | --------------------------------------------------------------- |
| **Open Palm** (telapak terbuka)     | 🖐   | Tahan 1 detik → otomatis memulai countdown dan mengambil foto   |
| **Point** (telunjuk menunjuk)       | ☝    | Arahkan ke ikon stiker di shelf → stiker muncul di layar kamera |
| **Pinch** (jempol + telunjuk rapat) | 🤏   | Grab dan geser stiker (pindahkan posisi), atau resize di sudut  |
| **V-Sign** (telunjuk + tengah)      | ✌    | Tahan 1 detik di atas stiker → hapus stiker                     |

- Indikator gesture berupa **ring progress** SVG yang menampilkan status deteksi secara visual.
- **Global Touchless Cursor**: kursor virtual mengikuti ujung jari telunjuk di layar, memungkinkan interaksi dengan semua tombol dan elemen UI tanpa sentuhan.
- Gesture dapat di-toggle on/off melalui tombol dedicated.

### 2.5 Pilihan Frame (Bingkai Foto)

Tersedia **7 pilihan frame** yang dapat dipilih di panel kiri:

| Frame                     | Deskripsi                                        |
| ------------------------- | ------------------------------------------------ |
| **Undersea 1** (`frame1`) | Frame preset PNG bertema bawah laut #1           |
| **Undersea 2** (`frame2`) | Frame preset PNG bertema bawah laut #2           |
| **Undersea 3** (`frame3`) | Frame preset PNG bertema bawah laut #3           |
| **Upload**                | Unggah frame custom dari Canva/Figma/Illustrator |

Selain itu, terdapat frame built-in tambahan (checker, film, stamp, oval, minimal) yang dirender via Canvas.

### 2.6 Upload Frame Custom

Fitur untuk mengunggah frame bingkai hasil desain sendiri:

- **Download Guide Template**: Tombol untuk mengunduh file template berukuran **420×1322 px** sebagai panduan penempatan 4 slot foto, header, dan footer.
- **Drag & Drop / File Browse**: Area dropzone untuk mengunggah file PNG transparan atau JPG.
- **Drawing Mode**:
  - _Overlay_: Frame PNG transparan ditimpa di atas foto.
  - _Background_: Frame dijadikan latar belakang di belakang foto.
- **Background Color Picker**: Pemilih warna latar jika menggunakan mode overlay.

### 2.7 Filter Foto

Tersedia **6 pilihan filter** yang diterapkan secara live pada preview kamera:

| Filter      | Efek CSS                                 |
| ----------- | ---------------------------------------- |
| **Natural** | Tanpa filter (default)                   |
| **B&W**     | Hitam putih dengan kontras tinggi        |
| **Sepia**   | Efek sepia klasik                        |
| **Vintage** | Sepia ringan + saturasi rendah + kontras |
| **Fade**    | Warna pudar lembut                       |
| **Warm**    | Tone hangat kekuningan                   |

### 2.8 Stiker Interaktif

Tersedia **6 stiker** bertema bawah laut yang bisa ditambahkan ke foto:

| Stiker     | Tipe                          |
| ---------- | ----------------------------- |
| 👑 Crown   | SVG (dibuat langsung di kode) |
| 🐟 Fish    | Gambar PNG                    |
| 🫧 Bubble  | Gambar PNG                    |
| 🌿 Seaweed | Gambar PNG                    |
| 🐚 Shell   | Gambar PNG                    |
| 🦑 Squid   | Gambar PNG                    |

- Stiker ditampilkan di **Sticker Shelf** (rak ikon) di bagian atas area kamera.
- Stiker bisa ditambahkan via klik/tap **atau** gesture tangan (point & dwell).
- Stiker bisa dipindahkan (drag) dan di-resize via mouse **atau** gesture pinch.
- Stiker bisa dihapus via tombol × **atau** gesture V-sign (hold 1 detik).
- Saat foto diambil, stiker otomatis "dibakar" (_burned_) ke dalam gambar hasil capture.
- Tombol **Clear stickers** tersedia untuk menghapus semua stiker sekaligus.

### 2.9 Capture Foto (Pengambilan Foto)

- Maksimal **4 foto** per sesi untuk membentuk 1 strip.
- Dua cara mengambil foto:
  1. **Tekan tombol Capture** (bulat besar di tengah bawah) atau tekan **Spasi**.
  2. **Gesture open palm** — tahan telapak tangan terbuka selama 1 detik.
- Setelah trigger, muncul **countdown 3-2-1** dengan animasi dan efek suara.
- Setelah countdown selesai, terjadi **efek flash** (kilatan putih) dan suara shutter.
- Badge counter menampilkan progres: `1/4`, `2/4`, `3/4`, `4/4`.

### 2.10 Photo Strip Output

- Foto-foto dirender ke dalam **Canvas strip** berukuran **420×1322 px**.
- Strip menampilkan: Header ("PHOTOBOOTH UNDERSEA"), 4 foto, Session ID, tanggal, dan footer.
- Strip ditampilkan secara real-time di panel kanan setiap kali foto baru diambil.

### 2.11 Aksi Setelah Strip Selesai

Setelah 4 foto lengkap, muncul 4 tombol aksi:

| Tombol           | Fungsi                                                          |
| ---------------- | --------------------------------------------------------------- |
| **Download PNG** | Mengunduh photo strip sebagai file PNG ke komputer              |
| **Create GIF**   | Membuat animasi Boomerang GIF dari 4 foto (encoded client-side) |
| **Save Gallery** | Menyimpan strip ke server dan masuk ke halaman Gallery          |
| **New Session**  | Memulai sesi baru (reset semua foto)                            |

### 2.12 Boomerang GIF

- GIF di-encode **sepenuhnya di browser** (client-side) menggunakan library `omggif.js`.
- Urutan animasi: foto 1→2→3→4→3→2 (boomerang/ping-pong).
- Resolusi GIF: 320×240 px, delay 0.25 detik per frame.
- Progress encoding ditampilkan dalam persentase.
- GIF bisa diunduh dan disimpan ke server.

### 2.13 Gallery (Halaman Galeri)

- Halaman terpisah (`gallery/index.php`) menampilkan semua foto yang telah disimpan.
- Foto ditampilkan dalam **grid responsif** dengan kartu glassmorphism.
- Setiap kartu menampilkan: thumbnail strip, Session ID, tanggal, tag (jumlah frame, filter, frame style).
- Aksi per kartu:
  - **DL** (Download): Unduh strip PNG.
  - **Del** (Hapus): Hapus foto dari server (dengan dialog konfirmasi custom bertema undersea).
- **Lightbox**: Klik thumbnail untuk melihat strip ukuran penuh.
- **Paginasi**: Navigasi halaman jika foto banyak (20 per halaman).
- **Touchless navigation**: Gesture recognition juga aktif di halaman galeri.

### 2.14 Session Management

- Setiap sesi mendapatkan **Session ID unik** (format: `PB_YYMMDD_XXXXXX`).
- Session ID ditampilkan di topbar dan diembed ke dalam strip.
- Tombol **Reset** (ikon rotate) menghapus semua foto sesi saat ini tanpa reload.

### 2.15 Audio & Sound Effects

- Efek suara untuk setiap interaksi: klik tombol, countdown tick, shutter, success, error, grab stiker, drop stiker, spawn stiker, hapus stiker.

### 2.16 UI/UX Premium

- **Tema bawah laut**: Gradien ocean, bubble animations, glassmorphism, wavy dividers.
- **Typography**: Google Fonts — Syne, DM Mono, Nunito, Bubblegum Sans.
- **Animasi**: Bubbles float, hover effects, card float-up, countdown pop, stiker spawn/pop.
- **Responsive**: Layout 3 kolom (panel kiri, kamera tengah, strip kanan).
- **Toast notifications**: Pesan sukses/error/info muncul di sudut layar.

---

## 3. Cara Penggunaan Aplikasi (Step by Step)

### Langkah 1 — Membuka Aplikasi

1. Pastikan server lokal (Laragon) sudah berjalan.
2. Buka browser (Chrome/Edge/Firefox) dan kunjungi:
   ```
   http://localhost/photobooth/
   ```
3. Halaman **Start Page** muncul dengan video latar bawah laut dan tombol **Start**.

### Langkah 2 — Memulai Sesi

1. Klik tombol **▶ Start**.
2. **Loading screen** muncul, menunggu proses inisialisasi selesai.
3. Saat browser meminta **izin kamera**, klik **Allow** (Izinkan).
4. Setelah loading selesai, tampilan utama aplikasi muncul dengan 3 panel:
   - **Kiri**: Pilihan Frame, Filter, dan indikator Gesture.
   - **Tengah**: Live camera preview dan tombol capture.
   - **Kanan**: Strip output (awalnya kosong, placeholder 4 slot).

### Langkah 3 — Memilih Frame

1. Di panel kiri bagian **Frame**, pilih salah satu bingkai:
   - Klik **Undersea 1**, **Undersea 2**, atau **Undersea 3** untuk frame preset.
   - Klik **Upload** jika ingin menggunakan frame custom.
2. Jika memilih **Upload**:
   - Klik **Download Guide Template** untuk mendapatkan template ukuran yang benar (420×1322 px).
   - Edit template di Canva/Figma/Illustrator.
   - Seret file hasil desain ke area dropzone, atau klik untuk browse.
   - Pilih **Drawing Mode**: Overlay (PNG transparan) atau Background (JPG/PNG solid).

### Langkah 4 — Memilih Filter (Opsional)

1. Di panel kiri bagian **Filter**, klik salah satu pilihan.
2. Filter langsung diterapkan ke preview kamera secara live.
3. Pilihan: Natural, B&W, Sepia, Vintage, Fade, Warm.

### Langkah 5 — Menambahkan Stiker (Opsional)

1. Di bagian atas area kamera, terdapat **Sticker Shelf** dengan 6 ikon stiker.
2. **Klik** ikon stiker untuk menambahkannya ke foto, **atau** gunakan gesture **Point** (arahkan jari telunjuk ke ikon, tahan ~0.65 detik).
3. Stiker muncul di tengah area kamera.
4. **Geser** stiker dengan mouse drag **atau** gesture **Pinch** (cubit dan geser).
5. **Resize** stiker dengan drag handle di sudut kanan bawah **atau** gesture Pinch di area resize.
6. **Hapus** stiker dengan klik tombol **×** **atau** gesture **V-Sign** (tahan ✌ di atas stiker selama 1 detik).
7. Klik **Clear stickers** untuk menghapus semua stiker sekaligus.

### Langkah 6 — Mengambil Foto

1. Posisikan diri di depan kamera.
2. Ambil foto dengan salah satu cara:
   - **Klik tombol Capture** (bulat besar di bawah kamera).
   - **Tekan tombol Spasi** di keyboard.
   - **Gesture Open Palm** — buka telapak tangan, tahan 1 detik hingga ring progress terisi penuh.
3. Countdown **3 → 2 → 1** muncul di layar dengan efek suara.
4. **Flash** menyala dan foto diambil.
5. Foto langsung muncul di strip output panel kanan.
6. Badge counter berubah: `1/4` → `2/4` → `3/4` → `4/4`.
7. Ulangi hingga 4 foto terambil.

### Langkah 7 — Melihat dan Menyimpan Hasil

Setelah 4 foto lengkap, strip foto selesai dan tombol aksi muncul:

1. **Download PNG**: Klik untuk mengunduh strip foto sebagai file PNG ke komputer.
2. **Create GIF**: Klik untuk membuat animasi Boomerang GIF dari 4 foto tersebut.
   - Tunggu proses encoding (progress ditampilkan).
   - Setelah selesai, preview GIF muncul.
   - Klik **Download GIF** untuk mengunduh.
3. **Save Gallery**: Klik untuk menyimpan strip ke server.
   - Foto tersimpan dan bisa dilihat di halaman Gallery.
4. **New Session**: Klik untuk memulai sesi baru (foto sebelumnya direset).

### Langkah 8 — Mengakses Gallery

1. Klik link **Gallery** di topbar kanan atas.
2. Halaman gallery menampilkan semua strip yang pernah disimpan.
3. Setiap kartu menampilkan:
   - Thumbnail strip foto.
   - Session ID dan tanggal pengambilan.
   - Tag: jumlah frame, filter yang digunakan, frame style.
4. **Download**: Klik tombol **DL** pada kartu untuk mengunduh strip.
5. **Lihat Full-Size**: Klik thumbnail untuk membuka lightbox.
6. **Hapus**: Klik tombol **Del**, konfirmasi di dialog modal, dan foto terhapus dari server.
7. Klik **Back to Studio** untuk kembali ke halaman utama photobooth.

### Langkah 9 — Reset Sesi (Opsional)

- Klik tombol **Reset** (ikon panah melingkar) di bawah kamera untuk memulai ulang tanpa reload halaman.
- Semua foto sesi saat ini dihapus dan strip dikosongkan.

---

## 4. Teknologi yang Digunakan

| Komponen     | Teknologi                                            |
| ------------ | ---------------------------------------------------- |
| Backend      | PHP (Apache via Laragon)                             |
| Frontend     | HTML5, CSS3, Vanilla JavaScript (ES6+)               |
| Kamera       | WebRTC (`getUserMedia`)                              |
| Gesture      | MediaPipe Hands                                      |
| Rendering    | HTML5 Canvas API                                     |
| GIF Encoding | omggif.js (client-side)                              |
| Typography   | Google Fonts (Syne, DM Mono, Nunito, Bubblegum Sans) |
| Database     | File-based (foto disimpan di folder `uploads/`)      |

---

## 5. Struktur File Projek

```
photobooth/
├── index.php              ← Halaman utama aplikasi
├── .htaccess              ← Konfigurasi Apache
├── README.md              ← Dokumentasi projek
├── css/
│   └── v2.css             ← Stylesheet utama (tema undersea)
├── js/
│   ├── v2-app.js          ← Orchestrator utama (boot, capture, session)
│   ├── v2-webrtc.js       ← Modul akses kamera WebRTC
│   ├── v2-gesture.js      ← Modul gesture recognition (MediaPipe)
│   ├── v2-filters.js      ← Modul filter foto (CSS filter mapping)
│   ├── v2-strip.js        ← Modul rendering photo strip (Canvas)
│   ├── v2-stickers.js     ← Modul stiker interaktif (gesture + mouse)
│   ├── v2-gif.js          ← Modul encoding Boomerang GIF
│   ├── v2-api.js          ← Modul komunikasi dengan server PHP
│   ├── v2-audio.js        ← Modul efek suara (SFX)
│   ├── v2-utils.js        ← Utilitas umum (Emitter, sleep, download, toast)
│   └── omggif.js          ← Library GIF encoder
├── php/
│   ├── save_photo.php     ← API simpan foto ke server
│   ├── get_gallery.php    ← API ambil daftar foto gallery
│   ├── delete_photo.php   ← API hapus foto
│   ├── save_gif.php       ← API simpan GIF ke server
│   ├── session_info.php   ← API info sesi
│   └── ping.php           ← API health check server
├── assets/
│   ├── frames/            ← Frame preset (frame1.png, frame2.png, frame3.png)
│   ├── stickers/          ← Stiker PNG (fish, bubble, seaweed, shell, squid)
│   ├── video/             ← Video latar bawah laut
│   └── logo-*.png         ← Logo aplikasi
├── gallery/
│   └── index.php          ← Halaman gallery
└── uploads/               ← Folder penyimpanan foto hasil capture
```

---

_Laporan ini disusun berdasarkan analisis kode sumber aplikasi Photobooth Undersea yang berlokasi di `c:\laragon\www\photobooth`._
   