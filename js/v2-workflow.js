/* v2-workflow.js — Automated Post-Capture Pipeline v2
   Runs automatically after 4 photos are captured.
   Each step has its own try/catch so NO single failure
   can block the QR Code from appearing.
*/
'use strict';

const Workflow = (() => {

  /* ── State ──────────────────────────────────── */
  let _driveData = null;
  let _timerHandle = null;
  let _timerSecs = 60;
  let _qrInstance = null;

  const QUEUE_KEY = 'pb_upload_queue';

  /* ═══════════════════════════════════════════════
     MAIN ENTRY POINT — called from App._onComplete()
     ═══════════════════════════════════════════════ */
  async function run(frames, sessionId) {
    console.log('[Workflow] run() — session:', sessionId);

    /* ── Show processing overlay ── */
    _showProcessingOverlay(true, 'Memproses foto Anda...');

    /* ── Step 1: Save strip + frames to server ── */
    try {
      _updateProcessing('Menyimpan foto ke server...');
      const stripDataURL = await Strip.getDataURL();
      if (stripDataURL) {
        await API.saveStrip(stripDataURL, frames, {
          filter: Filters.get(),
          frameStyle: Strip.getFrameStyle()
        });
        console.log('[Workflow] Strip saved OK');
      }
    } catch (e) {
      console.warn('[Workflow] Step1 (save strip) non-fatal:', e.message);
    }

    /* ── Step 2: Generate & save GIF ── */
    try {
      _updateProcessing('Membuat animasi GIF...');
      const gifUrl = await GifMaker.generate(frames);
      let gifBlob = (typeof GifMaker.getBlob === 'function')
        ? GifMaker.getBlob()
        : await (await fetch(gifUrl)).blob();
      if (gifBlob) {
        await API.saveGif(gifBlob, sessionId);
        console.log('[Workflow] GIF saved OK');
      }
    } catch (e) {
      console.warn('[Workflow] Step2 (GIF) non-fatal:', e.message);
    }

    /* ── Step 3: Upload to Google Drive ── */
    _updateProcessing('Mengunggah ke Google Drive...');
    try {
      _driveData = await API.uploadGDrive(sessionId);
      if (!_driveData?.success) throw new Error(_driveData?.message || 'Upload gagal');
      console.log('[Workflow] GDrive upload OK');
    } catch (e) {
      console.warn('[Workflow] Step3 (GDrive) fallback to local:', e.message);
      _queueOfflineUpload(sessionId);
      const base = _localBase(sessionId);
      _driveData = {
        success: true,
        is_local_fallback: true,
        google_drive_folder_url: base,
        photo_strip_url: base + 'strip.png',
        photo1_url: base + 'frame_0.jpg',
        photo2_url: base + 'frame_1.jpg',
        photo3_url: base + 'frame_2.jpg',
        photo4_url: base + 'frame_3.jpg',
        gif_url: base + 'boomerang.gif'
      };
    }

    /* ── Step 4: Build QR URL ── */
    _updateProcessing('Membuat QR Code...');
    let shareUrl = '';
    if (_driveData?.google_drive_folder_url && _driveData.google_drive_folder_url.includes('drive.google.com')) {
      shareUrl = _driveData.google_drive_folder_url;
    } else {
      const proto = location.protocol;
      const host = location.host;
      const pbPath = location.pathname.replace(/\/[^/]*$/, '');
      shareUrl = `${proto}//${host}${pbPath}/share.php?sid=${encodeURIComponent(sessionId)}`;
    }
    console.log('[Workflow] Final QR URL:', shareUrl);

    /* ── Step 6: Always show QR screen ── */
    _showProcessingOverlay(false);
    setTimeout(() => _showQRScreen(shareUrl), 300);
  }

  /* ═══════════════════════════════════════════════
     PROCESSING OVERLAY
     ═══════════════════════════════════════════════ */
  function _showProcessingOverlay(visible, text) {
    const el = document.getElementById('pbProcessingOverlay');
    if (!el) { console.warn('[Workflow] #pbProcessingOverlay not found'); return; }
    if (visible) {
      el.style.cssText = 'display:flex; opacity:1;';
      el.classList.add('visible');
      _updateProcessing(text || 'Memproses...');
    } else {
      el.classList.remove('visible');
      el.style.opacity = '0';
      setTimeout(() => { el.style.display = 'none'; }, 400);
    }
  }

  function _updateProcessing(text) {
    const el = document.getElementById('pbProcessingText');
    if (el) el.textContent = text;
  }

  /* ═══════════════════════════════════════════════
     QR CODE SCREEN
     ═══════════════════════════════════════════════ */
  function _showQRScreen(shareUrl) {
    const screen = document.getElementById('pbQRScreen');
    if (!screen) {
      console.error('[Workflow] CRITICAL: #pbQRScreen not found in DOM!');
      alert('QR Screen element missing. Check index.php markup.');
      return;
    }

    /* Render QR code canvas */
    const qrContainer = document.getElementById('pbQRCode');
    if (qrContainer) {
      qrContainer.innerHTML = '';
      try {
        _qrInstance = new QRCode(qrContainer, {
          text: shareUrl,
          width: 400,
          height: 400,
          colorDark: '#0d2e4d',
          colorLight: '#ffffff',
          correctLevel: 0 /* M — most scannable */
        });
        console.log('[Workflow] QR rendered OK');
      } catch (e) {
        console.error('[Workflow] QR render error:', e);
        /* Fallback: show URL as text */
        qrContainer.innerHTML = `<div style="background:#fff;padding:12px;border-radius:8px;font-size:10px;word-break:break-all;color:#333;max-width:240px;">${shareUrl}</div>`;
      }
    }

    /* Setup Download button */
    const btnDl = document.getElementById('pbBtnDownloadQR');
    if (btnDl) {
      btnDl.onclick = () => window.open(shareUrl, '_blank');
      if (_driveData?.is_gdrive) {
        btnDl.innerHTML = `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
            <polyline points="15 3 21 3 21 9"></polyline>
            <line x1="10" y1="14" x2="21" y2="3"></line>
          </svg>
          Buka Google Drive
        `;
      }
    }

    /* Make the screen visible */
    screen.style.display = 'flex';
    screen.style.opacity = '1';
    screen.style.zIndex = '9200';
    screen.classList.add('visible');
    console.log('[Workflow] QR screen shown');

    // NO auto-countdown: user closes manually via × button
    _watchReconnect();
  }

  function hideQRScreen() {
    const screen = document.getElementById('pbQRScreen');
    if (screen) {
      screen.classList.remove('visible');
      setTimeout(() => { screen.style.display = 'none'; }, 500);
    }
    _stopCountdown();
  }

  /* ═══════════════════════════════════════════════
     COUNTDOWN RING
     ═══════════════════════════════════════════════ */
  function _startCountdown() {
    _timerSecs = window.PB_CFG?.qrTimerSeconds || 60;
    _updateCountdownUI(_timerSecs);
    _stopCountdown();
    _timerHandle = setInterval(() => {
      _timerSecs--;
      _updateCountdownUI(_timerSecs);
      if (_timerSecs <= 0) {
        _stopCountdown();
        hideQRScreen();
        setTimeout(() => {
          if (typeof App !== 'undefined' && App.resetSession) {
            App.resetSession();
          } else {
            location.reload();
          }
        }, 600);
      }
    }, 1000);
  }

  function _stopCountdown() {
    if (_timerHandle) { clearInterval(_timerHandle); _timerHandle = null; }
  }

  function _updateCountdownUI(secs) {
    const numEl = document.getElementById('pbCountdownNum');
    const ringEl = document.getElementById('pbCountdownRing');
    if (numEl) numEl.textContent = secs;
    if (ringEl) {
      const max = window.PB_CFG?.qrTimerSeconds || 60;
      const circ = 2 * Math.PI * 28; // r=28
      const offset = circ - (secs / max) * circ;
      ringEl.style.strokeDasharray = circ;
      ringEl.style.strokeDashoffset = offset;
    }
  }

  /* ═══════════════════════════════════════════════
     OFFLINE QUEUE
     ═══════════════════════════════════════════════ */
  function _localBase(sessionId) {
    const proto = location.protocol;
    const host = location.host;
    const pbPath = location.pathname.replace(/\/[^/]*$/, '');
    return `${proto}//${host}${pbPath}/uploads/${sessionId}/`;
  }

  function _queueOfflineUpload(sessionId) {
    try {
      const queue = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
      if (!queue.find(q => q.sessionId === sessionId)) {
        queue.push({ sessionId, timestamp: Date.now() });
        localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
      }
    } catch (e) { }
  }

  function _watchReconnect() {
    window.addEventListener('online', _processQueue, { once: true });
    setTimeout(_processQueue, 4000);
  }

  async function _processQueue() {
    if (!navigator.onLine) return;
    try {
      const queue = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
      if (!queue.length) return;
      const remaining = [];
      for (const item of queue) {
        try {
          const result = await API.uploadGDrive(item.sessionId);
          if (!result?.success) remaining.push(item);
        } catch (e) {
          remaining.push(item);
        }
      }
      localStorage.setItem(QUEUE_KEY, JSON.stringify(remaining));
    } catch (e) { }
  }

  /* ─── Public API ─────────────────────────────── */
  return {
    run,
    hideQRScreen,
    stopCountdown: _stopCountdown
  };
})();
window.Workflow = Workflow;
