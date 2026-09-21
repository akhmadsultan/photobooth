/* v2-webrtc.js — WebRTC camera manager */
'use strict';

const WebCam = (() => {
  const ev = new U.Emitter();
  let stream=null, videoEl=null, active=false;

  const CONSTRAINTS = {
    video:{ width:{ideal:1280,min:640}, height:{ideal:720,min:480},
            frameRate:{ideal:30}, facingMode:'user' },
    audio:false
  };

  async function init(el){
    videoEl = el;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      const secureErr = new Error('navigator.mediaDevices is undefined. Please ensure HTTPS or localhost is used.');
      secureErr.name = 'SecurityError';
      ev.emit('error', secureErr);
      throw secureErr;
    }

    let s = null;
    let lastErr = null;

    // Multi-tier constraint fallback sequence
    const fallbackList = [
      CONSTRAINTS,
      { video: { width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
      { video: { facingMode: 'user' }, audio: false },
      { video: true, audio: false }
    ];

    for (const constraint of fallbackList) {
      try {
        s = await navigator.mediaDevices.getUserMedia(constraint);
        if (s && s.getVideoTracks().length > 0) break;
      } catch(e) {
        lastErr = e;
        console.warn('[WebCam] Constraint attempt failed:', constraint, e.name);
      }
    }

    // Try device enumeration if basic constraints failed
    if (!s) {
      try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevs = devices.filter(d => d.kind === 'videoinput');
        for (const dev of videoDevs) {
          try {
            s = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: dev.deviceId } }, audio: false });
            if (s && s.getVideoTracks().length > 0) break;
          } catch(e3) {
            lastErr = e3;
          }
        }
      } catch(eEnum) {
        console.warn('[WebCam] Enumerate devices failed:', eEnum);
      }
    }

    if (!s) {
      const finalErr = lastErr || new Error('All camera initialization attempts failed');
      ev.emit('error', finalErr);
      throw finalErr;
    }

    stream = s;
    el.srcObject = s;
    await new Promise((res, rej) => {
      el.onloadedmetadata = res;
      setTimeout(() => rej(new Error('video metadata timeout')), 8000);
    });
    await el.play();
    active = true;
    const t = s.getVideoTracks()[0];
    const cfg = t?.getSettings() || {};
    ev.emit('ready', { w: el.videoWidth, h: el.videoHeight, cfg });
    t?.addEventListener('ended', () => { active = false; ev.emit('lost'); });
    return s;
  }

  /**
   * Capture one frame, apply pixel filter, return dataURL
   * @param {string} filter
   * @param {HTMLElement} [frameEl]
   * @returns {{ dataURL:string, w:number, h:number }}
   */
  function capture(filter='none', frameEl=null){
    if(!active||!videoEl) throw new Error('Camera not active');
    const v=videoEl;
    const vw=v.videoWidth||640, vh=v.videoHeight||480;

    const targetFrame = frameEl || videoEl.parentElement || document.getElementById('cameraFrame');
    let srcX = 0, srcY = 0, srcW = vw, srcH = vh;

    if (targetFrame) {
      const fr = targetFrame.getBoundingClientRect();
      if (fr.width > 0 && fr.height > 0) {
        const frameAspect = fr.width / fr.height;
        const videoAspect = vw / vh;
        if (frameAspect > videoAspect) {
          srcH = vw / frameAspect;
          srcY = (vh - srcH) / 2;
        } else {
          srcW = vh * frameAspect;
          srcX = (vw - srcW) / 2;
        }
      }
    }

    const w = Math.round(srcW);
    const h = Math.round(srcH);
    const cvs=document.getElementById('captureCanvas');
    cvs.width=w; cvs.height=h;
    const ctx=cvs.getContext('2d');
    // Un-mirror (video is CSS-mirrored, capture should be natural)
    ctx.save(); ctx.translate(w,0); ctx.scale(-1,1);
    ctx.drawImage(v, srcX, srcY, srcW, srcH, 0, 0, w, h);
    ctx.restore();
    // Apply pixel filter
    U.applyPixelFilter(ctx,w,h,filter);
    return { dataURL:cvs.toDataURL('image/jpeg',.92), w, h };
  }

  function useVirtualCam(el) {
    videoEl = el || videoEl || document.getElementById('video');
    if (!videoEl) return;

    const cvs = document.createElement('canvas');
    cvs.width = 640;
    cvs.height = 480;
    const ctx = cvs.getContext('2d');

    function drawVirtualFrame() {
      const now = Date.now() * 0.002;
      const grad = ctx.createLinearGradient(0, 0, 640, 480);
      grad.addColorStop(0, '#240045');
      grad.addColorStop(0.5, '#7B2DC0');
      grad.addColorStop(1, '#C77DFF');
      ctx.fillStyle = grad;
      ctx.fillRect(0, 0, 640, 480);

      ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
      ctx.beginPath();
      ctx.arc(320 + Math.sin(now) * 80, 240 + Math.cos(now) * 40, 90, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = '#ffffff';
      ctx.font = 'bold 24px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('DEMO CAMERA STREAM', 320, 220);
      ctx.font = '14px monospace';
      ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
      ctx.fillText('Virtual Test Mode • Photobooth Ready', 320, 260);

      requestAnimationFrame(drawVirtualFrame);
    }
    drawVirtualFrame();

    const s = cvs.captureStream(30);
    stream = s;
    videoEl.srcObject = s;
    videoEl.play().catch(() => {});
    active = true;

    ev.emit('ready', { w: 640, h: 480, cfg: { label: 'Virtual Demo Camera' } });
    const errEl = document.getElementById('camErr');
    if (errEl) errEl.style.display = 'none';
    return s;
  }

  function stop(){
    stream?.getTracks().forEach(t=>t.stop());
    if(videoEl) videoEl.srcObject=null;
    stream=null; active=false;
  }

  function getInfo(){
    return { active, w:videoEl?.videoWidth, h:videoEl?.videoHeight };
  }

  function on(e,f){ ev.on(e,f); }
  return { init, capture, stop, getInfo, on, useVirtualCam, get active(){ return active; } };
})();
window.WebCam=WebCam;
