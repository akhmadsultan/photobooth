<?php
/**
 * Immersive Fiesta Photobooth — Purple Galaxy Edition v2
 * WebRTC + MediaPipe Hand Gesture Recognition
 */
session_start();
if (!isset($_SESSION['pb_session'])) {
    $_SESSION['pb_session'] = 'PB_' . date('ymd') . strtoupper(substr(md5(uniqid('',true)),0,6));
    $_SESSION['start_time'] = time();
}
$sid = $_SESSION['pb_session'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Immersive Fiesta Photobooth</title>
<meta name="description" content="Immersive Fiesta Photobooth — Capture your moment with Purple Galaxy style">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/v2.css?v=6">
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands@0.4.1646424915/hands.js" crossorigin="anonymous"></script>
<script src="js/omggif.js"></script>
<script src="js/qrcode.min.js"></script>
</head>
<body>


<!-- Bubbles Animation -->
<div class="bubbles-container">
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
</div>
<!-- START PAGE -->
<div id="startPage" class="start-page">
  <!-- Nebula overlay -->
  <div class="start-overlay"></div>

  <!-- Wordmark + Button center -->
  <div class="start-right">
    <div class="pb-wordmark" style="position:relative;">
      <span class="pb-wordmark-top">IMMERSIVE FIESTA</span>
      <span class="pb-wordmark-brand">PHOTOBOOTH</span>
      <span class="pb-wordmark-sub">✦ STUDIO ✦</span>
    </div>
    <button class="start-btn g-click" id="btnStart">
      <span class="start-btn-inner">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        MULAI
      </span>
    </button>
  </div>
</div>

<!-- LOAD SCREEN -->
<div id="loadScreen" class="load-screen">
  <div class="load-inner">
    <div class="load-wordmark">
      IMMERSIVE FIESTA
      <em>PHOTOBOOTH</em>
    </div>
    <div class="load-bar-track"><div class="load-bar-fill" id="loadBar"></div></div>
    <p class="load-status" id="loadStatus">Starting up...</p>
  </div>
</div>

<!-- APP -->
<div id="app" class="app" style="display:none">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-brand g-click">
      <div class="brand-logo-mark">✦</div>
      <div class="topbar-wordmark">
        IMMERSIVE FIESTA
        <span>PHOTOBOOTH</span>
      </div>
    </div>
    <div class="topbar-mid">
      <div class="status-pill" id="statusPill">
        <span class="sp-dot"></span>
        <span class="sp-text" id="spText">Ready</span>
      </div>
    </div>
    <div class="topbar-end">
      <code class="sid-label"><?= htmlspecialchars($sid) ?></code>
      <a href="gallery/index.php" class="nav-link">Gallery</a>
    </div>
  </header>

  <!-- THREE-COLUMN LAYOUT -->
  <div class="columns">

    <!-- COL A: Frame + Filter + Gesture -->
    <div class="col col-a">

      <section class="selector-block" id="frameBlock">
        <h3 class="selector-title">Frame</h3>
        <div class="selector-grid" id="framePicker">
          <button class="sel-item active g-click" data-frame="frame1">
            <div class="sel-preview" style="background-image: url('assets/frames/frame1.png'); background-size: cover; background-position: center; border-radius: 6px; border: 1.5px solid rgba(255,255,255,.5);"></div>
            <span>Undersea 1</span>
          </button>
          <button class="sel-item g-click" data-frame="frame2">
            <div class="sel-preview" style="background-image: url('assets/frames/frame2.png'); background-size: cover; background-position: center; border-radius: 6px; border: 1.5px solid rgba(255,255,255,.5);"></div>
            <span>Undersea 2</span>
          </button>
          <button class="sel-item g-click" data-frame="frame3">
            <div class="sel-preview" style="background-image: url('assets/frames/frame3.png'); background-size: cover; background-position: center; border-radius: 6px; border: 1.5px solid rgba(255,255,255,.5);"></div>
            <span>Undersea 3</span>
          </button>
          <button class="sel-item g-click" data-frame="upload">
            <div class="sel-preview sp-upload">
              <div class="sp-upload-inner">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="17 8 12 3 7 8"/>
                  <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
              </div>
            </div>
            <span>Upload</span>
          </button>
        </div>
      </section>

      <!-- Custom Canva/Figma Upload Panel -->
      <section class="selector-block upload-block" id="uploadBlock" style="display:none;">
        <h3 class="selector-title">Custom Frame 🎨</h3>
        
        <!-- Action: Download Template Guide -->
        <button class="up-dl-btn g-click" id="btnDlTemplate" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Download Guide Template
        </button>
        <p class="up-help-text">Dapatkan file panduan ukuran 420x1322px untuk diedit di Canva/Figma/Illustrator.</p>

        <!-- Dropzone / File Upload -->
        <div class="up-dropzone g-click" id="upDropzone">
          <input type="file" id="upFileInput" accept="image/png, image/jpeg" style="display:none;">
          <div class="up-dz-content">
            <svg class="up-dz-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <circle cx="8.5" cy="8.5" r="1.5"/>
              <polyline points="21 15 16 10 5 21"/>
            </svg>
            <span class="up-dz-label" id="upDzLabel">Pilih atau Seret Desain Bingkai</span>
            <span class="up-dz-sub">PNG transparan / JPG</span>
          </div>
        </div>

        <!-- Settings: Mode & Background Color -->
        <div class="up-settings">
          <div class="up-control">
            <label class="up-label">Drawing Mode</label>
            <select id="upMode" class="up-select g-click">
              <option value="overlay">Overlay (PNG transparan)</option>
              <option value="background">Background (JPG/PNG solid)</option>
            </select>
          </div>
          <div class="up-control" id="upColorCtrl">
            <label class="up-label">Background Color</label>
            <div class="color-picker-wrapper g-click">
              <input type="color" id="upBgColor" value="#ffffff">
            </div>
          </div>
        </div>
      </section>

      <section class="selector-block" id="filterBlock">
        <h3 class="selector-title">Filter</h3>
        <div class="selector-grid" id="filterPicker">
          <button class="sel-item active g-click" data-filter="none">
            <div class="sel-preview sf-none"></div>
            <span>Natural</span>
          </button>
          <button class="sel-item g-click" data-filter="bw">
            <div class="sel-preview sf-bw"></div>
            <span>B&W</span>
          </button>
          <button class="sel-item g-click" data-filter="sepia">
            <div class="sel-preview sf-sepia"></div>
            <span>Sepia</span>
          </button>
          <button class="sel-item g-click" data-filter="vintage">
            <div class="sel-preview sf-vintage"></div>
            <span>Vintage</span>
          </button>
          <button class="sel-item g-click" data-filter="fade">
            <div class="sel-preview sf-fade"></div>
            <span>Fade</span>
          </button>
          <button class="sel-item g-click" data-filter="warm">
            <div class="sel-preview sf-warm"></div>
            <span>Warm</span>
          </button>
        </div>
      </section>

      <section class="gesture-block" id="gestureBlock">
        <h3 class="selector-title">Gesture</h3>
        <div class="gesture-ring-area">
          <div class="gr-wrap" id="grWrap">
            <svg class="gr-svg" viewBox="0 0 100 100">
              <circle class="gr-bg"   cx="50" cy="50" r="42"/>
              <circle class="gr-prog" cx="50" cy="50" r="42" id="grProg"/>
            </svg>
            <div class="gr-center" id="grCenter">
              <svg class="gr-hand" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 28V14a2 2 0 0 1 4 0v6"/>
                <path d="M20 14V10a2 2 0 0 1 4 0v10"/>
                <path d="M12 28V12a2 2 0 0 1 4 0v2"/>
                <path d="M8 20v-6a2 2 0 0 1 4 0v14"/>
                <path d="M24 20a4 4 0 0 1 4 4v1a7 7 0 0 1-7 7h-4a8 8 0 0 1-5.66-2.34L8 27"/>
              </svg>
            </div>
          </div>
          <p class="gr-label" id="grLabel">Open palm</p>
          <p class="gr-sub"   id="grSub">hold 1.5 sec</p>
        </div>
      </section>

    </div><!-- /col-a -->

    <!-- COL B: Camera + Capture + Sticker tray -->
    <div class="col col-b">

      <!-- Camera viewport -->
      <div class="camera-frame" id="cameraFrame">
        <video id="video" autoplay muted playsinline></video>
        <canvas id="gestureCanvas" class="gesture-canvas"></canvas>
        <canvas id="captureCanvas" style="display:none"></canvas>

        <!-- ★ Sticker Shelf — top inside camera frame ★ -->
        <div class="sticker-shelf" id="stickerShelf">
          <!-- Left group: 3 icons -->
          <div class="shelf-group shelf-group-left" id="shelfGroupLeft"></div>

          <!-- Center label + gesture hint -->
          <div class="shelf-center">
            <span class="shelf-label">✦</span>
            <span class="shelf-mode-hint" id="shelfModeHint">Arahkan jari</span>
          </div>

          <!-- Right group: 3 icons -->
          <div class="shelf-group shelf-group-right" id="shelfGroupRight"></div>
        </div>

        <!-- Sticker overlay inside camera -->
        <div class="sticker-overlay" id="stickerOverlay"></div>

        <!-- Countdown -->
        <div class="cd-layer" id="cdLayer" style="display:none">
          <div class="cd-bubble"><span class="cd-n" id="cdN">3</span></div>
        </div>

        <!-- Flash -->
        <div class="flash-vfx" id="flashVfx"></div>

        <!-- Camera error -->
        <div class="cam-err" id="camErr" style="display:none">
          <p id="camErrTitle" style="font-size: 16px; font-weight: bold; color: #fff; margin-bottom: 6px;">Kamera Tidak Dapat Diakses</p>
          <p id="camErrDetail" style="font-size: 11px; color: rgba(255,255,255,0.8); text-align: center; max-width: 85%; margin-bottom: 14px; line-height: 1.5;"></p>
          <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:center;">
            <button class="g-click" onclick="App.initCam()" style="padding:8px 16px; background:#7B2DC0; border:1px solid #C77DFF; border-radius:12px; color:#fff; cursor:pointer; font-weight:bold; font-size:11px;">Coba Hubungkan Kamera</button>
            <button class="g-click" onclick="WebCam.useVirtualCam(document.getElementById('video'))" style="padding:8px 16px; background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); border-radius:12px; color:#fff; cursor:pointer; font-size:11px;">Mode Demo (Tanpa Kamera)</button>
          </div>
        </div>

        <!-- Frame counter -->
        <div class="fc-badge" id="fcBadge">
          <span id="fcCur">0</span><span class="fc-sep">/</span><span>4</span>
        </div>
      </div>

      <!-- Capture row: stickers LEFT | capture button | stickers RIGHT -->
      <div class="cam-controls">
        <p class="cam-hint" id="camHint">Open palm to start — or tap capture</p>

        <div class="cam-btns-row">

          <!-- Core buttons -->
          <div class="cam-btns">
            <button class="btn-reset g-click" id="btnReset" title="Reset session">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <polyline points="1 4 1 10 7 10"/>
                <path d="M3.51 15a9 9 0 1 0 .49-3.18"/>
              </svg>
            </button>
            <button class="btn-capture g-click" id="btnCapture">
              <span class="bc-outer"></span>
              <span class="bc-inner"></span>
            </button>
            <button class="btn-toggle-gesture g-click" id="btnToggleGesture" title="Toggle gesture" data-active="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M18 11V6a2 2 0 0 0-4 0v5M14 10V4a2 2 0 0 0-4 0v6M10 10.5V6a2 2 0 0 0-4 0v8M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L8 15"/>
              </svg>
            </button>
          </div>

        </div><!-- /cam-btns-row -->

        <!-- Clear stickers link -->
        <button class="stk-clear-inline g-click" id="btnClearStickers">Clear stickers</button>

      </div><!-- /cam-controls -->

    </div><!-- /col-b -->

    <!-- COL C: Strip output -->
    <div class="col col-c">

      <section class="output-block">
        <div class="output-header">
          <h3 class="selector-title">Strip <span class="strip-ct" id="stripCt">0/4</span></h3>
        </div>

        <div class="strip-wrap" id="stripWrap">
          <canvas id="stripCanvas" class="strip-cv" style="display:none"></canvas>
          <div class="strip-ph" id="stripPh">
            <div class="sph-slots">
              <div></div><div></div><div></div><div></div>
            </div>
            <p>Capture 4 photos</p>
          </div>
        </div>

        <div class="output-actions" id="outActions" style="display:none">
          <!-- Preview label -->
          <p class="oa-label">Foto selesai! Cek preview di kanan 👉</p>
          <div class="oa-btn-row">
            <button class="oa-btn oa-ghost g-click" id="btnRetakePreview">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.6"/></svg>
              Foto Ulang
            </button>
            <button class="oa-btn oa-primary oa-qris g-click" id="btnShowQR">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/></svg>
              Ambil Foto via QRIS
            </button>
          </div>
        </div>

      </section>

      <section class="gif-section" id="gifSection" style="display:none">
        <h3 class="selector-title">Boomerang</h3>
        <div class="gif-preview-wrap" id="gifPrevWrap">
          <div class="gif-loading-state" id="gifLoadState">
            <div class="gif-spin"></div>
            <span>Encoding frames</span>
          </div>
          <img id="gifOut" class="gif-out-img" style="display:none" alt="Boomerang GIF">
        </div>
        <button class="oa-btn oa-primary g-click" id="btnDlGif" style="display:none">Download GIF</button>
      </section>

    </div><!-- /col-c -->

  </div><!-- /columns -->


</div><!-- /app -->

<!-- Toast container -->
<div class="toasts" id="toasts"></div>

<!-- Global Touchless Cursor -->
<div class="global-cursor" id="globalCursor" style="display:none">
  <svg viewBox="0 0 40 40">
    <circle class="gc-bg" cx="20" cy="20" r="16" />
    <circle class="gc-prog" id="gcProg" cx="20" cy="20" r="16" />
  </svg>
</div>

<script>
window.PB_CFG = {
  sessionId: '<?= htmlspecialchars($sid) ?>',
  apiBase:   'php/',
  maxFrames:  4,
  cdSecs:     3,
  holdMs:     1000,
  cooldownMs: 2500,
  qrTimerSeconds: 60
};
</script>
<script>
// ── Start page dismiss ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const btn       = document.getElementById('btnStart');
  const startPage = document.getElementById('startPage');
  if (!btn || !startPage) return;

  btn.addEventListener('click', () => {
    startPage.classList.add('out');
    startPage.addEventListener('transitionend', () => startPage.remove(), { once: true });
  }, { once: true });
});
</script>
<script src="js/v2-utils.js?v=10"></script>
<script src="js/v2-audio.js?v=10"></script>
<script src="js/v2-webrtc.js?v=10"></script>
<script src="js/v2-gesture.js?v=11"></script>
<script src="js/v2-filters.js?v=10"></script>
<script src="js/v2-strip.js?v=10"></script>
<script src="js/v2-gif.js?v=10"></script>
<script src="js/v2-api.js?v=13"></script>
<script src="js/v2-stickers.js?v=10"></script>
<script src="js/v2-workflow.js?v=8"></script>
<script src="js/v2-app.js?v=14"></script>

