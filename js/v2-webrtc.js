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
   * @returns {{ dataURL:string, w:number, h:number }}
   */
  function capture(filter='none'){
    if(!active||!videoEl) throw new Error('Camera not active');
    const v=videoEl;
    const w=v.videoWidth||640, h=v.videoHeight||480;
    const cvs=document.getElementById('captureCanvas');
    cvs.width=w; cvs.height=h;
    const ctx=cvs.getContext('2d');
    // Un-mirror (video is CSS-mirrored, capture should be natural)
    ctx.save(); ctx.translate(w,0); ctx.scale(-1,1);
    ctx.drawImage(v,0,0,w,h);
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
