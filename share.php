<?php
/**
 * share.php — Mobile-Responsive Download Gallery
 * QR code scans lead here. Displays strip, photos, and GIF with download buttons.
 *
 * Query params:
 *   sid   - session ID
 *   strip - photo strip URL
 *   p1..p4 - individual photo URLs
 *   gif   - GIF URL
 */

$sid   = htmlspecialchars(preg_replace('/[^A-Za-z0-9_\-]/', '', substr($_GET['sid'] ?? '', 0, 64)));
$strip = filter_var($_GET['strip'] ?? '', FILTER_SANITIZE_URL);
$p1    = filter_var($_GET['p1']    ?? '', FILTER_SANITIZE_URL);
$p2    = filter_var($_GET['p2']    ?? '', FILTER_SANITIZE_URL);
$p3    = filter_var($_GET['p3']    ?? '', FILTER_SANITIZE_URL);
$p4    = filter_var($_GET['p4']    ?? '', FILTER_SANITIZE_URL);
$gif   = filter_var($_GET['gif']   ?? '', FILTER_SANITIZE_URL);

// Load session metadata from server if sid is provided
if (!empty($sid)) {
    $sessionDir = __DIR__ . '/uploads/' . $sid . '/';
    $metaFile   = $sessionDir . 'meta.json';
    $metaData   = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : null;
    $gdrive     = $metaData['gdrive_data'] ?? null;

    $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $baseUrl = "{$proto}://{$host}{$base}/uploads/{$sid}/";

    if (empty($strip)) $strip = $gdrive['photo_strip_url'] ?? ($baseUrl . 'strip.png');
    if (empty($p1))    $p1    = $gdrive['photo1_url']      ?? ($baseUrl . 'frame_0.jpg');
    if (empty($p2))    $p2    = $gdrive['photo2_url']      ?? ($baseUrl . 'frame_1.jpg');
    if (empty($p3))    $p3    = $gdrive['photo3_url']      ?? ($baseUrl . 'frame_2.jpg');
    if (empty($p4))    $p4    = $gdrive['photo4_url']      ?? ($baseUrl . 'frame_3.jpg');
    if (empty($gif))   $gif   = $gdrive['gif_url']         ?? ($baseUrl . 'boomerang.gif');
}