<script>
function StickerAdd(id) {
  var overlay = document.getElementById('stickerOverlay');
  if (!overlay) { console.error('No #stickerOverlay'); return; }
  if (typeof Stickers === 'undefined') { console.error('Stickers not loaded'); return; }
  Stickers.addById(id);
}
</script>

<!-- ============================================================ -->
<!-- PROCESSING OVERLAY -->
<!-- ============================================================ -->
<div id="pbProcessingOverlay" class="pb-overlay pb-processing-overlay" style="display:none">
  <div class="pb-processing-card">
    <div class="pb-processing-spinner">
      <svg viewBox="0 0 50 50">
        <circle class="pbs-track" cx="25" cy="25" r="20" fill="none" stroke-width="4"/>
        <circle class="pbs-fill" cx="25" cy="25" r="20" fill="none" stroke-width="4"/>
      </svg>
    </div>
    <div class="pb-processing-icon">📸</div>
    <p class="pb-processing-title">Sedang Diproses</p>
    <p class="pb-processing-text" id="pbProcessingText">Memproses foto Anda...</p>
    <div class="pb-processing-steps">
      <div class="pbs-step pbs-active"><span>📷</span> Foto</div>
      <div class="pbs-step"><span>🎞</span> Strip</div>
      <div class="pbs-step"><span>🎬</span> GIF</div>
      <div class="pbs-step"><span>☁️</span> Upload</div>
      <div class="pbs-step"><span>🔗</span> QR</div>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- QR CODE SCREEN -->
