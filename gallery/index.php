<?php
/* gallery/index.php — Gallery viewer (v2 aesthetic) */
session_start();
$apiBase='../php/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Gallery — Foto Booth</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&family=Nunito:wght@800;900&family=Bubblegum+Sans&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/v2.css?v=sponge4">
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js" crossorigin="anonymous"></script>
<script src="../js/v2-utils.js?v=3"></script>
<script src="../js/v2-audio.js?v=1"></script>
<script src="../js/v2-gesture.js?v=4"></script>
<style>
:root {
  --fd: var(--f-display);
  --fm: var(--f-mono);
  --ocean-dark: var(--ocean-darkest);
}
body {
  height: auto !important;
  overflow-y: auto !important;
  overflow-x: hidden;
  background-attachment: fixed;
}
/* Ensure topbar is sticky in gallery */
.topbar {
  position: sticky;
  top: 0;
  z-index: 100;
}

main{max-width:1200px;margin:0 auto;padding:32px 24px;}
.ph{display:flex;align-items:baseline;gap:14px;margin-bottom:28px;}
.pt{font-weight:800;font-size:28px;letter-spacing:-1px;color:var(--ocean-dark);}
.pc{font-family:var(--fm);font-size:11px;color:var(--text-muted);}
.loading,.empty{
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:80px;gap:14px;text-align:center;
}
.spin{width:30px;height:30px;border:3px solid rgba(255,255,255,.3);
  border-top-color:var(--teal);border-radius:50%;animation:s .7s linear infinite;}
@keyframes s{to{transform:rotate(360deg);}}
.loading p,.empty p{font-family:var(--fm);font-size:11px;color:var(--text-muted);}
.et{font-size:22px;font-weight:700;color:var(--ocean-dark);}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;}
.card{
  background:rgba(255,255,255,.25);border:2px solid rgba(255,255,255,.5);
  border-radius:20px;overflow:hidden;transition:transform .2s,box-shadow .2s;
  animation:fu .3s ease both;backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  box-shadow:0 4px 20px rgba(13,74,128,.15),inset 0 1px 0 rgba(255,255,255,.5);
}
.card:hover{
  transform:translateY(-4px);
  box-shadow:0 10px 32px rgba(13,74,128,.25),0 0 0 1px rgba(255,255,255,.6);
}
@keyframes fu{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;}}
.card-img{width:100%;aspect-ratio:2/4;object-fit:cover;display:block;
  background:rgba(13,74,128,.3);cursor:pointer;}