$title = 'Hasil Foto Sesi ' . ($sid ?: 'Photobooth');
$photos = array_filter([$p1, $p2, $p3, $p4]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title><?= $title ?> — Photobooth Undersea</title>
<meta name="description" content="Unduh hasil foto photobooth Anda: Photo Strip, foto individual, dan GIF animasi.">
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --teal:   #2ecfb0;
  --teal-d: #1a9e88;
  --dark:   #07142a;
  --card:   rgba(255,255,255,0.1);
  --border: rgba(255,255,255,0.18);
  --text:   #ffffff;
  --muted:  rgba(255,255,255,0.5);
  --mono:   'DM Mono', monospace;
  --display:'Syne', sans-serif;
  --r:      20px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
  font-family: var(--display);
  background: var(--dark);
  color: var(--text);
  min-height: 100vh;
  overflow-x: hidden;
  padding-bottom: 60px;
  /* Deep sea gradient */
  background-image:
    radial-gradient(ellipse at top, #0d4a80 0%, transparent 60%),
    radial-gradient(ellipse at bottom, #0a2d5e 0%, transparent 60%);
  background-attachment: fixed;
}

/* ── Ambient Bubbles ─────────────────────────────── */
.bubbles {
  position: fixed; inset: 0;
  pointer-events: none; z-index: 0; overflow: hidden;
}
.bubble {
  position: absolute;
  bottom: -60px;
  border-radius: 50%;
  background: rgba(46,207,176,0.08);
  border: 1px solid rgba(46,207,176,0.15);
  animation: rise linear infinite;
}
.bubble:nth-child(1) { width: 20px; height: 20px; left: 10%; animation-duration: 9s;  animation-delay: 0s; }
.bubble:nth-child(2) { width: 14px; height: 14px; left: 25%; animation-duration: 13s; animation-delay: 2s; }
.bubble:nth-child(3) { width: 30px; height: 30px; left: 45%; animation-duration: 11s; animation-delay: 4s; }
.bubble:nth-child(4) { width: 10px; height: 10px; left: 65%; animation-duration: 8s;  animation-delay: 1s; }
.bubble:nth-child(5) { width: 22px; height: 22px; left: 80%; animation-duration: 14s; animation-delay: 3s; }
.bubble:nth-child(6) { width: 16px; height: 16px; left: 90%; animation-duration: 10s; animation-delay: 6s; }
@keyframes rise {
  from { transform: translateY(0) scale(1);   opacity: 0; }
  10%  { opacity: 1; }
  90%  { opacity: 0.5; }
  to   { transform: translateY(-110vh) scale(1.2); opacity: 0; }
}

/* ── Layout ──────────────────────────────────────── */
.page { position: relative; z-index: 1; max-width: 480px; margin: 0 auto; padding: 0 16px; }

/* ── Header ──────────────────────────────────────── */
.site-header {
  text-align: center;
  padding: 32px 0 24px;
}
.logo {
  width: 56px; height: 56px;
  border-radius: 14px;
  margin-bottom: 14px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.35);
}
.site-title {
  font-size: 22px; font-weight: 800;
  letter-spacing: -0.5px;
  background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.7) 100%);
  -webkit-background-clip: text; background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 4px;
}
.site-sub {
  font-family: var(--mono); font-size: 10px;
  color: var(--muted); letter-spacing: 1px; text-transform: uppercase;
}
.session-badge {
  display: inline-block;
  margin-top: 12px;
  background: rgba(46,207,176,0.12);
  border: 1px solid rgba(46,207,176,0.3);
  border-radius: 20px;
  padding: 4px 14px;
  font-family: var(--mono); font-size: 9px;
  color: var(--teal); letter-spacing: 1px;
}

/* ── Section ─────────────────────────────────────── */
.section {
  margin-bottom: 28px;
}
.section-label {
  font-family: var(--mono); font-size: 10px; font-weight: 500;
  color: var(--muted); text-transform: uppercase; letter-spacing: 1.5px;
  margin-bottom: 14px;
  display: flex; align-items: center; gap: 8px;
}
.section-label::after {
  content: ''; flex: 1;
  height: 1px; background: var(--border);
}

/* ── Card ────────────────────────────────────────── */
.card {
  background: var(--card);
  border: 1.5px solid var(--border);
  border-radius: var(--r);
  overflow: hidden;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 8px 32px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.1);
  animation: fadeUp 0.4s ease both;
}
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: none; }
}

/* ── Strip Section ───────────────────────────────── */
.strip-card .strip-preview-wrap {
  position: relative;
  background: rgba(0,0,0,0.3);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
  min-height: 200px;
}
.strip-preview-wrap img.strip-img {
  max-width: 100%; max-height: 420px;
  border-radius: 10px;
  box-shadow: 0 12px 40px rgba(0,0,0,0.4);
  display: block;
  cursor: zoom-in;
  transition: transform 0.3s ease;
}
.strip-preview-wrap img.strip-img:hover { transform: scale(1.02); }
.strip-card .card-footer {
  padding: 14px 16px;
  border-top: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
}
.card-info {
  font-family: var(--mono); font-size: 10px; color: var(--muted);
}
.card-info strong { color: #fff; font-size: 12px; display: block; margin-bottom: 2px; }

/* ── Photos Grid ─────────────────────────────────── */
.photos-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.photo-card {
  animation-delay: calc(var(--i) * 80ms);
}
.photo-card .photo-wrap {
  position: relative;
  background: rgba(0,0,0,0.3);
  aspect-ratio: 4/3;
  overflow: hidden;
}
.photo-card img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform 0.35s ease;
  cursor: zoom-in;
}
.photo-card img:hover { transform: scale(1.06); }
.photo-num {
  position: absolute; top: 8px; left: 8px;
  background: rgba(0,0,0,0.55);
  border-radius: 6px;
  padding: 2px 8px;
  font-family: var(--mono); font-size: 9px; font-weight: 600;
  color: rgba(255,255,255,0.8);
}
.photo-card .card-footer {
  padding: 10px 12px;
  border-top: 1px solid var(--border);
}

