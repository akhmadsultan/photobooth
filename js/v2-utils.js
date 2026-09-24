/* v2-utils.js — Shared utilities */
'use strict';

/* ── Canvas roundRect polyfill (Safari < 15.4, older Chrome) ── */
(function(){
  const p = CanvasRenderingContext2D.prototype;
  if (p.roundRect) return;
  p.roundRect = function(x, y, w, h, r) {
    r = typeof r === 'number' ? r : (Array.isArray(r) ? r[0] : 0);
    this.moveTo(x + r, y);
    this.lineTo(x + w - r, y);
    this.quadraticCurveTo(x + w, y, x + w, y + r);
    this.lineTo(x + w, y + h - r);
    this.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    this.lineTo(x + r, y + h);
    this.quadraticCurveTo(x, y + h, x, y + h - r);
    this.lineTo(x, y + r);
    this.quadraticCurveTo(x, y, x + r, y);
    this.closePath();
    return this;
  };
})();

const U = (() => {
  /* EventEmitter */
  class Emitter {
    constructor(){ this._m={}; }
    on(e,f){ (this._m[e]||(this._m[e]=[])).push(f); return this; }
    off(e,f){ this._m[e]=( this._m[e]||[]).filter(x=>x!==f); }
    emit(e,...a){ (this._m[e]||[]).forEach(f=>f(...a)); }
    once(e,f){ const w=(...a)=>{f(...a);this.off(e,w);}; return this.on(e,w); }
  }

  /* FPS counter (rolling window) */
  class FPS {
    constructor(n=30){ this.t=[]; this.n=n; }
    tick(){ const now=performance.now(); this.t.push(now); if(this.t.length>this.n)this.t.shift(); }
    get(){ if(this.t.length<2)return 0; return Math.round((this.t.length-1)/((this.t[this.t.length-1]-this.t[0])/1000)); }
  }

  const clamp=(v,lo,hi)=>Math.min(Math.max(v,lo),hi);
  const lerp=(a,b,t)=>a+(b-a)*t;
  const sleep=ms=>new Promise(r=>setTimeout(r,ms));

  /* dataURL → Blob */
  function dataURLtoBlob(d){
    const [h,b]=d.split(',');
    const m=h.match(/:(.*?);/)[1];
    const s=atob(b); const u=new Uint8Array(s.length);
    for(let i=0;i<s.length;i++)u[i]=s.charCodeAt(i);
    return new Blob([u],{type:m});
  }

  /* Download blob */
  function download(data, name, mime='image/png'){
    const blob=data instanceof Blob?data:dataURLtoBlob(data);
    const url=URL.createObjectURL(blob);
    const a=Object.assign(document.createElement('a'),{href:url,download:name});
    document.body.appendChild(a); a.click();
    setTimeout(()=>{ document.body.removeChild(a); URL.revokeObjectURL(url); },200);
  }

  /**
   * Apply pixel-level filter to a canvas context
   * @param {CanvasRenderingContext2D} ctx
   * @param {number} w width
   * @param {number} h height
   * @param {string} filter
   */
  function applyPixelFilter(ctx, w, h, filter){
    if(!filter||filter==='none') return;
    const id=ctx.getImageData(0,0,w,h);
    const d=id.data;
    for(let i=0;i<d.length;i+=4){
      const r=d[i],g=d[i+1],b=d[i+2];
      switch(filter){
        case 'bw':{
          const v=0.299*r+0.587*g+0.114*b;
          // High-contrast B&W
          const c=clamp((v-128)*1.4+128,0,255);
          d[i]=d[i+1]=d[i+2]=c; break;
        }
        case 'sepia':{
          d[i]  =clamp(r*.393+g*.769+b*.189,0,255);
          d[i+1]=clamp(r*.349+g*.686+b*.168,0,255);
          d[i+2]=clamp(r*.272+g*.534+b*.131,0,255); break;
        }
        case 'vintage':{
          // Sepia + faded + slight vignette warmth
          const sr=clamp(r*.393+g*.769+b*.189,0,255);
          const sg=clamp(r*.349+g*.686+b*.168,0,255);
          const sb=clamp(r*.272+g*.534+b*.131,0,255);
          d[i]  =clamp(lerp(r,sr,.55)*.96+12,0,255);
          d[i+1]=clamp(lerp(g,sg,.55)*.92+8, 0,255);
          d[i+2]=clamp(lerp(b,sb,.55)*.85,   0,255); break;
        }
        case 'fade':{
          // Low-contrast faded pastel
          d[i]  =clamp(r*.75+56,0,255);
          d[i+1]=clamp(g*.75+52,0,255);
          d[i+2]=clamp(b*.75+60,0,255); break;
        }
        case 'warm':{
          d[i]  =clamp(r*1.15,0,255);
          d[i+1]=clamp(g*1.03,0,255);
          d[i+2]=clamp(b*.85, 0,255); break;
        }
      }
    }
    ctx.putImageData(id,0,0);
  }

  /* Toast helper - disabled per user request */
  function toast(msg, type='info', dur=3500){
    // Suppressed UI toast notification per user request
    console.log(`[Toast ${type}]`, msg);
  }

  return { Emitter, FPS, clamp, lerp, sleep, dataURLtoBlob, download, applyPixelFilter, toast };
})();
window.U=U;
