/* v2-strip.js — Photo strip canvas renderer
   Supports 6 frame styles: none, checker, stamp, oval, film, minimal
   Filter is applied pixel-level to each frame during render.          */
'use strict';

const Strip = (() => {
  const FRAME_W = 420;
  const PHOTO_W = 380;
  const PHOTO_H = 285;  // 4:3
  const PAD     = 20;
  const GAP     = 14;
  const HEADER  = 56;
  const FOOTER  = 44;

  const TOTAL_H = HEADER + PAD + 4*(PHOTO_H+GAP) - GAP + PAD + FOOTER;

  const frames = [];  // array of dataURL strings (already filtered)
  let stripCvs = null;
  let frameStyle = 'frame1';

  const presetFrameUrls = {
    frame1: 'assets/frames/frame1.png',
    frame2: 'assets/frames/frame2.png',
    frame3: 'assets/frames/frame3.png',
    frame4: 'assets/frames/Frame4.png',
    frame5: 'assets/frames/Frame5.png',
    frame6: 'assets/frames/Frame6.png'
  };
  const presetFrameImgs = {
    frame1: null,
    frame2: null,
    frame3: null,
    frame4: null,
    frame5: null,
    frame6: null
  };

  async function preloadPresetFrames() {
    const promises = Object.keys(presetFrameUrls).map(key => {
      return new Promise(resolve => {
        const img = new Image();
        img.onload = () => {
          presetFrameImgs[key] = img;
          resolve();
        };
        img.onerror = () => {
          console.error(`Failed to preload frame: ${key}`);
          resolve();
        };
        img.src = presetFrameUrls[key];
      });
    });
    await Promise.all(promises);
  }

  const uploadSettings = {
    img: null,
    mode: 'overlay',
    bgColor: '#ffffff'
  };

  function init(canvas){
    stripCvs=canvas;
    stripCvs.width=FRAME_W;
    stripCvs.height=TOTAL_H;
  }

  /* Add a captured dataURL frame */
  function add(dataURL){
    if(frames.length>=4) return frames.length-1;
    frames.push(dataURL);
    _render();
    return frames.length-1;
  }

  function setFrameStyle(s){ frameStyle=s; _render(); }
  function getFrameStyle(){ return frameStyle; }
  function count(){ return frames.length; }
  function isComplete(){ return frames.length>=4; }
  function reset(){ frames.length=0; _render(); }
  function getFrames(){ return [...frames]; }

  /* Remove only the last captured frame (retake) */
  function retakeLast(){
    if(frames.length === 0) return 0;
    frames.pop();
    _render();
    return frames.length;
  }


  function setUploadedFrame(img, mode, bgColor) {
    if (img !== undefined) uploadSettings.img = img;
    if (mode !== undefined) uploadSettings.mode = mode;
    if (bgColor !== undefined) uploadSettings.bgColor = bgColor;
    _render();
  }
  function getUploadedSettings(){ return uploadSettings; }

  /* Get final PNG dataURL — fully renders all frames synchronously */
  async function getDataURL(){
    if(!stripCvs) return null;
    // Draw background + header + footer first
    const ctx = stripCvs.getContext('2d');
    _drawBackground(ctx);
    if (frameStyle !== 'upload' && !presetFrameImgs[frameStyle]) {
      _drawHeader(ctx);
      _drawFooter(ctx);
    }

    // Draw all photo frames, waiting for each image to load
    const L = { x: PAD, frameH: PHOTO_H, frameW: PHOTO_W, headerH: HEADER, gap: GAP };
    const promises = frames.map((url, i) => {
      const y = HEADER + PAD + i * (PHOTO_H + GAP);
      return _drawPhotoAsync(ctx, url, PAD, y, PHOTO_W, PHOTO_H, i);
    });
    // Draw empty slots synchronously
    for(let i = frames.length; i < 4; i++){
      const y = HEADER + PAD + i * (PHOTO_H + GAP);
      _drawEmpty(ctx, PAD, y, PHOTO_W, PHOTO_H, i);
    }
    await Promise.all(promises);

    // Redraw header/footer on top of photos
    if (frameStyle !== 'upload' && !presetFrameImgs[frameStyle]) {
      _drawHeader(ctx);
      _drawFooter(ctx);
    } else if (frameStyle === 'upload' && uploadSettings.mode === 'overlay' && uploadSettings.img) {
      ctx.drawImage(uploadSettings.img, 0, 0, FRAME_W, TOTAL_H);
    } else if (presetFrameImgs[frameStyle]) {
      ctx.drawImage(presetFrameImgs[frameStyle], 0, 0, FRAME_W, TOTAL_H);
    }

    return stripCvs.toDataURL('image/png');
  }

  /* ── Main render ───────────────────────────────────── */
  function _render(){
    if(!stripCvs) return;
    const ctx=stripCvs.getContext('2d');
    _drawBackground(ctx);
    if (frameStyle !== 'upload' && !presetFrameImgs[frameStyle]) {
      _drawHeader(ctx);
    }
    for(let i=0;i<4;i++){
      const y=HEADER+PAD+i*(PHOTO_H+GAP);
      if(i<frames.length) _drawPhoto(ctx,frames[i],PAD,y,PHOTO_W,PHOTO_H,i);
      else                 _drawEmpty(ctx,PAD,y,PHOTO_W,PHOTO_H,i);
    }
    if (frameStyle !== 'upload' && !presetFrameImgs[frameStyle]) {
      _drawFooter(ctx);
    }
    if (frameStyle === 'upload' && uploadSettings.mode === 'overlay' && uploadSettings.img) {
      ctx.drawImage(uploadSettings.img, 0, 0, FRAME_W, TOTAL_H);
    } else if (presetFrameImgs[frameStyle]) {
      ctx.drawImage(presetFrameImgs[frameStyle], 0, 0, FRAME_W, TOTAL_H);
    }
    // Show canvas, hide placeholder
    stripCvs.style.display='block';
    const ph=document.getElementById('stripPh');
    if(ph) ph.style.display='none';
  }

  async function _renderAsync(){
    _render();
    // Reload all images to ensure they're fully painted
    const promises=frames.map((url,i)=>{
      const y=HEADER+PAD+i*(PHOTO_H+GAP);
      return _drawPhotoAsync(stripCvs.getContext('2d'),url,PAD,y,PHOTO_W,PHOTO_H,i);
    });
    await Promise.all(promises);
    if (frameStyle !== 'upload' && !presetFrameImgs[frameStyle]) {
      _drawHeader(stripCvs.getContext('2d'));
      _drawFooter(stripCvs.getContext('2d'));
    } else if (frameStyle === 'upload' && uploadSettings.mode === 'overlay' && uploadSettings.img) {
      stripCvs.getContext('2d').drawImage(uploadSettings.img, 0, 0, FRAME_W, TOTAL_H);
    } else if (presetFrameImgs[frameStyle]) {
      stripCvs.getContext('2d').drawImage(presetFrameImgs[frameStyle], 0, 0, FRAME_W, TOTAL_H);
    }
  }

  /* ── Background styles ─────────────────────────────── */
  function _drawBackground(ctx){
    ctx.save();
    switch(frameStyle){
      case 'frame1':
      case 'frame2':
      case 'frame3':
      case 'frame4':
      case 'frame5':
      case 'frame6':
      case 'upload':{
        if (frameStyle === 'upload' && uploadSettings.mode === 'background' && uploadSettings.img) {
          ctx.drawImage(uploadSettings.img, 0, 0, FRAME_W, TOTAL_H);
        } else {
          ctx.fillStyle = (frameStyle === 'upload' ? uploadSettings.bgColor : '#ffffff') || '#ffffff';
          ctx.fillRect(0,0,FRAME_W,TOTAL_H);
        }
        break;
      }
      case 'checker':{
        // Light blue / white checker (ocean tiles)
        ctx.fillStyle='#5bc8f0';
        ctx.fillRect(0,0,FRAME_W,TOTAL_H);
        ctx.fillStyle='#ffffff';
        const sq=14;
        for(let r=0;r<Math.ceil(TOTAL_H/sq);r++)
          for(let c=0;c<Math.ceil(FRAME_W/sq);c++)
            if((r+c)%2===0) ctx.fillRect(c*sq,r*sq,sq,sq);
        // Semi-transparent overlay
        ctx.fillStyle='rgba(91,200,240,.5)';
        ctx.fillRect(8,8,FRAME_W-16,TOTAL_H-16);
        break;
      }
      case 'film':{
        ctx.fillStyle='#0d3a6e';
        ctx.fillRect(0,0,FRAME_W,TOTAL_H);
        // Film holes - yellow like pineapple holes
        const holeW=12,holeH=9,holeGap=22;
        ctx.fillStyle='rgba(247,215,22,.35)';
        for(let y=24;y<TOTAL_H-20;y+=holeGap){
          ctx.beginPath(); ctx.roundRect(4,y,holeW,holeH,2); ctx.fill();
          ctx.beginPath(); ctx.roundRect(FRAME_W-4-holeW,y,holeW,holeH,2); ctx.fill();
        }
        break;
      }
      case 'stamp':{
        // Sandy ocean floor colors
        ctx.fillStyle='#a8e4f0';
        ctx.fillRect(0,0,FRAME_W,TOTAL_H);
        ctx.setLineDash([8,5]);
        ctx.strokeStyle='rgba(247,108,108,.7)';
        ctx.lineWidth=2;
        ctx.strokeRect(12,12,FRAME_W-24,TOTAL_H-24);
        ctx.setLineDash([]);
        // Coral top/bottom bars
        const csz=10;
        for(let x=0;x<FRAME_W;x+=csz*2){
          ctx.fillStyle='#f76c6c'; ctx.fillRect(x,0,csz,14);
          ctx.fillStyle='#2ecfb0'; ctx.fillRect(x+csz,0,csz,14);
          ctx.fillStyle='#2ecfb0'; ctx.fillRect(x,TOTAL_H-14,csz,14);
          ctx.fillStyle='#f76c6c'; ctx.fillRect(x+csz,TOTAL_H-14,csz,14);
        }
        break;
      }
      case 'oval':{
        // Gradient ocean
        const g=ctx.createLinearGradient(0,0,0,TOTAL_H);
        g.addColorStop(0,'#7de8ff');
        g.addColorStop(0.5,'#3aafde');
        g.addColorStop(1,'#1a7ab8');
        ctx.fillStyle=g; ctx.fillRect(0,0,FRAME_W,TOTAL_H);
        // Bubble decoration
        ctx.strokeStyle='rgba(255,255,255,.3)'; ctx.lineWidth=1.5;
        [[80,200,18],[340,380,12],[60,500,8],[350,150,10]].forEach(([x,y,r])=>{
          ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2); ctx.stroke();
        });
        break;
      }
      case 'minimal':{
        ctx.fillStyle='#e8f8ff';
        ctx.fillRect(0,0,FRAME_W,TOTAL_H);
        ctx.strokeStyle='#2ecfb0'; ctx.lineWidth=3;
        ctx.strokeRect(2,2,FRAME_W-4,TOTAL_H-4);
        break;
      }
      default:{ // none — ocean gradient
        const g=ctx.createLinearGradient(0,0,0,TOTAL_H);
        g.addColorStop(0,'#7de8ff');
        g.addColorStop(0.3,'#4dc8f0');
        g.addColorStop(0.7,'#2295cc');
        g.addColorStop(1,'#0e6aaa');
        ctx.fillStyle=g; ctx.fillRect(0,0,FRAME_W,TOTAL_H);
        // Subtle bubble highlights
        [[60,150,35],[340,300,25],[80,600,20],[350,500,30]].forEach(([x,y,r])=>{
          const bg=ctx.createRadialGradient(x,y,0,x,y,r);
          bg.addColorStop(0,'rgba(255,255,255,.18)');
          bg.addColorStop(1,'transparent');
          ctx.fillStyle=bg; ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2); ctx.fill();
        });
      }
    }
    ctx.restore();
  }

  /* ── Photo frame drawing ───────────────────────────── */
  function _drawPhoto(ctx, url, x, y, w, h, idx){
    const img=new Image();
    img.onload=()=>{
      _paintPhoto(ctx,img,x,y,w,h,idx);
      if (frameStyle !== 'upload' && !presetFrameImgs[frameStyle]) {
        _drawHeader(ctx);
        _drawFooter(ctx);
      } else if (frameStyle === 'upload' && uploadSettings.mode === 'overlay' && uploadSettings.img) {
        ctx.drawImage(uploadSettings.img, 0, 0, FRAME_W, TOTAL_H);
      } else if (presetFrameImgs[frameStyle]) {
        ctx.drawImage(presetFrameImgs[frameStyle], 0, 0, FRAME_W, TOTAL_H);
      }
    };
    img.src=url;
  }

  function _drawPhotoAsync(ctx, url, x, y, w, h, idx){
    return new Promise(res=>{
      const img=new Image();
      img.onload=()=>{ _paintPhoto(ctx,img,x,y,w,h,idx); res(); };
      img.onerror=res;
      img.src=url;
    });
  }

  function _paintPhoto(ctx, img, x, y, w, h, idx){
    ctx.save();
    switch(frameStyle){
      case 'frame1':
      case 'frame2':
      case 'frame3':
      case 'frame4':
      case 'frame5':
      case 'frame6':
      case 'upload':{
        _fitImage(ctx,img,x,y,w,h);
        ctx.restore();
        ctx.save();
        break;
      }
      case 'oval':{
        // Oval clip
        ctx.beginPath();
        ctx.ellipse(x+w/2, y+h/2, w/2-2, h/2-2, 0, 0, Math.PI*2);
        ctx.clip();
        _fitImage(ctx,img,x,y,w,h);
        ctx.restore();
        // Oval border — teal
        ctx.save();
        ctx.strokeStyle='#2ecfb0';
        ctx.lineWidth=3;
        ctx.shadowColor='rgba(46,207,176,.5)';
        ctx.shadowBlur=6;
        ctx.beginPath();
        ctx.ellipse(x+w/2,y+h/2,w/2-1,h/2-1,0,0,Math.PI*2);
        ctx.stroke();
        ctx.shadowBlur=0;
        break;
      }
      case 'stamp':{
        _fitImage(ctx,img,x,y,w,h);
        ctx.restore();
        ctx.save();
        // Coral/red stamp border
        ctx.strokeStyle='#f76c6c';
        ctx.lineWidth=3;
        ctx.strokeRect(x,y,w,h);
        break;
      }
      case 'film':{
        _fitImage(ctx,img,x,y,w,h);
        ctx.restore();
        ctx.save();
        ctx.strokeStyle='rgba(247,215,22,.4)';
        ctx.lineWidth=1.5;
        ctx.strokeRect(x,y,w,h);
        break;
      }
      default:{
        const rad=frameStyle==='minimal'?0:8;
        if(rad>0){
          ctx.beginPath(); ctx.roundRect(x,y,w,h,rad); ctx.clip();
        }
        _fitImage(ctx,img,x,y,w,h);
        ctx.restore();
        if(frameStyle!=='none'){
          ctx.save();
          // White bubble-like border
          ctx.strokeStyle=frameStyle==='checker'
            ?'rgba(255,255,255,.6)':'rgba(255,255,255,.4)';
          ctx.lineWidth=2.5;
          if(rad>0){ ctx.beginPath(); ctx.roundRect(x,y,w,h,rad); ctx.stroke(); }
          else ctx.strokeRect(x,y,w,h);
        }
      }
    }
    ctx.restore();
  }

  function _fitImage(ctx, img, x, y, w, h){
    const ia=img.width/img.height, fa=w/h;
    let sx,sy,sw,sh;
    if(ia>fa){ sh=img.height; sw=sh*fa; sx=(img.width-sw)/2; sy=0; }
    else { sw=img.width; sh=sw/fa; sx=0; sy=(img.height-sh)/2; }
    ctx.drawImage(img,sx,sy,sw,sh,x,y,w,h);
  }

  function _drawEmpty(ctx, x, y, w, h, idx){
    ctx.save();
    // Semi-transparent water fill
    ctx.fillStyle = 'rgba(255,255,255,.12)';
    ctx.beginPath(); ctx.roundRect(x,y,w,h,8); ctx.fill();
    // Dashed border — white bubble style
    ctx.strokeStyle = 'rgba(255,255,255,.35)';
    ctx.lineWidth = 2; ctx.setLineDash([8,5]);
    ctx.beginPath(); ctx.roundRect(x,y,w,h,8); ctx.stroke();
    ctx.setLineDash([]);
    // Frame number — white with outline
    ctx.font = `bold 32px Arial, sans-serif`;
    ctx.lineWidth = 3; ctx.lineJoin = 'round';
    ctx.strokeStyle = 'rgba(13,58,94,.4)';
    ctx.strokeText(idx+1, x+w/2, y+h/2);
    ctx.fillStyle = 'rgba(255,255,255,.35)';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(idx+1, x+w/2, y+h/2);
    ctx.restore();
  }

  /* ── Header / Footer ───────────────────────────────── */
  function _drawHeader(ctx){
    ctx.save();
    const cy  = Math.round(HEADER / 2);
    const BOLD = `bold 20px Arial, "Helvetica Neue", sans-serif`;
    const MONO = `400 9px "Courier New", monospace`;

    ctx.textBaseline = 'middle';
    ctx.textAlign    = 'left';

    // "PHOTO" — white with dark outline
    ctx.font        = BOLD;
    ctx.lineWidth   = 4;
    ctx.strokeStyle = 'rgba(13,58,94,.8)';
    ctx.lineJoin    = 'round';
    ctx.strokeText('PHOTO', PAD, cy);
    ctx.fillStyle   = '#ffffff';
    ctx.fillText('PHOTO', PAD, cy);
    const photoW = ctx.measureText('PHOTO').width;

    // "BOOTH" — yellow with dark outline
    ctx.strokeText('BOOTH', PAD + photoW, cy);
    ctx.fillStyle = '#f7d716';
    ctx.fillText('BOOTH', PAD + photoW, cy);
    const boothW = ctx.measureText('BOOTH').width;

    // "UNDERSEA" — small teal label below
    ctx.font      = `bold 8px "Courier New", monospace`;
    ctx.fillStyle = '#2ecfb0';
    ctx.textBaseline = 'top';
    ctx.fillText('UNDERSEA', PAD, cy + 11);
    ctx.textBaseline = 'middle';
    ctx.font = BOLD;

    // Right: frame count — white
    ctx.font        = MONO;
    ctx.strokeStyle = 'rgba(13,58,94,.6)';
    ctx.lineWidth   = 2;
    ctx.textAlign   = 'right';
    ctx.strokeText(`${frames.length} / 4`, FRAME_W - PAD, cy);
    ctx.fillStyle   = 'rgba(255,255,255,.8)';
    ctx.fillText(`${frames.length} / 4`, FRAME_W - PAD, cy);

    // Wavy divider line
    ctx.beginPath();
    ctx.lineWidth = 2; ctx.setLineDash([]);
    const y0 = HEADER - 2;
    for(let x = PAD; x <= FRAME_W - PAD; x += 10){
      const wave = Math.sin((x - PAD) * 0.2) * 2;
      x === PAD ? ctx.moveTo(x, y0 + wave) : ctx.lineTo(x, y0 + wave);
    }
    ctx.strokeStyle = 'rgba(255,255,255,.5)';
    ctx.stroke();
    ctx.restore();
  }

  function _drawFooter(ctx){
    ctx.save();
    const footerY = TOTAL_H - FOOTER;
    const midY    = footerY + FOOTER / 2;

    // Wavy top divider
    ctx.beginPath();
    ctx.lineWidth = 2; ctx.strokeStyle = 'rgba(255,255,255,.4)';
    for(let x = PAD; x <= FRAME_W - PAD; x += 8){
      const wave = Math.sin((x - PAD) * 0.25) * 2;
      x === PAD ? ctx.moveTo(x, footerY + 3 + wave) : ctx.lineTo(x, footerY + 3 + wave);
    }
    ctx.stroke();

    const MONO = `400 9px "Courier New", monospace`;
    ctx.font         = MONO;
    ctx.textBaseline = 'middle';

    // Session ID — white
    ctx.fillStyle = 'rgba(255,255,255,.6)';
    ctx.textAlign = 'left';
    ctx.fillText(window.PB_CFG?.sessionId || 'SESSION', PAD, midY);

    // Date — white
    ctx.textAlign = 'right';
    const d = new Date();
    ctx.fillText(
      `${d.getFullYear()}.${String(d.getMonth()+1).padStart(2,'0')}.${String(d.getDate()).padStart(2,'0')}`,
      FRAME_W - PAD, midY
    );

    // Center — yellow cartoon tagline
    ctx.textAlign  = 'center';
    ctx.lineWidth  = 2;
    ctx.lineJoin   = 'round';
    ctx.strokeStyle = 'rgba(13,58,94,.6)';
    ctx.strokeText('Immersive Fiesta Studio', FRAME_W / 2, midY);
    ctx.fillStyle  = '#C77DFF';
    ctx.fillText('Immersive Fiesta Studio', FRAME_W / 2, midY);


    // Tiny bubble dots along edges
    ctx.fillStyle = 'rgba(255,255,255,.25)';
    [6, 10, 14].forEach((r, i) => {
      ctx.beginPath();
      ctx.arc(8, footerY + 8 + i * 10, r / 2, 0, Math.PI * 2);
      ctx.fill();
    });
    [6, 10, 14].forEach((r, i) => {
      ctx.beginPath();
      ctx.arc(FRAME_W - 8, footerY + 8 + i * 10, r / 2, 0, Math.PI * 2);
      ctx.fill();
    });

    ctx.restore();
  }

  return { init, add, setFrameStyle, getFrameStyle, count, isComplete, reset, retakeLast, getFrames, getDataURL, setUploadedFrame, getUploadedSettings, preloadPresetFrames };

})();
window.Strip=Strip;