/* ── GIF Section ─────────────────────────────────── */
.gif-card .gif-preview-wrap {
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
  background: rgba(0,0,0,0.2);
  min-height: 160px;
}
.gif-card img {
  width: 100%;
  height: auto;
  display: block;
  border-radius: 10px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.35);
  cursor: zoom-in;
  image-rendering: auto;
}
.gif-card .card-footer {
  padding: 14px 16px;
  border-top: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
}
.gif-badge {
  font-family: var(--mono); font-size: 9px; font-weight: 600;
  background: rgba(46,207,176,0.15);
  border: 1px solid rgba(46,207,176,0.3);
  color: var(--teal);
  border-radius: 10px; padding: 3px 10px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

/* ── Download Button ─────────────────────────────── */
.btn-dl {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 18px;
  background: linear-gradient(135deg, var(--teal) 0%, var(--teal-d) 100%);
  color: #fff;
  border: none; border-radius: 10px;
  font-family: var(--display); font-weight: 700; font-size: 13px;
  text-decoration: none; cursor: pointer;
  box-shadow: 0 4px 14px rgba(46,207,176,0.35);
  transition: all 0.2s ease;
  white-space: nowrap;
}
.btn-dl:hover, .btn-dl:active {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(46,207,176,0.45);
}
.btn-dl svg { width: 15px; height: 15px; flex-shrink: 0; }

.btn-dl-sm {
  padding: 7px 14px;
  font-size: 11px;
  border-radius: 8px;
  width: 100%;
  justify-content: center;
}

/* ── Lightbox ────────────────────────────────────── */
.lightbox {
  display: none;
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,0.92);
  backdrop-filter: blur(10px);
  align-items: center; justify-content: center;
  padding: 20px;
  cursor: zoom-out;
}
.lightbox.open { display: flex; }
.lightbox img {
  max-width: 100%; max-height: 90vh;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.5);
  animation: lbPop 0.2s ease;
}
@keyframes lbPop {
  from { transform: scale(0.9); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}
.lb-close {
  position: fixed; top: 16px; right: 16px;
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.25);
  color: #fff; width: 40px; height: 40px;
  border-radius: 50%; font-size: 20px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  backdrop-filter: blur(8px);
  transition: background 0.15s;
}
.lb-close:hover { background: rgba(255,255,255,0.25); }

/* ── Footer ──────────────────────────────────────── */
.site-footer {
  text-align: center; padding: 32px 0 16px;
  font-family: var(--mono); font-size: 9px;
  color: rgba(255,255,255,0.2); letter-spacing: 0.5px;
}

/* ── Error State ─────────────────────────────────── */
.err-state {
  text-align: center; padding: 60px 20px;
  font-family: var(--mono);
}
.err-state .err-icon { font-size: 48px; margin-bottom: 16px; }
.err-state p { font-size: 12px; color: var(--muted); }
</style>
</head>
<body>

<div class="bubbles">
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
</div>

