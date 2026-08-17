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
    videoEl=el;
    let s;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      const secureErr = new Error('navigator.mediaDevices is undefined. Please ensure HTTPS or localhost is used.');
      secureErr.name = 'SecurityError';
      ev.emit('error', secureErr);
      throw secureErr;
    }
    try {
      s=await navigator.mediaDevices.getUserMedia(CONSTRAINTS);
    } catch(e){
      console.warn('[WebCam] HD failed, fallback:', e.name);
      try { s=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false}); }
      catch(e2){ ev.emit('error',e2); throw e2; }
    }
    stream=s;
    el.srcObject=s;
    await new Promise((res,rej)=>{
      el.onloadedmetadata=res;
      setTimeout(()=>rej(new Error('timeout')),8000);
    });
    await el.play();
    active=true;
    const t=s.getVideoTracks()[0];
    const cfg=t?.getSettings()||{};
    ev.emit('ready',{ w:el.videoWidth, h:el.videoHeight, cfg });
    t?.addEventListener('ended',()=>{ active=false; ev.emit('lost'); });
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

  function stop(){
    stream?.getTracks().forEach(t=>t.stop());
    if(videoEl) videoEl.srcObject=null;
    stream=null; active=false;
  }

  function getInfo(){
    return { active, w:videoEl?.videoWidth, h:videoEl?.videoHeight };
  }

  function on(e,f){ ev.on(e,f); }
  return { init, capture, stop, getInfo, on, get active(){ return active; } };
})();
window.WebCam=WebCam;