<!-- ============================================================ -->
<div id="pbQRScreen" class="pb-overlay pb-qr-screen" style="display:none">
  <div class="pbqr-inner">

    <!-- Close button -->
    <button class="pbqr-close" id="pbBtnCloseQR" aria-label="Tutup">&times;</button>

    <!-- Header -->
    <div class="pbqr-header">
      <div style="font-size:28px;margin-bottom:8px;text-shadow:0 0 12px rgba(199,125,255,.6);">✦</div>
      <h1 class="pbqr-title">Scan QR untuk Mengunduh</h1>
      <p class="pbqr-desc">Arahkan kamera HP ke QR Code untuk melihat &amp; mengunduh Photo Strip, foto, dan GIF Anda.</p>
    </div>

    <!-- QR Code -->
    <div class="pbqr-code-wrap">
      <div class="pbqr-code-frame">
        <div id="pbQRCode" class="pbqr-code"></div>
      </div>
      <p class="pbqr-scan-hint">📱 Arahkan kamera HP ke QR Code</p>
    </div>

    <!-- Action Buttons -->
    <div class="pbqr-actions">
      <button class="pbqr-btn pbqr-btn-secondary" id="pbBtnDownloadQR">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
          <polyline points="15 3 21 3 21 9"></polyline>
          <line x1="10" y1="14" x2="21" y2="3"></line>
        </svg>
        Buka Google Drive
      </button>
    </div>

  </div>
</div>

</body>
</html>