<div class="page">

  <!-- Header -->
  <header class="site-header">
    <img src="assets/logo-photobooth-undersea.png" class="logo" alt="Photobooth Undersea" onerror="this.style.display='none'">
    <h1 class="site-title">Hasil Foto Anda</h1>
    <p class="site-sub">Photobooth Undersea</p>
    <?php if ($sid): ?>
    <div class="session-badge"><?= $sid ?></div>
    <?php endif; ?>
  </header>

  <?php if (empty($strip) && empty($photos)): ?>
  <!-- Error State -->
  <div class="err-state">
    <div class="err-icon">🔍</div>
    <p>Hasil foto tidak ditemukan.<br>Pastikan link QR Code Anda benar.</p>
  </div>
  <?php else: ?>

  <!-- ── Photo Strip ─────────────────────────────── -->
  <?php if ($strip): ?>
  <section class="section" style="animation-delay:0.05s">
    <p class="section-label">📸 Photo Strip</p>
    <div class="card strip-card">
      <div class="strip-preview-wrap">
        <img class="strip-img"
             src="<?= htmlspecialchars($strip) ?>"
             alt="Photo Strip"
             onclick="openLightbox(this.src)"
             onerror="this.parentElement.innerHTML='<p style=\'color:rgba(255,255,255,.4);font-family:monospace;font-size:11px;padding:20px\'>Gambar tidak tersedia</p>'">
      </div>
      <div class="card-footer">
        <div class="card-info">
          <strong>Photo Strip</strong>
          Semua 4 foto dalam 1 gambar
        </div>
        <a class="btn-dl"
           href="<?= htmlspecialchars($strip) ?>"
           download="photobooth_strip_<?= $sid ?>.png"
           target="_blank">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Download
        </a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── Individual Photos ───────────────────────── -->
  <?php if (!empty($photos)): ?>
  <section class="section" style="animation-delay:0.12s">
    <p class="section-label">🖼 Foto Individual</p>
    <div class="photos-grid">
      <?php
      $photoList = [
        ['url' => $p1, 'label' => 'Foto 1', 'name' => "photo1_{$sid}.jpg"],
        ['url' => $p2, 'label' => 'Foto 2', 'name' => "photo2_{$sid}.jpg"],
        ['url' => $p3, 'label' => 'Foto 3', 'name' => "photo3_{$sid}.jpg"],
        ['url' => $p4, 'label' => 'Foto 4', 'name' => "photo4_{$sid}.jpg"],
      ];
      foreach ($photoList as $i => $photo):
        if (empty($photo['url'])) continue;
      ?>
      <div class="card photo-card" style="--i:<?= $i ?>">
        <div class="photo-wrap">
          <img src="<?= htmlspecialchars($photo['url']) ?>"
               alt="<?= $photo['label'] ?>"
               onclick="openLightbox(this.src)"
               loading="lazy"
               onerror="this.style.display='none'">
          <span class="photo-num"><?= $photo['label'] ?></span>
        </div>
        <div class="card-footer">
          <a class="btn-dl btn-dl-sm"
             href="<?= htmlspecialchars($photo['url']) ?>"
             download="<?= $photo['name'] ?>"
             target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Download
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── GIF Animation ───────────────────────────── -->
  <?php if ($gif): ?>
  <section class="section" style="animation-delay:0.2s">
    <p class="section-label">🎬 Animasi GIF</p>
    <div class="card gif-card">
      <div class="gif-preview-wrap">
        <img src="<?= htmlspecialchars($gif) ?>"
             alt="Animasi GIF"
             onclick="openLightbox(this.src)"
             loading="lazy"
             onerror="this.parentElement.innerHTML='<p style=\'color:rgba(255,255,255,.4);font-family:monospace;font-size:11px;padding:20px\'>GIF tidak tersedia</p>'">
      </div>
      <div class="card-footer">
        <span class="gif-badge">GIF Animasi</span>
        <a class="btn-dl"
           href="<?= htmlspecialchars($gif) ?>"
           download="photobooth_animation_<?= $sid ?>.gif"
           target="_blank">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Download GIF
        </a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php endif; ?>

  <footer class="site-footer">
    Photobooth Undersea &bull; Dibuat dengan ❤️ &bull; <?= date('Y') ?>
  </footer>

</div><!-- /page -->

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <button class="lb-close" onclick="closeLightbox()" aria-label="Tutup">&times;</button>
  <img id="lbImg" src="" alt="Preview">
</div>

<script>
function openLightbox(src) {
  document.getElementById('lbImg').src = src;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeLightbox();
});
</script>
</body>
</html>