.card-meta{padding:8px 10px;}
.csid{font-family:var(--fm);font-size:9px;letter-spacing:1px;
  color:var(--ocean-dark);margin-bottom:2px;font-weight:700;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cdate{font-family:var(--fm);font-size:9px;color:var(--text-muted);}
.ctags{display:flex;gap:3px;flex-wrap:wrap;margin-top:5px;}
.ctag{font-family:var(--fm);font-size:8px;padding:2px 7px;border-radius:10px;
  background:rgba(46,207,176,.2);color:var(--ocean-dark);
  border:1px solid rgba(46,207,176,.4);letter-spacing:.5px;font-weight:600;}
.cacts{display:flex;gap:5px;padding:0 10px 10px;}
.cbtn{
  flex:1;padding:7px 4px;border:2px solid rgba(255,255,255,.5);border-radius:8px;
  background:rgba(255,255,255,.3);color:var(--ocean-dark);
  font-family:var(--fm);font-size:9px;letter-spacing:.5px;text-transform:uppercase;
  cursor:pointer;transition:all .15s;text-align:center;text-decoration:none;
  display:block;font-weight:600;
  box-shadow:0 2px 0 rgba(0,0,0,.1);
}
.cbtn:hover{background:var(--teal);color:#fff;border-color:var(--teal);}
.cbtn.d:hover{background:var(--coral);border-color:var(--coral);}
.lb{position:fixed;inset:0;background:rgba(13,74,128,.92);
  display:flex;align-items:center;justify-content:center;
  z-index:999;backdrop-filter:blur(20px);}
.lb.h{display:none;}
.lbi{max-width:90vw;max-height:88vh;object-fit:contain;border-radius:12px;display:block;
  box-shadow:0 20px 80px rgba(0,0,0,.5),0 0 0 3px rgba(255,255,255,.3);}
.lbx{position:absolute;top:20px;right:24px;font-family:var(--fm);font-size:22px;
  color:rgba(255,255,255,.6);background:none;border:none;cursor:pointer;transition:color .15s;}
.lbx:hover{color:#fff;}
.pag{display:flex;justify-content:center;gap:6px;margin-top:36px;}
.pgb{width:34px;height:34px;display:flex;align-items:center;justify-content:center;
  border:2px solid rgba(255,255,255,.4);border-radius:8px;
  background:rgba(255,255,255,.2);
  font-family:var(--fm);font-size:11px;cursor:pointer;transition:all .15s;
  color:var(--ocean-dark);font-weight:700;backdrop-filter:blur(8px);}
.pgb.a,.pgb:hover{background:var(--yellow);color:var(--ocean-dark);border-color:var(--yellow);}
</style>
</head>
<body>

<!-- Hidden Elements for Touchless Gesture Tracking -->
<video id="video" autoplay playsinline muted style="position:fixed; bottom:0; right:0; width:1px; height:1px; opacity:0; pointer-events:none;"></video>
<canvas id="gestureCanvas" style="display:none"></canvas>

<!-- Global Touchless Cursor -->
<div class="global-cursor" id="globalCursor" style="display:none">
  <svg viewBox="0 0 40 40">
    <circle class="gc-bg" cx="20" cy="20" r="16" />
    <circle class="gc-prog" id="gcProg" cx="20" cy="20" r="16" />
  </svg>
</div>

<!-- Custom Undersea Confirm Modal -->
<div id="confirmModal" class="lb" style="display:none; z-index: 10000; background: rgba(13, 58, 128, 0.95); align-items: center; justify-content: center;">
  <div style="background: rgba(255, 255, 255, 0.95); border: 4px solid var(--yellow); border-radius: 24px; padding: 32px; max-width: 420px; width: 90%; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.3); animation: fu 0.3s ease both; backdrop-filter: blur(8px);">
    <h3 style="font-family: var(--f-display); font-size: 26px; color: var(--ocean-dark); margin-bottom: 12px; -webkit-text-stroke: 1px var(--brand-stroke); paint-order: stroke fill;">Hapus Foto? 🗑️</h3>
    <p style="font-family: var(--f-mono); font-size: 13px; color: #333; margin-bottom: 24px;" id="confirmModalMsg">Apakah Anda yakin ingin menghapus foto ini?</p>
    <div style="display: flex; gap: 14px; justify-content: center;">
      <button class="cbtn d" id="confirmModalYes" style="flex: 1; font-size: 11px; padding: 10px; font-weight: 800;">Ya, Hapus</button>
      <button class="cbtn" id="confirmModalNo" style="flex: 1; font-size: 11px; padding: 10px; font-weight: 800;">Batal</button>
    </div>
  </div>
</div>

<!-- Bubbles Animation -->
<div class="bubbles-container" style="z-index:-1;">
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
</div>

<header class="topbar">
  <div class="topbar-brand">
    <img src="../assets/logo-photobooth-undersea.png" class="topbar-logo" alt="Photobooth Undersea">
  </div>
  <div class="topbar-mid">
    <div class="status-pill"><span class="sp-dot"></span><span class="sp-text">Gallery View</span></div>
  </div>
  <div class="topbar-end">
    <a href="../index.php" class="nav-link">Back to Studio</a>
  </div>
</header>
<main>
  <div class="ph"><h1 class="pt">Gallery</h1><span class="pc" id="pc">—</span></div>
  <div id="ct"><div class="loading"><div class="spin"></div><p>Loading...</p></div></div>
  <div class="pag" id="pag"></div>
</main>
<div class="lb h" id="lb" onclick="closeLB()">
  <button class="lbx" onclick="closeLB()">&#x2715;</button>
  <img class="lbi" id="lbi" src="" alt="">
</div>
<script>
const API='<?= htmlspecialchars($apiBase) ?>';
let pg=1,pages=1;

// Custom Confirm Modal Logic
let confirmResolve = null;
function showConfirm(msg) {
  document.getElementById('confirmModalMsg').textContent = msg;
  document.getElementById('confirmModal').style.display = 'flex';
  return new Promise((resolve) => {
    confirmResolve = resolve;
  });
}
function closeConfirm(val) {
  document.getElementById('confirmModal').style.display = 'none';
  if (confirmResolve) {
    confirmResolve(val);
    confirmResolve = null;
  }
}

// Bind custom confirm buttons
document.getElementById('confirmModalYes').addEventListener('click', () => closeConfirm(true));
document.getElementById('confirmModalNo').addEventListener('click', () => closeConfirm(false));

async function load(p=1){
  pg=p;
  document.getElementById('ct').innerHTML='<div class="loading"><div class="spin"></div><p>Loading...</p></div>';
  try{
    const d=await(await fetch(`${API}get_gallery.php?page=${p}&per_page=20`)).json();
    if(!d.success) throw new Error(d.message);
    pages=d.pages||1;
    document.getElementById('pc').textContent=d.total+' sessions';
    if(!d.items.length){
      document.getElementById('ct').innerHTML='<div class="empty"><p class="et">No photos yet</p><p>Head to the studio to shoot</p></div>';
      return;
    }
    const g=document.createElement('div'); g.className='grid';
    d.items.forEach((it,i)=>{
      const c=document.createElement('div'); c.className='card'; c.style.animationDelay=i*.03+'s';
      const dt=new Date(it.created_at).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});
      c.innerHTML=`
        <img class="card-img" src="${it.strip_url}" loading="lazy" onclick="openLB('${it.strip_url}')">
        <div class="card-meta">
          <div class="csid">${it.session_id}</div>
          <div class="cdate">${dt}</div>
          <div class="ctags">
            <span class="ctag">${it.frame_count||0}/4</span>
            ${it.filter&&it.filter!=='none'?`<span class="ctag">${it.filter}</span>`:''}
            ${it.frame_style&&it.frame_style!=='none'?`<span class="ctag">${it.frame_style}</span>`:''}
          </div>
        </div>
        <div class="cacts">
          <a class="cbtn" href="${it.strip_url}" download="${it.session_id}_strip.png">DL</a>
          <button class="cbtn d" onclick="del('${it.session_id}',this)">Del</button>
        </div>`;
      g.appendChild(c);
    });
    document.getElementById('ct').innerHTML=''; document.getElementById('ct').appendChild(g);
    const pag=document.getElementById('pag'); pag.innerHTML='';
    if(pages>1) for(let i=1;i<=pages;i++){
      const b=document.createElement('button'); b.className='pgb'+(i===pg?' a':'');
      b.textContent=i; b.onclick=()=>load(i); pag.appendChild(b);
    }
  }catch(e){
    document.getElementById('ct').innerHTML=`<div class="empty"><p class="et">Error</p><p>${e.message}</p></div>`;
    U.toast('Gagal memuat galeri: ' + e.message, 'error', 3000);
  }
}

function openLB(u){ document.getElementById('lbi').src=u; document.getElementById('lb').classList.remove('h'); }
function closeLB(){ document.getElementById('lb').classList.add('h'); document.getElementById('lbi').src=''; }

async function del(id,btn){
  const yes = await showConfirm('Hapus foto sesi ' + id + '?');
  if(!yes) return;
  
  btn.textContent='...'; btn.disabled=true;
  try{
    const d=await(await fetch(`${API}delete_photo.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})})).json();
    if(d.success) {
      btn.closest('.card').remove();
      U.toast('Foto berhasil dihapus', 'success', 2000);
    } else {
      U.toast('Gagal menghapus: '+d.message, 'error', 3000);
      btn.textContent='Del';btn.disabled=false;
    }
  }catch(e){
    U.toast('Error: '+e.message, 'error', 3000);
    btn.textContent='Del';btn.disabled=false;
  }
}

// Global Touchless Gesture Logic for Gallery
async function initTouchless() {
  const videoEl = document.getElementById('video');
  const gestureCanvas = document.getElementById('gestureCanvas');
  if (!videoEl || !gestureCanvas) return;

  // Request webcam stream
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
      audio: false
    });
    videoEl.srcObject = stream;
    await videoEl.play();
  } catch (e) {
    console.error('[Gallery Touchless] Failed to access webcam:', e);
    U.toast('Kamera tidak terdeteksi untuk navigasi touchless', 'warn', 4000);
    return;
  }

  // Global Cursor Elements
  const gcEl = document.getElementById('globalCursor');
  const gcProg = document.getElementById('gcProg');
  const GC_CIRC = 2 * Math.PI * 16;
  if (gcProg) {
    gcProg.style.strokeDasharray = GC_CIRC;
    gcProg.style.strokeDashoffset = GC_CIRC;
  }

  let gHoverEl = null, gHoverStart = 0;
  let lastHitTestTime = 0;
  let lastTargetEl = null;
  const G_DWELL_MS = 1000;

  function cancelGlobalDwell() {
    if (gHoverEl) {
      gHoverEl.classList.remove('g-hover');
      gHoverEl = null;
    }
    gHoverStart = 0;
    if (gcProg) gcProg.style.strokeDashoffset = GC_CIRC;
  }

  // Listen to gesture frames
  Gesture.on('frame', (fData) => {
    if (gcEl) {
      if (fData.isPointing && fData.indexTip) {
        gcEl.style.display = 'block';
        
        const cvsW = gestureCanvas.width || 640;
        const cvsH = gestureCanvas.height || 480;
        
        // Horizontally mirrored camera mapped to screen coords
        const screenX = (1 - (fData.indexTip.x / cvsW)) * window.innerWidth;
        const screenY = (fData.indexTip.y / cvsH) * window.innerHeight;
        
        gcEl.style.transform = `translate(${screenX}px, ${screenY}px)`;
        
        const now = Date.now();
        let targetEl = lastTargetEl;

        // Throttle hit-testing to 100ms
        if (now - lastHitTestTime > 100) {
          lastHitTestTime = now;
          const hitEls = document.elementsFromPoint(screenX, screenY);
          targetEl = null;
          for (const el of hitEls) {
            const interactive = el.closest('button, a, .card-img, .pgb, .cbtn, [role="button"]');
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
          cancelGlobalDwell();
        }
      } else {
        gcEl.style.display = 'none';
        cancelGlobalDwell();
      }
    }
  });

  try {
    await Gesture.init(videoEl, gestureCanvas);
    console.log('[Gallery Touchless] Gesture engine initialized successfully');
  } catch (e) {
    console.error('[Gallery Touchless] Failed to initialize Gesture engine:', e);
  }
}

document.addEventListener('keydown',e=>{if(e.key==='Escape')closeLB();});
document.addEventListener('DOMContentLoaded', () => {
  initTouchless();
});

load(1);
</script>
</body>
</html>
