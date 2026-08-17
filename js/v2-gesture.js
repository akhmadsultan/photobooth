/* v2-gesture.js — MediaPipe Hands full gesture suite
   Detects: Open Palm (trigger), Pointing (☝), Pinch (🤏), V-sign (✌)
   Emits 'frame' with full gesture state every frame.
*/
'use strict';

const Gesture = (() => {
  const ev = new U.Emitter();

  const CFG = {
    holdMs:      window.PB_CFG?.holdMs     || 1500,
    cooldownMs:  window.PB_CFG?.cooldownMs || 2500,
    detectConf:  .50,   // FIX: was .70 — too strict for normal lighting
    trackConf:   .50,   // FIX: was .60
    pinchThresh: 55,   // canvas-px — thumb+index distance to count as pinch
  };

  const state = {
    running:false, enabled:true, paused:false,
    holdStart:null, holdProg:0,
    lastTrigger:0, trigCount:0,
    smoothConf:0,
    hands:null, cam:null,
    /* Latest computed gesture — read by Stickers each frame */
    gesture: {
      mode: 'none',        // 'none'|'point'|'pinch'|'vsign'
      indexTip:  null,     // {x,y} canvas px (mirrored to display)
      pinchMid:  null,     // {x,y} midpoint thumb+index (display coords)
      pinchDist: 0,        // px between thumb and index in canvas space
      isPointing: false,
      isPinching: false,
      isVSign:    false,
    },
    smoothCoords: {
      indexTip: null,
      pinchMid: null
    }
  };

  /* ── Init ──────────────────────────────────────────────── */
  async function init(videoEl, canvasEl){
    state.canvas  = canvasEl;
    state.ctx     = canvasEl.getContext('2d');
    state.videoEl = videoEl;

    state.hands = new Hands({
      locateFile: f => `https://cdn.jsdelivr.net/npm/@mediapipe/hands@0.4.1646424915/${f}`
    });
    state.hands.setOptions({
      maxNumHands:            1,
      modelComplexity:        1,
      minDetectionConfidence: CFG.detectConf,
      minTrackingConfidence:  CFG.trackConf
    });

    // FIX: emit 'ready' only after model is actually initialized (first result fires)
    let _modelReady = false;
    const _origOnResults = onResults;
    state.hands.onResults((res) => {
      if (!_modelReady) {
        _modelReady = true;
        ev.emit('ready');
      }
      _origOnResults(res);
    });

    // Set running to true to allow the manual frame loop to execute
    state.running = true;
    _manualLoop();
  }

  function _manualLoop() {
    // ── Adaptive FPS throttle ──────────────────────────────────
    // On the start page, MediaPipe runs at ~8 fps to avoid competing
    // with the background video decoder (which causes video freeze).
    // After start page is dismissed, ramp up to ~30 fps (full gesture).
    const FPS_START_PAGE = 8;   // fps while video bg is visible
    const FPS_FULL       = 30;  // fps after start page gone
    let lastSend = 0;

    const loop = async () => {
      if (!state.running) return;

      const now = performance.now();
      const onStartPage = !!document.getElementById('startPage');
      const targetFps   = onStartPage ? FPS_START_PAGE : FPS_FULL;
      const minInterval = 1000 / targetFps;

      if (state.enabled && !state.paused && state.videoEl?.readyState >= 2) {
        if (now - lastSend >= minInterval) {
          lastSend = now;
          try {
            await state.hands.send({ image: state.videoEl });
          } catch (err) {
            console.warn('[Gesture] MediaPipe send failed:', err);
          }
        }
      }
      requestAnimationFrame(loop);
    };
    requestAnimationFrame(loop);
  }

  /* ── Result handler ─────────────────────────────────────── */
  function onResults(res) {
    const cvs = state.canvas;
    if (!cvs) return;
    const targetW = state.videoEl?.videoWidth  || 640;
    const targetH = state.videoEl?.videoHeight || 480;
    if (cvs.width !== targetW || cvs.height !== targetH) {
      cvs.width  = targetW;
      cvs.height = targetH;
    }
    const ctx  = state.ctx;
    ctx.clearRect(0, 0, cvs.width, cvs.height);

    // Reset gesture state
    const g = state.gesture;
    const wasPinching = g.isPinching; // Save previous state for hysteresis
    g.mode       = 'none';
    g.indexTip   = null;
    g.pinchMid   = null;
    g.pinchDist  = 0;
    g.isPointing = false;
    g.isPinching = false;
    g.isVSign    = false;

    let palmDetected = false, bestConf = 0;

    if (res.multiHandLandmarks?.length) {
      for (let h = 0; h < res.multiHandLandmarks.length; h++) {
        const lm = res.multiHandLandmarks[h];

        // Use the FIRST detected hand for sticker gestures
        let isStickerGesture = false;
        if (h === 0) {
          const point = _detectPointing(lm, cvs.width, cvs.height);
          const pinch = _detectPinch(lm, cvs.width, cvs.height, wasPinching);
          const vsign = _detectVSign(lm, cvs.width, cvs.height);

          // Priority: vsign > pinch > point
          if (vsign.isVSign) {
            g.mode       = 'vsign';
            g.isVSign    = true;
            g.indexTip   = vsign.indexTip;
            isStickerGesture = true;
          } else if (pinch.isPinching) {
            g.mode       = 'pinch';
            g.isPinching = true;
            g.indexTip   = pinch.indexTip;
            g.pinchMid   = pinch.mid;
            g.pinchDist  = pinch.dist;
            isStickerGesture = true;
          } else if (point.isPointing) {
            g.mode       = 'point';
            g.isPointing = true;
            g.indexTip   = point.indexTip;
            isStickerGesture = true;
          }
          // Apply EMA Smoothing
          const alpha = 0.4;
          const _smooth = (target, raw) => {
            if (!raw) return null;
            if (!target || Math.hypot(target.x - raw.x, target.y - raw.y) > 80) {
              return { x: raw.x, y: raw.y }; // snap if jumped far
            }
            return {
              x: target.x + alpha * (raw.x - target.x),
              y: target.y + alpha * (raw.y - target.y)
            };
          };

          state.smoothCoords.indexTip = _smooth(state.smoothCoords.indexTip, g.indexTip);
          state.smoothCoords.pinchMid = _smooth(state.smoothCoords.pinchMid, g.pinchMid);

          // Update g with smoothed values
          if (g.indexTip) g.indexTip = state.smoothCoords.indexTip;
          if (g.pinchMid) g.pinchMid = state.smoothCoords.pinchMid;

          // Draw cursor
          _drawCursor(ctx, g, cvs.width, cvs.height);
        }

        // Open palm — only for shutter trigger (ignore if hand is doing a sticker gesture)
        const palm = _detectOpenPalm(lm, cvs.width, cvs.height);
        if (palm.isOpen && !isStickerGesture) { 
          palmDetected = true; 
          bestConf = Math.max(bestConf, palm.conf); 
        }

        // Draw confidence arc near wrist
        _drawConfArc(ctx, lm, cvs.width, cvs.height, palm.conf, palm.isOpen);
      }
    }

    state.smoothConf += .3 * ((palmDetected ? bestConf : 0) - state.smoothConf);
    _updateHold(palmDetected, bestConf, Date.now());

    ev.emit('frame', {
      detected:   palmDetected,
      conf:       state.smoothConf,
      holdProg:   state.holdProg,
      // Sticker gesture data
      mode:       g.mode,
      indexTip:   g.indexTip,
      pinchMid:   g.pinchMid,
      pinchDist:  g.pinchDist,
      isPointing: g.isPointing,
      isPinching: g.isPinching,
      isVSign:    g.isVSign,
    });
  }

  /* ── Open palm (shutter) ────────────────────────────────── */
  function _detectOpenPalm(lm, cw, ch) {
    const pt   = i => ({ x: lm[i].x * cw, y: lm[i].y * ch });
    const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    const wrist = pt(0);
    const fingers = [[8,5],[12,9],[16,13],[20,17]];
    let ext = 0;
    for (const [tip, mcp] of fingers) {
      const td = dist(pt(tip), wrist), md = dist(pt(mcp), wrist);
      // FIX: was > 1.4 (too strict). Lowered to 1.25 for better detection at angles
      if (td / Math.max(md, .1) > 1.25) ext++;
    }
    // FIX: thumbSpread threshold lowered from .75 to .55, and made non-mandatory
    const thumbSpread = dist(pt(4), pt(5)) / Math.max(dist(wrist, pt(5)), .1) > .55;
    // FIX: was ext >= 4 && thumbSpread. Now 3 fingers suffice, thumb is a bonus
    const isOpen = ext >= 3 && thumbSpread;
    const conf   = U.clamp((ext / 4) * .85 + (thumbSpread ? .15 : 0), 0, 1);
    return { isOpen, conf };
  }

  /* ── Pointing ☝ — only index finger extended ────────────── */
  function _detectPointing(lm, cw, ch) {
    const pt   = i => ({ x: lm[i].x * cw, y: lm[i].y * ch });
    const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    const wrist = pt(0);

    const indexExt = dist(pt(8), wrist) / Math.max(dist(pt(5), wrist), .1) > 1.35;
    const others   = [[12,9],[16,13],[20,17]];
    let curled = 0;
    for (const [tip, mcp] of others) {
      if (dist(pt(tip), wrist) / Math.max(dist(pt(mcp), wrist), .1) < 1.2) curled++;
    }
    return {
      isPointing: indexExt && curled >= 2,
      indexTip:   pt(8)
    };
  }

  /* ── Pinch 🤏 — thumb + index close together ─────────────── */
  function _detectPinch(lm, cw, ch, wasPinching = false) {
    const thumb = { x: lm[4].x * cw, y: lm[4].y * ch };
    const index = { x: lm[8].x * cw, y: lm[8].y * ch };
    const wrist = { x: lm[0].x * cw, y: lm[0].y * ch };
    const indexMcp = { x: lm[5].x * cw, y: lm[5].y * ch };
    
    // Hand size reference (wrist to index MCP)
    const refSize = Math.max(Math.hypot(indexMcp.x - wrist.x, indexMcp.y - wrist.y), 10);
    const dist  = Math.hypot(index.x - thumb.x, index.y - thumb.y);
    
    // Hysteresis: if already pinching, use a looser threshold so it doesn't drop easily
    const threshRatio = wasPinching ? 0.40 : 0.25;
    const threshAbs   = wasPinching ? 40 : 20;
    
    const isPinching = (dist / refSize) < threshRatio || dist < threshAbs;

    return {
      isPinching,
      dist,
      indexTip: index,
      thumbTip: thumb,
      mid: { x: (thumb.x + index.x) / 2, y: (thumb.y + index.y) / 2 }
    };
  }

  /* ── V-sign ✌ — index + middle up, ring + pinky curled ──── */
  function _detectVSign(lm, cw, ch) {
    const pt   = i => ({ x: lm[i].x * cw, y: lm[i].y * ch });
    const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    const wrist = pt(0);

    const idxExt = dist(pt(8),  wrist) / Math.max(dist(pt(5),  wrist), .1) > 1.35;
    const midExt = dist(pt(12), wrist) / Math.max(dist(pt(9),  wrist), .1) > 1.35;
    const rngCrl = dist(pt(16), wrist) / Math.max(dist(pt(13), wrist), .1) < 1.15;
    const pnkCrl = dist(pt(20), wrist) / Math.max(dist(pt(17), wrist), .1) < 1.15;

    return {
      isVSign:  idxExt && midExt && rngCrl && pnkCrl,
      indexTip: pt(8)
    };
  }

  /* ── Draw cursor dot (no text/emoji) ──────────────────────── */
  function _drawCursor(ctx, g) {
    if (!g.indexTip) return;
    const x = g.indexTip.x, y = g.indexTip.y;

    let color, r;
    if      (g.isVSign)    { color = 'rgba(247,108,108,0.9)'; r = 10; }
    else if (g.isPinching) { color = 'rgba(46,207,176,0.9)';  r = 11; }
    else if (g.isPointing) { color = 'rgba(255,255,255,0.88)'; r = 7;  }
    else return;

    ctx.save();
    // Core dot
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();
    // Outer ring
    ctx.beginPath();
    ctx.arc(x, y, r + 5, 0, Math.PI * 2);
    ctx.strokeStyle = color.replace('0.9', '0.3').replace('0.88', '0.28');
    ctx.lineWidth = 2;
    ctx.stroke();
    ctx.restore();
  }

  /* ── Confidence arc near wrist ──────────────────────────── */
  function _drawConfArc(ctx, lm, cw, ch, conf, isOpen) {
    const wx = lm[0].x * cw, wy = lm[0].y * ch;
    const r = 22, tau = Math.PI * 2;
    ctx.beginPath();
    ctx.arc(wx, wy, r, -Math.PI/2, -Math.PI/2 + tau);
    ctx.strokeStyle = 'rgba(255,255,255,.18)';
    ctx.lineWidth = 3; ctx.lineCap = 'round';
    ctx.stroke();
    if (conf > .05) {
      ctx.beginPath();
      ctx.arc(wx, wy, r, -Math.PI/2, -Math.PI/2 + conf * tau);
      ctx.strokeStyle = isOpen ? 'rgba(45,138,78,.9)' : 'rgba(28,79,216,.9)';
      ctx.lineWidth = 3; ctx.lineCap = 'round';
      ctx.stroke();
    }
    if (state.holdProg > 0) {
      ctx.beginPath();
      ctx.arc(wx, wy, r + 6, -Math.PI/2, -Math.PI/2 + state.holdProg * tau);
      ctx.strokeStyle = 'rgba(230,50,36,.9)';
      ctx.lineWidth = 2.5; ctx.lineCap = 'round';
      ctx.stroke();
    }
  }

  /* ── Hold timer (open palm → shutter) ───────────────────── */
  function _updateHold(detected, conf, now) {
    const cool = (now - state.lastTrigger) < CFG.cooldownMs;
    if (!state.enabled || cool) {
      state.holdStart = null; state.holdProg = 0; return;
    }
    if (detected) {
      if (!state.holdStart) { state.holdStart = now; ev.emit('holdStart'); }
      const el = now - state.holdStart;
      state.holdProg = U.clamp(el / CFG.holdMs, 0, 1);
      if (el >= CFG.holdMs) {
        state.holdStart = null; state.holdProg = 0;
        state.lastTrigger = now; state.trigCount++;
        ev.emit('trigger', { conf, count: state.trigCount });
      }
    } else {
      if (state.holdStart) ev.emit('holdCancel');
      state.holdStart = null; state.holdProg = 0;
    }
  }

  /* ── Controls ───────────────────────────────────────────── */
  function setEnabled(v)    { state.enabled = v; if (!v) { state.holdStart = null; state.holdProg = 0; } }
  function pause()          { state.paused = true; }
  function resume()         { state.paused = false; }
  function resetCooldown()  { state.lastTrigger = 0; }
  function stop()           { state.running = false; state.cam?.stop(); }
  function getHoldProg()    { return state.holdProg; }
  function getLandmarkState() { return { ...state.gesture }; }

  function on(e, f)  { ev.on(e, f); }
  function off(e, f) { ev.off(e, f); }

  return { init, setEnabled, pause, resume, resetCooldown, stop, getHoldProg, getLandmarkState, on, off, CFG };
})();
window.Gesture = Gesture;
