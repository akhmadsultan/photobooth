/**
 * v2-stickers.js — Hand-Gesture Sticker System
 *
 * GESTURE MAP:
 *  ☝  POINT  (telunjuk) → arahkan ke icon di shelf 0.65s → stiker SPAWN
 *  🤏 PINCH  (jempol+telunjuk rapat) → grab stiker → geser (MOVE only, no scale)
 *  ✌  V-SIGN (telunjuk+tengah) → tahan 1s di atas stiker → DELETE
 *
 * Fallback: klik/tap icon → spawn, mouse drag → move, × → delete
 */
'use strict';

const Stickers = (() => {

  /* ── Catalog ─────────────────────────────────────────────── */
  const CATALOG = [
    { id: 'pulau1', label: 'Pulau 1', type: 'img', src: 'assets/stickers/pulau1.png' },
    { id: 'pulau2', label: 'Pulau 2', type: 'img', src: 'assets/stickers/pulau2.png' },
    { id: 'pulau3', label: 'Pulau 3', type: 'img', src: 'assets/stickers/pulau3.png' },
    { id: 'pulau4', label: 'Pulau 4', type: 'img', src: 'assets/stickers/pulau4.png' },
    { id: 'mascot', label: 'Mascot',  type: 'img', src: 'assets/stickers/mascot.png' },
    { id: 'crown',  label: 'Crown',   type: 'img', src: 'assets/stickers/crown.png' }
  ];

  /* ── Constants ───────────────────────────────────────────── */
  const SPAWN_SIZE  = 130;   // px — bigger = easier to select with finger
  const DWELL_MS    = 650;   // ms to hold finger on shelf icon before spawn
  const DEL_HOLD_MS = 1000;  // ms to hold V-sign before delete fires

  /* ── State ───────────────────────────────────────────────── */
  let stickers  = [];
  let selected  = null;
  let dragState = null;
  let overlayEl = null;
  let _evBound  = false;
  let selectionTimer = null;

  /* Pinch grab state */
  const pinch = {
    active: false, sticker: null,
    isResize: false, initSize: 0,
    initMidX: 0, initMidY: 0,
    initStkX: 0, initStkY: 0
  };

  /* V-sign delete state */
  const vDel = { sticker: null, startTime: null };

  /* Dwell state per icon { defId → { startTime, spawned, progEl, CIRC } } */
  const dwell = {};

  /* ── Feature Toggle (Deactivated by default) ─────────────── */
  let _enabled = false;

  /* ── Init ────────────────────────────────────────────────── */
  function init(overlayElement) {
    overlayEl = overlayElement;
    if (!_enabled) {
      if (overlayEl) overlayEl.style.display = 'none';
      const shelf = document.getElementById('stickerShelf');
      if (shelf) shelf.style.display = 'none';
      const btnClear = document.getElementById('btnClearStickers');
      if (btnClear) btnClear.style.display = 'none';
      return;
    }
    _renderShelf();
    _bindMouseEvents();
    _bindGestureEvents();
  }

  /* ── Build balanced shelf (left 3 | center | right 3) ──── */
  function _renderShelf() {
    ['stkTrayLeft', 'stkTrayRight', 'shelfItems'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.innerHTML = '';
    });

    const leftGrp  = document.getElementById('shelfGroupLeft');
    const rightGrp = document.getElementById('shelfGroupRight');
    if (!leftGrp || !rightGrp) return;
    leftGrp.innerHTML = rightGrp.innerHTML = '';

    const CIRC = 2 * Math.PI * 17;
    const ns   = 'http://www.w3.org/2000/svg';

    CATALOG.forEach((def, i) => {
      const btn = document.createElement('button');
      btn.className         = 'shelf-stk-btn g-click';
      btn.id                = `shelf-btn-${def.id}`;
      btn.title             = def.label;
      btn.dataset.label     = def.label;
      btn.dataset.stickerId = def.id;

      if (def.type === 'img') {
        const img = document.createElement('img');
        img.src = def.src; img.alt = def.label; img.draggable = false;
        btn.appendChild(img);
      } else {
        btn.innerHTML = def.svg;
      }

      btn.addEventListener('click', () => _addSticker(def));

      (i < 3 ? leftGrp : rightGrp).appendChild(btn);
    });
  }

  /* ── Bind gesture events ─────────────────────────────────── */
  function _bindGestureEvents() {
    const tryBind = () => {
      if (typeof Gesture === 'undefined') { setTimeout(tryBind, 300); return; }
      Gesture.on('frame', _onFrame);
    };
    tryBind();
  }

  /* ── Main gesture frame handler ──────────────────────────── */
  function _onFrame({ mode, indexTip, pinchMid, pinchDist, isPointing, isPinching, isVSign }) {
    const cameraFrame   = document.getElementById('cameraFrame');
    const gestureCanvas = document.getElementById('gestureCanvas');
    if (!cameraFrame || !gestureCanvas) return;

    const frameRect = cameraFrame.getBoundingClientRect();
    const cvsW = gestureCanvas.width  || 640;
    const cvsH = gestureCanvas.height || 480;

    /* Canvas landmark px → mirrored frame display px (handling object-fit:cover) */
    const toFrame = pt => {
      if (!pt) return null;
      const nx = pt.x / cvsW;
      const ny = pt.y / cvsH;

      const vRatio = cvsW / cvsH;
      const fRatio = frameRect.width / frameRect.height;
      let scale, offsetX = 0, offsetY = 0;

      if (fRatio > vRatio) {
        // frame is wider: scale to fit width, crop height
        scale = frameRect.width / cvsW;
        offsetY = (frameRect.height - cvsH * scale) / 2;
      } else {
        // frame is taller: scale to fit height, crop width
        scale = frameRect.height / cvsH;
        offsetX = (frameRect.width - cvsW * scale) / 2;
      }

      // 1 - nx because camera is horizontally mirrored
      return {
        x: offsetX + (1 - nx) * (cvsW * scale),
        y: offsetY + ny * (cvsH * scale)
      };
    };

    const fingerPx = toFrame(indexTip);
    const midPx    = pinchMid ? toFrame(pinchMid) : null;

    _setModeHint(mode);

    if (isVSign) {
      _cancelPinch();
      _handleVDelete(fingerPx);
    } else if (isPinching) {
      _cancelVDelete();
      _handlePinch(midPx);
    } else if (isPointing) {
      _cancelPinch();
      _cancelVDelete();
      _handlePoint(fingerPx);
    } else {
      _cancelPinch();
      _cancelVDelete();
    }
  }

  /* ── Mode hint label ─────────────────────────────────────── */
  function _setModeHint(mode) {
    const el = document.getElementById('shelfModeHint');
    if (!el) return;
    const MAP = {
      none:  ['Arahkan jari', ''],
      point: ['Tunjuk dengan kursor ungu', 'mode-point'],
      pinch: ['Grab / Scale stiker', 'mode-grab'],
      vsign: ['Hapus stiker', 'mode-delete'],
    };
    const [text, cls] = MAP[mode] || MAP.none;
    el.textContent = text;
    el.className   = 'shelf-mode-hint' + (cls ? ' ' + cls : '');
  }

  /* ── POINT → SELECT STICKER ON CANVAS ─────────────────────── */
  function _handlePoint(fingerPx) {
    if (!fingerPx) return;
    /* Over sticker on canvas → select it */
    for (let i = stickers.length - 1; i >= 0; i--) {
      const s = stickers[i];
      if (!s.el) continue;
      const cx = s.x + s.size / 2;
      const cy = s.y + s.size / 2;
      if (Math.hypot(fingerPx.x - cx, fingerPx.y - cy) < s.size / 2 + 15) {
        _selectSticker(s);
        break;
      }
    }
  }


  /* ── PINCH → MOVE OR RESIZE ──────────────────────────────── */
  function _handlePinch(midPx) {
    if (!midPx) { _cancelPinch(); return; }

    if (!pinch.active) {
      /* Search for sticker under pinch midpoint */
      for (let i = stickers.length - 1; i >= 0; i--) {
        const s = stickers[i];
        if (!s.el) continue;

        // 1. Check resize handle hit (bottom-right corner)
        const rx = s.x + s.size;
        const ry = s.y + s.size;
        if (s.el.classList.contains('selected') && Math.hypot(midPx.x - rx, midPx.y - ry) < 45) {
          pinch.active   = true;
          pinch.sticker  = s;
          pinch.isResize = true;
          pinch.initSize = s.size;
          pinch.initMidX = midPx.x;
          pinch.initMidY = midPx.y;
          _selectSticker(s);
          s.el.classList.add('hand-scaling');
          s.el.classList.remove('hand-grabbed');
          window.SFX?.grab?.();
          return;
        }

        // 2. Check center grab (large hit area)
        const cx  = s.x + s.size / 2;
        const cy  = s.y + s.size / 2;
        if (Math.hypot(midPx.x - cx, midPx.y - cy) < s.size / 2 + 30) {
          pinch.active   = true;
          pinch.sticker  = s;
          pinch.isResize = false;
          pinch.initMidX = midPx.x;
          pinch.initMidY = midPx.y;
          pinch.initStkX = s.x;
          pinch.initStkY = s.y;
          _selectSticker(s);
          s.el.classList.add('hand-grabbed');
          s.el.classList.remove('hand-scaling');
          window.SFX?.grab?.();
          return;
        }
      }
      return;
    }

    /* Move or Scale grabbed sticker */
    const s = pinch.sticker;
    if (!s?.el) { _cancelPinch(); return; }

    _resetSelectionTimer();

    if (pinch.isResize) {
      const dx = midPx.x - pinch.initMidX;
      const dy = midPx.y - pinch.initMidY;
      const deltaScale = (dx + dy) * 0.707; // Project onto diagonal for smooth continuous scaling
      const newSize = Math.max(40, Math.min(400, pinch.initSize + deltaScale));
      s.size = newSize;
      s.el.style.width  = newSize + 'px';
      s.el.style.height = newSize + 'px';
    } else {
      s.x = pinch.initStkX + (midPx.x - pinch.initMidX);
      s.y = pinch.initStkY + (midPx.y - pinch.initMidY);
      s.el.style.left = s.x + 'px';
      s.el.style.top  = s.y + 'px';
    }
  }

  function _cancelPinch() {
    if (pinch.active) window.SFX?.drop?.();
    pinch.sticker?.el?.classList.remove('hand-grabbed', 'hand-scaling');
    pinch.active  = false;
    pinch.sticker = null;
  }

  /* ── V-SIGN → DWELL → DELETE ─────────────────────────────── */
  function _handleVDelete(fingerPx) {
    if (!fingerPx) { _cancelVDelete(); return; }
    const now = Date.now();

    /* Find sticker under finger */
    let hit = null;
    for (let i = stickers.length - 1; i >= 0; i--) {
      const s = stickers[i];
      if (!s.el) continue;
      const cx = s.x + s.size / 2;
      const cy = s.y + s.size / 2;
      if (Math.hypot(fingerPx.x - cx, fingerPx.y - cy) < s.size / 2 + 25) {
        hit = s; break;
      }
    }

    if (!hit) { _cancelVDelete(); return; }

    /* Switch target if different sticker */
    if (vDel.sticker?.id !== hit.id) {
      _cancelVDelete();
      vDel.sticker   = hit;
      vDel.startTime = now;
      hit.el.classList.add('hand-deleting');
      _attachDeleteRing(hit.el);
    }

    /* Update countdown ring */
    const prog   = Math.min((now - vDel.startTime) / DEL_HOLD_MS, 1);
    const ringEl = hit.el.querySelector('.del-prog');
    if (ringEl) ringEl.style.strokeDashoffset = 200 * (1 - prog);

    if (prog >= 1) {
      _popSticker(hit);
      _cancelVDelete();
    }
  }

  function _attachDeleteRing(el) {
    if (el.querySelector('.stk-delete-ring')) return;
    const ns  = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('class', 'stk-delete-ring');
    svg.setAttribute('viewBox', '0 0 72 72');

    const bg = document.createElementNS(ns, 'circle');
    ['cx','cy','r'].forEach((a,v) => bg.setAttribute(a, [36,36,32][v]));
    bg.setAttribute('fill', 'none');
    bg.setAttribute('stroke', 'rgba(247,108,108,.2)');
    bg.setAttribute('stroke-width', '5');

    const fg = document.createElementNS(ns, 'circle');
    ['cx','cy','r'].forEach((a,v) => fg.setAttribute(a, [36,36,32][v]));
    fg.setAttribute('class', 'del-prog');
    fg.setAttribute('fill', 'none');
    fg.setAttribute('stroke', '#f76c6c');
    fg.setAttribute('stroke-width', '5');
    fg.setAttribute('stroke-linecap', 'round');
    fg.setAttribute('stroke-dasharray', '200');
    fg.setAttribute('stroke-dashoffset', '200');
    fg.setAttribute('transform', 'rotate(-90 36 36)');

    svg.appendChild(bg); svg.appendChild(fg);
    el.appendChild(svg);
  }

  function _cancelVDelete() {
    if (vDel.sticker?.el) {
      vDel.sticker.el.classList.remove('hand-deleting');
      vDel.sticker.el.querySelector('.stk-delete-ring')?.remove();
    }
    vDel.sticker   = null;
    vDel.startTime = null;
  }

  function _popSticker(data) {
    if (!data.el) return;
    window.SFX?.del?.();
    data.el.classList.add('popping');
    data.el.classList.remove('hand-deleting', 'hand-grabbed', 'selected');
    setTimeout(() => data.el?.remove(), 300);
    stickers = stickers.filter(s => s.id !== data.id);
    if (selected?.id   === data.id) selected = null;
    if (pinch.sticker?.id === data.id) { pinch.active = false; pinch.sticker = null; }
  }

  /* ── Add sticker to overlay ──────────────────────────────── */
  function _addSticker(def) {
    const container = overlayEl || document.getElementById('stickerOverlay');
    if (!container) return;
    if (!overlayEl) overlayEl = container;

    const rect = container.getBoundingClientRect();
    /* Spawn at centre with small random jitter */
    const x = rect.width  / 2 - SPAWN_SIZE / 2 + (Math.random() - 0.5) * 80;
    const y = rect.height / 2 - SPAWN_SIZE / 2 + (Math.random() - 0.5) * 80;

    window.SFX?.spawn?.();

    const data = { id: Date.now(), def, x, y, size: SPAWN_SIZE, rotation: 0, el: null };
    stickers.push(data);
    _createEl(data, container);
  }

  /* ── Create sticker DOM element ──────────────────────────── */
  function _createEl(data, container) {
    const wrap = document.createElement('div');
    wrap.className = 'sticker-item spawning';
    wrap.dataset.stickerId = data.id;
    wrap.style.cssText = `
      position:absolute; left:${data.x}px; top:${data.y}px;
      width:${data.size}px; height:${data.size}px;
      transform:rotate(${data.rotation}deg);
      cursor:grab; user-select:none; touch-action:none; z-index:50;
    `;
    setTimeout(() => wrap.classList.remove('spawning'), 400);

    /* Content */
    if (data.def.type === 'img') {
      const img = document.createElement('img');
      img.src = data.def.src;
      img.style.cssText = 'width:100%;height:100%;object-fit:contain;pointer-events:none;display:block;';
      img.draggable = false;
      wrap.appendChild(img);
    } else {
      wrap.innerHTML = data.def.svg;
    }

    /* Grab-hint ring (fades out) */
    const hint = document.createElement('div');
    hint.className = 'pinch-hint';
    wrap.appendChild(hint);

    /* × delete button */
    const del = document.createElement('button');
    del.className = 'stk-del'; del.innerHTML = '×'; del.title = 'Hapus';
    wrap.appendChild(del);

    /* Resize handle (mouse fallback) */
    const resH = document.createElement('div');
    resH.className = 'stk-resize';
    resH.innerHTML = `<svg viewBox="0 0 10 10" width="10" height="10">
      <path d="M1 9L9 1M5 9L9 5" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
    </svg>`;
    wrap.appendChild(resH);

    container.appendChild(wrap);
    data.el = wrap;

    /* Mouse/touch drag */
    wrap.addEventListener('pointerdown', e => {
      if (e.target.closest('.stk-del') || e.target.closest('.stk-resize')) return;
      e.preventDefault();
      _selectSticker(data);
      dragState = { data, startX: e.clientX, startY: e.clientY, origX: data.x, origY: data.y };
      wrap.setPointerCapture(e.pointerId);
      wrap.style.cursor = 'grabbing';
    });

    resH.addEventListener('pointerdown', e => {
      e.preventDefault(); e.stopPropagation();
      dragState = { data, isResize: true, startX: e.clientX, startY: e.clientY, origSize: data.size };
      resH.setPointerCapture(e.pointerId);
    });

    del.addEventListener('click', e => { e.stopPropagation(); _popSticker(data); });

    _selectSticker(data);
  }

  /* ── Mouse/touch global events ───────────────────────────── */
  function _bindMouseEvents() {
    if (_evBound) return;
    _evBound = true;

    document.addEventListener('pointermove', e => {
      if (!dragState) return;
      const { data, isResize, startX, startY } = dragState;
      const dx = e.clientX - startX, dy = e.clientY - startY;
      if (isResize) {
        const deltaScale = (dx + dy) * 0.707;
        const n = Math.max(40, Math.min(400, dragState.origSize + deltaScale));
        data.size = n;
        if (data.el) { data.el.style.width = n + 'px'; data.el.style.height = n + 'px'; }
      } else {
        data.x = dragState.origX + dx; data.y = dragState.origY + dy;
        if (data.el) { data.el.style.left = data.x + 'px'; data.el.style.top = data.y + 'px'; }
      }
      _resetSelectionTimer();
    });

    document.addEventListener('pointerup', () => {
      if (dragState && !dragState.isResize && dragState.data.el)
        dragState.data.el.style.cursor = 'grab';
      dragState = null;
    });

    document.addEventListener('pointercancel', () => { dragState = null; });

    document.getElementById('cameraFrame')?.addEventListener('click', e => {
      if (!e.target.closest('.sticker-item') && !e.target.closest('.sticker-shelf'))
        _deselectAll();
    });
  }

  function _selectSticker(data) {
    if (selected && selected.id !== data.id) _deselectAll();
    selected = data;
    data.el?.classList.add('selected');
    _resetSelectionTimer();
  }

  function _resetSelectionTimer() {
    clearTimeout(selectionTimer);
    if (selected && selected.el) {
      selected.el.classList.add('selected'); // Ensure it's visible if it was hidden
      selectionTimer = setTimeout(() => {
        if (selected && !pinch.active && !dragState && !vDel.sticker) {
          selected.el?.classList.remove('selected');
        }
      }, 2000);
    }
  }

  function _deselectAll() {
    clearTimeout(selectionTimer);
    stickers.forEach(s => s.el?.classList.remove('selected'));
    selected = null;
  }

  /* ── Clear all ───────────────────────────────────────────── */
  function clearAll() {
    stickers.forEach(s => s.el?.remove());
    stickers = []; selected = null;
    pinch.active = false; pinch.sticker = null;
    vDel.sticker = null;
    const ov = overlayEl || document.getElementById('stickerOverlay');
    if (ov) ov.querySelectorAll('.sticker-item').forEach(el => el.remove());
  }

  /* ── Burn stickers to canvas ─────────────────────────────── */
  async function burnToCanvasAsync(canvas) {
    if (!_enabled || !stickers.length) return;
    const frameEl = overlayEl || document.getElementById('stickerOverlay');
    if (!frameEl) return;
    const fr  = frameEl.getBoundingClientRect();
    if (!fr.width || !fr.height) return;
    const ctx = canvas.getContext('2d');

    const sx = canvas.width  / fr.width;
    const sy = canvas.height / fr.height;
    const sScale = sx; // Uniform scale for sticker bounding box

    await Promise.all(stickers.map(data => new Promise(resolve => {
      const img  = new Image();
      const draw = () => {
        // Both camera preview and captured photo are mirrored (scaleX(-1)),
        // so X coordinate is directly data.x * sx
        const cx = data.x * sx;
        const cy = data.y * sy;
        const sw = data.size * sScale;
        const sh = data.size * sScale;

        // Replicate CSS object-fit: contain so stickers maintain intrinsic aspect ratio
        let drawW = sw, drawH = sh;
        const imgW = img.naturalWidth || img.width;
        const imgH = img.naturalHeight || img.height;
        if (imgW && imgH) {
          const aspect = imgW / imgH;
          if (aspect > 1) {
            drawH = sw / aspect;
          } else {
            drawW = sh * aspect;
          }
        }

        ctx.save();
        ctx.translate(cx + sw / 2, cy + sh / 2);
        ctx.rotate(data.rotation * Math.PI / 180);
        ctx.drawImage(img, -drawW / 2, -drawH / 2, drawW, drawH);
        ctx.restore();
        resolve();
      };
      img.onload  = draw;
      img.onerror = resolve;

      if (data.def.type === 'img') {
        img.src = data.def.src;
      } else {
        const url = URL.createObjectURL(new Blob([data.def.svg], { type: 'image/svg+xml;charset=utf-8' }));
        img.onload = () => { draw(); URL.revokeObjectURL(url); };
        img.src = url;
      }
    })));
  }

  /* ── Public API ──────────────────────────────────────────── */
  function count()   { return stickers.length; }

  function addById(id) {
    const ov = document.getElementById('stickerOverlay');
    if (ov && !overlayEl) { overlayEl = ov; _bindMouseEvents(); }
    const def = CATALOG.find(d => d.id === id);
    if (def) _addSticker(def);
  }

  function renderPicker() {
    if (!_enabled) return;
    _renderShelf();
  }

  function setEnabled(flag) {
    _enabled = !!flag;
    if (overlayEl) overlayEl.style.display = _enabled ? 'block' : 'none';
    const shelf = document.getElementById('stickerShelf');
    if (shelf) shelf.style.display = _enabled ? 'flex' : 'none';
    const btnClear = document.getElementById('btnClearStickers');
    if (btnClear) btnClear.style.display = _enabled ? 'inline-block' : 'none';
    if (_enabled) {
      _renderShelf();
      _bindMouseEvents();
      _bindGestureEvents();
    }
  }

  function isEnabled() {
    return _enabled;
  }

  return { init, clearAll, burnToCanvasAsync, count, addById, renderPicker, setEnabled, isEnabled, CATALOG };
})();

window.Stickers = Stickers;

document.addEventListener('DOMContentLoaded', () => {
  const overlay = document.getElementById('stickerOverlay');
  if (overlay) Stickers.init(overlay);
});
