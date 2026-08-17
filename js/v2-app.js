/* v2-app.js — Main Application Orchestrator v2
   Coordinates: WebCam → Gesture → Capture → Strip → GIF → API  */
'use strict';

const App = (() => {
  /* ── State ───────────────────────────────────────────── */
  const S = {
    phase: 'loading',   // loading|ready|countdown|capturing|done
    gestureOn: true,
    photoCount: 0,
    gifURL: null
  };

  /* ── DOM refs ────────────────────────────────────────── */
  let videoEl, gestureCanvas, captureCanvas;
  let btnCapture, btnReset, btnToggleGesture;
  let btnDlStrip, btnMkGif, btnSaveGal, btnNewSess, btnDlGif;
  let grProg, grCenter, grLabel, grSub;
  let statusPill, spText;
  let _selectedFrameIndex = 0;

  // Global touchless cursor state
  let gcEl = null, gcProg = null;
  let gHoverEl = null, gHoverStart = 0;
  let lastHitTestTime = 0;
  let lastTargetEl = null;
  const G_DWELL_MS = 1000;
  const GC_CIRC = 2 * Math.PI * 16;

  /* ── Boot ────────────────────────────────────────────── */
  async function boot(){
    _queryDOM();

    gcEl = document.getElementById('globalCursor');
    gcProg = document.getElementById('gcProg');
    if(gcProg){
      gcProg.style.strokeDasharray = GC_CIRC;
      gcProg.style.strokeDashoffset = GC_CIRC;
    }

    _bindButtons();
    _progress(10,'Checking camera support');

    await U.sleep(200);

    _progress(20,'Loading preset undersea frames');
    await Strip.preloadPresetFrames();

    // Init strip canvas
    Strip.init(document.getElementById('stripCanvas'));

    // Init stickers
    const stickerOverlay = document.getElementById('stickerOverlay');
    console.log("[App] stickerOverlay found:", !!stickerOverlay);
    if (stickerOverlay) Stickers.init(stickerOverlay);
    console.log("[App] Stickers.init done, count=", Stickers.count());

    // Clear stickers button
    document.getElementById('btnClearStickers')?.addEventListener('click', () => {
      Stickers.clearAll();
      U.toast('Stickers cleared', 'info', 1500);
    });

    // Init filters
    videoEl=document.getElementById('video');
    Filters.init(videoEl);

    // Frame picker
    document.querySelectorAll('[data-frame]').forEach(b=>{
      b.addEventListener('click',()=>{
        document.querySelectorAll('[data-frame]').forEach(x=>x.classList.remove('active'));
        b.classList.add('active');
        Strip.setFrameStyle(b.dataset.frame);

        const upBlock = document.getElementById('uploadBlock');
        if (upBlock) {
          if (b.dataset.frame === 'upload') {
            upBlock.style.display = 'block';
          } else {
            upBlock.style.display = 'none';
          }
        }
      });
    });

    _initUploader();

    _progress(30,'Starting camera');

    // Camera
    try {
      WebCam.on('ready', info=>{
        const errEl = document.getElementById('camErr');
        if(errEl) errEl.style.display = 'none';
        document.getElementById('fcBadge').innerHTML=
          `<span id="fcCur">0</span><span class="fc-sep">/</span><span>4</span>`;
        _setStatus('active','Ready');
      });
      WebCam.on('error', (err)=>{
        const errEl = document.getElementById('camErr');
        const detailEl = document.getElementById('camErrDetail');
        const titleEl = document.getElementById('camErrTitle');
        if(errEl) errEl.style.display='flex';
        
        let title = 'Kamera Tidak Dapat Diakses';
        let msg = 'Gagal mengakses kamera. Pastikan izin kamera telah diberikan.';
        
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          msg = 'Fitur kamera diblokir karena website dijalankan melalui protokol tidak aman (HTTP). Silakan aktifkan SSL (HTTPS) di Laragon Anda, atau buka halaman ini melalui http://localhost/photobooth atau http://127.0.0.1/photobooth.';
        } else if (err) {
          if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            msg = 'Izin kamera ditolak. Silakan klik ikon gembok/kamera di sebelah kiri bilah alamat browser Anda dan aktifkan izin kamera.';
          } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            msg = 'Perangkat kamera tidak ditemukan. Pastikan kamera terpasang dengan benar.';
          } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
            msg = 'Kamera sedang digunakan oleh aplikasi lain (seperti Zoom, Teams, OBS, dll.). Silakan tutup aplikasi tersebut lalu coba lagi.';
          } else if (err.name === 'SecurityError') {
            msg = 'Akses kamera diblokir oleh kebijakan keamanan browser (HTTPS diperlukan). Gunakan localhost atau aktifkan SSL.';
          } else {
            msg = `Detail Error (${err.name}): ${err.message || err}`;
          }
        }
        
        if (titleEl) titleEl.textContent = title;
        if (detailEl) detailEl.textContent = msg;
        _setStatus('','Camera error');
      });
      await initCam();
    } catch(e){
      console.error('[App] Camera init failed',e);
    }

    _progress(60,'Loading gesture model');

    // Gesture engine
    try {
      gestureCanvas=document.getElementById('gestureCanvas');
      Gesture.on('ready',()=>{ U.toast('Gesture engine ready','info',2000); });
      Gesture.on('frame',_onGestureFrame);
      Gesture.on('holdStart',()=>{ _setStatus('gesture','Detecting palm...'); });
      Gesture.on('holdCancel',()=>{ if(S.phase==='ready') _setStatus('active','Ready'); });
      Gesture.on('trigger',_onGestureTrigger);
      await Gesture.init(videoEl, gestureCanvas);
    } catch(e){
      console.error('[App] Gesture init failed',e);
      U.toast('Gesture unavailable — use manual capture','warn');
    }

    _progress(90,'Server check');
    const ping=await API.ping();
    if(!ping.ok) U.toast('Server offline — gallery save disabled','warn');

    _progress(100,'Ready!');
    await U.sleep(400);

    // Show app
    const ls=document.getElementById('loadScreen');
    ls.classList.add('out');
    document.getElementById('app').style.display='flex';
    setTimeout(()=>ls.style.display='none',600);

    // Re-render sticker picker now that trays are visible
    Stickers.renderPicker();

    S.phase='ready';
    _setStatus('active','Ready');
    _updateFrameBadge();
    const activeFrameBtn = document.querySelector('[data-frame].active');
    if (activeFrameBtn) {
      Strip.setFrameStyle(activeFrameBtn.dataset.frame);
    }
  }

  async function initCam(){
    await WebCam.init(document.getElementById('video'));
  }

  /* ── Gesture frame update (no-op if not ready) ───────── */
  function _onGestureFrame({detected, conf, holdProg}){
    // Update ring progress SVG
    const CIRC=2*Math.PI*42; // r=42 in SVG viewBox 0 0 100 100
    if(grProg){
      const offset=CIRC-(holdProg>0 ? holdProg : conf*.6)*CIRC;
      grProg.style.strokeDasharray=CIRC;
      grProg.style.strokeDashoffset=Math.max(0,offset);
      grProg.classList.toggle('active', detected&&holdProg===0);
      grProg.classList.toggle('complete', holdProg>.99);
    }
    if(grCenter){
      grCenter.classList.toggle('active', detected&&holdProg===0);
      grCenter.classList.toggle('complete', holdProg>.99);
    }
    if(grLabel){
      if(holdProg>0) grLabel.textContent='Hold still...';
      else if(detected) grLabel.textContent='Palm detected';
      else grLabel.textContent='Open palm';
    }
    if(grSub){
      grSub.textContent=holdProg>0
        ? `${Math.round(holdProg*100)}%`
        : 'hold 1 sec';
    }

    // --- Global Touchless Cursor Logic ---
    // The frame event passes indexTip & isPointing (via v2-gesture.js)
    // arguments[0] object contains these.
    const fData = arguments[0];
    if (gcEl) {
      if (fData.isPointing && fData.indexTip) {
        gcEl.style.display = 'block';
        
        // Use cached gestureCanvas
        const cvs = gestureCanvas || document.getElementById('gestureCanvas');
        const cvsW = cvs?.width || 640;
        const cvsH = cvs?.height || 480;
        
        // Map to screen (horizontally mirrored camera)
        const screenX = (1 - (fData.indexTip.x / cvsW)) * window.innerWidth;
        const screenY = (fData.indexTip.y / cvsH) * window.innerHeight;
        
        gcEl.style.transform = `translate(${screenX}px, ${screenY}px)`;
        
        const now = Date.now();
        let targetEl = lastTargetEl;

        // Throttle hit-testing to every 100ms to prevent layout reflow freezes
        if (now - lastHitTestTime > 100) {
          lastHitTestTime = now;
          const hitEls = document.elementsFromPoint(screenX, screenY);
          targetEl = null;
          for (const el of hitEls) {
            const interactive = el.closest('button, a, select, input, .g-click, .up-dropzone, .pgb, .cbtn, [role="button"]');
            if (interactive) {
              targetEl = interactive;
              break;
            }
          }
          lastTargetEl = targetEl;
        }
        
        if (targetEl && !targetEl.disabled) {
          if (gHoverEl !== targetEl) {
            if (gHoverEl) gHoverEl.classList.remove('g-hover');
            gHoverEl = targetEl;
            gHoverEl.classList.add('g-hover');
            gHoverStart = now;
          }
          
          const prog = Math.min((now - gHoverStart) / G_DWELL_MS, 1);
          if (gcProg) gcProg.style.strokeDashoffset = GC_CIRC * (1 - prog);
          
          if (prog >= 1 && !targetEl.dataset.gClicked) {
            targetEl.dataset.gClicked = "true";
            targetEl.click();
            window.SFX?.success?.();
            setTimeout(() => { targetEl.dataset.gClicked = ""; }, 1200);
          }
        } else {
          _cancelGlobalDwell();
        }
        
      } else {
        gcEl.style.display = 'none';
        _cancelGlobalDwell();
      }
    }
  }

  function _cancelGlobalDwell() {
    if (gHoverEl) {
      gHoverEl.classList.remove('g-hover');
      gHoverEl = null;
    }
    gHoverStart = 0;
    if (gcProg) gcProg.style.strokeDashoffset = GC_CIRC;
  }

  async function _onGestureTrigger({conf}){
    if(S.phase!=='ready') return;
    S.phase='countdown';
    _setStatus('countdown','Countdown');
    U.toast('Palm detected — shooting!','info',1500);
    await _runCapture();
  }

  /* ── Capture sequence ────────────────────────────────── */
  async function _runCapture(){
    if(Strip.isComplete()){ U.toast('Strip complete — start new session','warn'); return; }

    btnCapture.disabled=true;
    Gesture.pause();
    S.phase='countdown';

    // Countdown
    await _countdown(window.PB_CFG?.cdSecs||3);

    // Flash and Shutter Sound
    window.SFX?.shutter?.();
    const fl=document.getElementById('flashVfx');
    fl.classList.remove('go');
    void fl.offsetWidth;
    fl.classList.add('go');

    await U.sleep(60);

    // Capture
    S.phase='capturing';
    const filter=Filters.get();
    const {dataURL, w, h}=WebCam.capture(filter);

    // Burn stickers onto the captured frame
    let finalDataURL = dataURL;
    try {
      const burnCvs = document.createElement('canvas');
      burnCvs.width  = w || 640;
      burnCvs.height = h || 480;
      const burnCtx  = burnCvs.getContext('2d');
      await new Promise(res => {
        const img = new Image();
        img.onload = () => { burnCtx.drawImage(img, 0, 0); res(); };
        img.src = dataURL;
      });
      await Stickers.burnToCanvasAsync(burnCvs);
      finalDataURL = burnCvs.toDataURL('image/jpeg', .92);
    } catch(e) {
      console.warn('[App] Sticker burn failed:', e);
      finalDataURL = dataURL;
    }

    const idx=Strip.add(finalDataURL);
    S.photoCount++;
    _updateFrameBadge();
    document.getElementById('stripCt').textContent=`${S.photoCount}/4`;

    U.toast(`Frame ${idx+1} captured`,'success',1200);

    if(Strip.isComplete()) await _onComplete();
    else {
      S.phase='ready';
      btnCapture.disabled=false;
      Gesture.resume();
      Gesture.resetCooldown();
      _setStatus('active',`${4-S.photoCount} more`);
    }
  }

  async function _onComplete(){
    S.phase='done';
    Gesture.setEnabled(false);
    _setStatus('active','Strip complete!');
    window.SFX?.success?.();

    // Show output actions and hide placeholder canvas
    const outActions = document.getElementById('outActions');
    if (outActions) outActions.style.display = 'flex';
    const ph = document.getElementById('stripPh');
    if (ph) ph.style.display = 'none';

    // Auto-trigger the full post-capture workflow
    const frames    = Strip.getFrames();
    const sessionId = window.PB_CFG?.sessionId || 'UNKNOWN';
    try {
      await Workflow.run(frames, sessionId);
    } catch(e) {
      console.error('[App] Workflow error:', e);
      U.toast('Workflow error: ' + e.message, 'error', 8000);
      U.toast('Strip complete! Download or save to gallery.','success',4000);
    }
    btnCapture.disabled=false;
  }

  /* ── Countdown ───────────────────────────────────────── */
  async function _countdown(secs){
    const layer=document.getElementById('cdLayer');
    const numEl=document.getElementById('cdN');
    layer.style.display='flex';
    for(let n=secs;n>0;n--){
      window.SFX?.tick?.();
      numEl.textContent=n;
      numEl.classList.remove('pop');
      void numEl.offsetWidth;
      numEl.classList.add('pop');
      await U.sleep(1000);
    }
    layer.style.display='none';
  }


  function _updateFrameBadge(){
    const el=document.getElementById('fcCur');
    if(el) el.textContent=S.photoCount;
  }

  /* ── Status ──────────────────────────────────────────── */
  function _setStatus(cls, text){
    if(statusPill){
      statusPill.className='status-pill'+(cls?` ${cls}`:'');
    }
    if(spText) spText.textContent=text;
  }

  /* ── Loading progress ────────────────────────────────── */
  function _progress(pct, text){
    const bar=document.getElementById('loadBar');
    const st=document.getElementById('loadStatus');
    if(bar) bar.style.width=pct+'%';
    if(st && text) st.textContent=text;
  }

  /* ── DOM query ───────────────────────────────────────── */
  function _queryDOM(){
    videoEl          = document.getElementById('video');
    gestureCanvas    = document.getElementById('gestureCanvas');
    captureCanvas    = document.getElementById('captureCanvas');
    btnCapture       = document.getElementById('btnCapture');
    btnReset         = document.getElementById('btnReset');
    btnToggleGesture = document.getElementById('btnToggleGesture');
    btnDlStrip       = document.getElementById('btnDlStrip');
    btnMkGif         = document.getElementById('btnMkGif');
    btnSaveGal       = document.getElementById('btnSaveGal');
    btnNewSess       = document.getElementById('btnNewSess');
    btnDlGif         = document.getElementById('btnDlGif');
    grProg           = document.getElementById('grProg');
    grCenter         = document.getElementById('grCenter');
    grLabel          = document.getElementById('grLabel');
    grSub            = document.getElementById('grSub');
    statusPill       = document.getElementById('statusPill');
    spText           = document.getElementById('spText');
  }

  /* ── Button bindings ─────────────────────────────────── */
  function _bindButtons(){
    // Topbar Logo -> Start Page
    document.querySelector('.topbar-brand')?.addEventListener('click', () => {
      window.SFX?.click?.();
      if(confirm('Return to start page? Current session will be lost.')) location.reload();
    });

    document.getElementById('btnCapture')?.addEventListener('click',()=>{
      window.SFX?.click?.();
      if(S.phase==='ready') _runCapture();
    });

    document.getElementById('btnReset')?.addEventListener('click',()=>{
      window.SFX?.click?.();
      if(S.phase==='countdown'||S.phase==='capturing') return;
      _resetSession();
    });

    document.getElementById('btnToggleGesture')?.addEventListener('click',e=>{
      S.gestureOn=!S.gestureOn;
      Gesture.setEnabled(S.gestureOn);
      e.currentTarget.dataset.active=S.gestureOn;
      U.toast(S.gestureOn?'Gesture on':'Gesture off','info',1500);
    });

    document.getElementById('btnDlStrip')?.addEventListener('click',async()=>{
      window.SFX?.click?.();
      U.toast('Preparing download...','info',1200);
      const url=await Strip.getDataURL();
      U.download(url, `${window.PB_CFG?.sessionId}_strip.png`);
      window.SFX?.success?.();
      U.toast('Strip downloaded','success');
    });

    document.getElementById('btnMkGif')?.addEventListener('click', async()=>{
      window.SFX?.click?.();
      const frames = Strip.getFrames();
      if(!frames.length){ window.SFX?.error?.(); U.toast('No photos yet','warn'); return; }

      const sec     = document.getElementById('gifSection');
      const loadSt  = document.getElementById('gifLoadState');
      const gifOut  = document.getElementById('gifOut');
      const dlBtn   = document.getElementById('btnDlGif');
      const loadTxt = document.getElementById('gifLoadState')?.querySelector('span');

      if(sec)    sec.style.display   = 'block';
      if(loadSt) loadSt.style.display = 'flex';
      if(gifOut) gifOut.style.display  = 'none';
      if(dlBtn)  dlBtn.style.display   = 'none';

      // Remove any old listener to avoid double-fire
      const progressHandler = pct => {
        if(loadTxt) loadTxt.textContent = `Encoding… ${pct}%`;
        _setStatus('active', `GIF ${pct}%`);
      };
      GifMaker.on('progress', progressHandler);

      try {
        const url = await GifMaker.generate(frames);
        if(gifOut){ gifOut.src = url; gifOut.style.display = 'block'; }
        if(loadSt)  loadSt.style.display = 'none';
        if(dlBtn)   dlBtn.style.display  = 'block';
        _setStatus('active', 'Ready');
        window.SFX?.success?.();
        U.toast('Boomerang GIF ready!', 'success');
      } catch(e){
        if(loadSt) loadSt.style.display = 'none';
        if(sec)    sec.style.display    = 'none';
        _setStatus('active', 'Ready');
        window.SFX?.error?.();
        U.toast('GIF failed: ' + e.message, 'error', 6000);
        console.error('[GIF]', e);
      }
    });

    document.getElementById('btnDlGif')?.addEventListener('click',()=>{
      window.SFX?.click?.();
      GifMaker.download();
      window.SFX?.success?.();
      U.toast('GIF downloaded','success');
    });

    document.getElementById('btnSaveGal')?.addEventListener('click', async () => {
      window.SFX?.click?.();
      const frames = Strip.getFrames();
      if (!frames.length) { window.SFX?.error?.(); U.toast('No photos yet', 'warn'); return; }

      const btn = document.getElementById('btnSaveGal');
      const origText = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Saving...';

      try {
        U.toast('Saving to gallery...', 'info', 2000);
        const stripURL = await Strip.getDataURL();
        if (!stripURL) throw new Error('Failed to render strip canvas');

        const res = await API.saveStrip(stripURL, frames, {
          filter:     Filters.get(),
          frameStyle: Strip.getFrameStyle()
        });

        if (res.success) {
          window.SFX?.success?.();
          U.toast(`Saved! Session: ${res.id}`, 'success', 5000);
        } else {
          throw new Error(res.message || 'Unknown server error');
        }
      } catch (e) {
        window.SFX?.error?.();
        U.toast('Save failed: ' + e.message, 'error', 7000);
        console.error('[Save]', e);
      }

      btn.disabled = false;
      btn.textContent = origText;
    });

    document.getElementById('btnNewSess')?.addEventListener('click',()=>{
      window.SFX?.click?.();
      if(confirm('Start new session? Current photos will be lost.')) location.reload();
    });

    // Space = capture
    document.addEventListener('keydown',e=>{
      if(e.code==='Space'&&S.phase==='ready'){ e.preventDefault(); _runCapture(); }
    });

    // ── QR Screen buttons ──────────────────────────────────
    // "Kirim ke Video Mapping" — opens confirmation dialog
    document.getElementById('pbBtnVideoMapping')?.addEventListener('click', () => {
      window.SFX?.click?.();
      Workflow.stopCountdown();
      _selectedFrameIndex = 0; // Default to first photo
      _renderVMGrid();
      const dlg = document.getElementById('pbVMDialog');
      if (dlg) { dlg.style.display = 'flex'; requestAnimationFrame(() => dlg.classList.add('visible')); }
    });

    // Dialog: Cancel
    document.getElementById('pbVMCancel')?.addEventListener('click', () => {
      window.SFX?.click?.();
      _closeVMDialog();
      // Resume countdown after cancel
      Workflow._startCountdown?.();
    });

    // Dialog: Confirm Send
    document.getElementById('pbVMConfirm')?.addEventListener('click', async () => {
      window.SFX?.click?.();
      _closeVMDialog();
      await Workflow.sendToVideoMapping(_selectedFrameIndex);
    });

    // Retry button
    document.getElementById('pbBtnRetryVM')?.addEventListener('click', async () => {
      window.SFX?.click?.();
      await Workflow.sendToVideoMapping(_selectedFrameIndex);
    });

    // Close QR screen — user closes manually, then resets session
    document.getElementById('pbBtnCloseQR')?.addEventListener('click', () => {
      window.SFX?.click?.();
      Workflow.hideQRScreen();
      setTimeout(() => { App.resetSession(); }, 500);
    });
  }

  /* ── Session reset ───────────────────────────────────── */
  function _resetSession(){
    S.phase='ready'; S.photoCount=0;
    Strip.reset();
    document.getElementById('outActions').style.display='none';
    document.getElementById('stripCt').textContent='0/4';
    document.getElementById('stripPh').style.display='flex';
    document.getElementById('stripCanvas').style.display='none';
    document.getElementById('gifSection').style.display='none';
    _updateFrameBadge();
    // Fix: must resume() AND resetCooldown() — setEnabled alone isn't enough
    // if Gesture was paused (e.g. during countdown) or disabled (_onComplete)
    Gesture.setEnabled(true);
    Gesture.resume();
    Gesture.resetCooldown();
    S.gestureOn=true;
    document.getElementById('btnToggleGesture').dataset.active='true';
    btnCapture.disabled=false;
    _setStatus('active','Ready');
    U.toast('New session started','info');
  }

  /* ── Custom Canva/Figma Template Uploader & Guide Downloader ── */
  function _downloadGuideTemplate() {
    const c = document.createElement('canvas');
    c.width = 420;
    c.height = 1322;
    const ctx = c.getContext('2d');
    
    // Fill background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 420, 1322);
    
    const PAD = 20;
    const HEADER = 56;
    const PHOTO_W = 380;
    const PHOTO_H = 285;
    const GAP = 14;
    
    // Fill slots with light teal overlay
    ctx.fillStyle = '#eafaf1';
    ctx.strokeStyle = '#2ecfb0';
    ctx.lineWidth = 3;
    
    // Draw 4 slots
    for (let i = 0; i < 4; i++) {
      const y = HEADER + PAD + i * (PHOTO_H + GAP);
      ctx.fillRect(PAD, y, PHOTO_W, PHOTO_H);
      ctx.strokeRect(PAD, y, PHOTO_W, PHOTO_H);
      
      // Slot Number
      ctx.fillStyle = '#0d4a80';
      ctx.font = 'bold 20px Arial, sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(`SLOT FOTO ${i + 1} (380x285)`, 420/2, y + PHOTO_H/2);
    }
    
    // Header & Footer boundaries
    ctx.fillStyle = '#fff9e6';
    ctx.strokeStyle = '#f7d716';
    ctx.lineWidth = 2;
    ctx.fillRect(PAD, 10, PHOTO_W, HEADER - 2);
    ctx.strokeRect(PAD, 10, PHOTO_W, HEADER - 2);
    ctx.fillStyle = '#0d3a5e';
    ctx.font = 'bold 11px Arial';
    ctx.fillText('HEADER AREA (420x76)', 420/2, HEADER/2 + 8);
    
    const footerY = 1322 - 44 - 10;
    ctx.fillStyle = '#fff9e6';
    ctx.fillRect(PAD, footerY, PHOTO_W, 44);
    ctx.strokeRect(PAD, footerY, PHOTO_W, 44);
    ctx.fillStyle = '#0d3a5e';
    ctx.fillText('FOOTER AREA (420x44)', 420/2, footerY + 22);
    
    // Watermark/Instruction
    ctx.fillStyle = 'rgba(13, 58, 94, 0.4)';
    ctx.font = '9px monospace';
    ctx.fillText('DESIGN GUIDE • 420x1322 px', 420/2, 1322 - 6);
    
    // Trigger download
    const link = document.createElement('a');
    link.download = 'photobooth_template_guide_420x1322.png';
    link.href = c.toDataURL('image/png');
    link.click();
    
    U.toast('Guide template downloaded', 'success');
  }

  function _initUploader() {
    const btnDl = document.getElementById('btnDlTemplate');
    const dz = document.getElementById('upDropzone');
    const fileInput = document.getElementById('upFileInput');
    const selectMode = document.getElementById('upMode');
    const bgColorInput = document.getElementById('upBgColor');
    const colorCtrl = document.getElementById('upColorCtrl');

    if (btnDl) {
      btnDl.addEventListener('click', _downloadGuideTemplate);
    }

    if (dz && fileInput) {
      dz.addEventListener('click', () => fileInput.click());

      fileInput.addEventListener('change', e => {
        const file = e.target.files[0];
        if (file) _handleUploadedFile(file);
      });

      // Drag and drop
      dz.addEventListener('dragover', e => {
        e.preventDefault();
        dz.classList.add('dragover');
      });
      dz.addEventListener('dragleave', () => {
        dz.classList.remove('dragover');
      });
      dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (file) _handleUploadedFile(file);
      });
    }

    if (selectMode) {
      selectMode.addEventListener('change', () => {
        const mode = selectMode.value;
        if (colorCtrl) {
          colorCtrl.style.display = mode === 'overlay' ? 'block' : 'none';
        }
        Strip.setUploadedFrame(undefined, mode, undefined);
      });
    }

    if (bgColorInput) {
      bgColorInput.addEventListener('input', () => {
        Strip.setUploadedFrame(undefined, undefined, bgColorInput.value);
      });
    }
  }

  function _handleUploadedFile(file) {
    if (!file.type.startsWith('image/')) {
      U.toast('File harus berupa gambar (PNG/JPG)', 'error');
      return;
    }

    const reader = new FileReader();
    const dzLabel = document.getElementById('upDzLabel');

    reader.onload = e => {
      const img = new Image();
      img.onload = () => {
        Strip.setUploadedFrame(img, undefined, undefined);
        U.toast('Frame berhasil diunggah!', 'success');
        if (dzLabel) dzLabel.textContent = file.name;
      };
      img.onerror = () => {
        U.toast('Gagal memuat gambar bingkai', 'error');
      };
      img.src = e.target.result;
    };
    reader.onerror = () => {
      U.toast('Gagal membaca berkas', 'error');
    };
    reader.readAsDataURL(file);
  }

  function _renderVMGrid() {
    const grid = document.getElementById('pbVMGrid');
    if (!grid) return;
    grid.innerHTML = '';
    
    const frames = Strip.getFrames();
    frames.forEach((src, idx) => {
      const item = document.createElement('div');
      item.className = 'pbvm-item' + (idx === _selectedFrameIndex ? ' selected' : '');
      item.dataset.index = idx;
      
      const img = document.createElement('img');
      img.src = src;
      img.alt = `Foto ${idx + 1}`;
      
      item.appendChild(img);
      
      item.addEventListener('click', () => {
        window.SFX?.click?.();
        _selectedFrameIndex = idx;
        
        // Update selection UI
        grid.querySelectorAll('.pbvm-item').forEach(el => {
          el.classList.remove('selected');
        });
        item.classList.add('selected');
      });
      
      grid.appendChild(item);
    });
  }

  function _closeVMDialog() {
    const dlg = document.getElementById('pbVMDialog');
    if (dlg) {
      dlg.classList.remove('visible');
      setTimeout(() => { dlg.style.display = 'none'; }, 300);
    }
  }

  document.addEventListener('DOMContentLoaded',()=>{
    // Boot immediately to enable touchless gesturing on the Start page
    boot().catch(e=>{ console.error('[App] Boot error',e); U.toast('Boot error: '+e.message,'error',0); });
  });

  return { boot, initCam, resetSession: _resetSession };
})();
window.App=App;
