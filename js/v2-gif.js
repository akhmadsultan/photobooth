/* v2-gif.js — GIF boomerang encoder using omggif (local, zero CORS)
   Uses 5-bit per-channel uniform quantization with full-coverage
   lookup table to guarantee O(1) per-pixel indexing — no freeze.   */
'use strict';

const GifMaker = (() => {
  const ev = new U.Emitter();
  let lastBlob = null, lastURL = null;

  const GIF_W = 320, GIF_H = 240;
  const FRAME_DELAY = 25; // centiseconds (0.25 s per frame)

  const yield_ = () => new Promise(r => setTimeout(r, 0));

  /* ── Public: generate boomerang GIF ─────────────────── */
  async function generate(frameURLs) {
    if (!frameURLs?.length) throw new Error('No frames provided');

    ev.emit('start');
    ev.emit('progress', 5);

    if (typeof GifWriter === 'undefined')
      throw new Error('omggif.js not loaded — check js/omggif.js');

    /* 1. Load images */
    const imgs = await Promise.all(frameURLs.map(_loadImg));
    ev.emit('progress', 15);
    await yield_();

    /* 2. Build boomerang sequence */
    const seq = [...imgs];
    if (imgs.length > 2) {
      seq.push(...[...imgs].reverse().slice(1, imgs.length - 1));
    }

    /* 3. Render frames to pixel data */
    const tmpCvs = document.createElement('canvas');
    tmpCvs.width = GIF_W; tmpCvs.height = GIF_H;
    const tmpCtx = tmpCvs.getContext('2d', { willReadFrequently: true });

    const rgbaFrames = [];
    for (let i = 0; i < seq.length; i++) {
      tmpCtx.clearRect(0, 0, GIF_W, GIF_H);
      _drawCover(tmpCtx, seq[i], GIF_W, GIF_H);
      rgbaFrames.push(tmpCtx.getImageData(0, 0, GIF_W, GIF_H));
      ev.emit('progress', 15 + Math.round((i + 1) / seq.length * 20));
      await yield_();
    }

    /* 4. Build 256-color palette using median-cut on frequency counts
          Sample EVERY pixel (fast — just Map lookups, no heavy math).
          5-bit quantization = 32768 possible keys, tiny Map.          */
    ev.emit('progress', 38);
    await yield_();

    const palette256 = await _buildPalette256(rgbaFrames);
    ev.emit('progress', 55);
    await yield_();

    /* 5. Build full 32768-entry lookup table: quantKey → paletteIdx
          This is O(32768) once, making per-pixel indexing O(1).      */
    const lut = _buildLUT(palette256);
    ev.emit('progress', 62);
    await yield_();

    /* 6. Encode GIF with GifWriter */
    const bufSize = GIF_W * GIF_H * seq.length * 4 + 8192;
    const buf = new Uint8Array(bufSize);
    const gw = new GifWriter(buf, GIF_W, GIF_H, {
      loop: 0,
      palette: palette256
    });

    for (let i = 0; i < rgbaFrames.length; i++) {
      const indexed = _indexFrameLUT(rgbaFrames[i].data, GIF_W * GIF_H, lut);
      gw.addFrame(0, 0, GIF_W, GIF_H, indexed, {
        delay: FRAME_DELAY,
        disposal: 2
      });
      ev.emit('progress', 62 + Math.round((i + 1) / rgbaFrames.length * 35));
      await yield_();
    }

    const endPos = gw.end();
    const blob = new Blob([buf.slice(0, endPos)], { type: 'image/gif' });

    if (lastURL) URL.revokeObjectURL(lastURL);
    lastBlob = blob;
    lastURL  = URL.createObjectURL(blob);

    ev.emit('progress', 100);
    ev.emit('done', { url: lastURL, blob, size: blob.size });
    console.log(`[GIF] Done: ${(blob.size / 1024).toFixed(1)} KB, ${seq.length} frames`);
    return lastURL;
  }

  /* ── Build 256-color palette ─────────────────────────
     Count every pixel's 5-bit key across all frames,
     pick top 255 by frequency + black at index 0.      */
  async function _buildPalette256(rgbaFrames) {
    const freq = new Map();

    for (let fi = 0; fi < rgbaFrames.length; fi++) {
      const d = rgbaFrames[fi].data;
      const n = d.length;
      // Sample every 4th pixel — enough coverage, 4× faster
      for (let o = 0; o < n; o += 16) {
        const k = ((d[o] >> 3) << 10) | ((d[o+1] >> 3) << 5) | (d[o+2] >> 3);
        freq.set(k, (freq.get(k) || 0) + 1);
      }
      await yield_();
    }

    // Sort by frequency, take top 255
    const top255 = [...freq.entries()]
      .sort((a, b) => b[1] - a[1])
      .slice(0, 255);

    // Build palette array of 256 packed 0xRRGGBB integers
    const palette = new Array(256).fill(0x000000);
    top255.forEach(([k], i) => {
      const r = ((k >> 10) & 31) << 3;
      const g = ((k >> 5)  & 31) << 3;
      const b = ( k        & 31) << 3;
      palette[i + 1] = (r << 16) | (g << 8) | b;
    });

    return palette;
  }

  /* ── Build full 32768-entry LUT ──────────────────────
     For every possible 5-bit quantized color key,
     find the nearest palette entry using Euclidean dist.
     Cost: 32768 × 256 comparisons = ~8M ops, done once. */
  function _buildLUT(palette) {
    const lut = new Uint8Array(32768); // key → palette index

    // Pre-expand palette to R/G/B arrays for fast access
    const pr = new Uint8Array(256);
    const pg = new Uint8Array(256);
    const pb = new Uint8Array(256);
    for (let i = 0; i < 256; i++) {
      pr[i] = (palette[i] >> 16) & 0xff;
      pg[i] = (palette[i] >>  8) & 0xff;
      pb[i] =  palette[i]        & 0xff;
    }

    for (let k = 0; k < 32768; k++) {
      const r = ((k >> 10) & 31) << 3;
      const g = ((k >> 5)  & 31) << 3;
      const b = ( k        & 31) << 3;

      let bestIdx = 0, bestDist = Infinity;
      for (let p = 0; p < 256; p++) {
        const dr = r - pr[p], dg = g - pg[p], db = b - pb[p];
        const dist = dr*dr + dg*dg + db*db;
        if (dist < bestDist) { bestDist = dist; bestIdx = p; }
        if (dist === 0) break; // exact match
      }
      lut[k] = bestIdx;
    }

    return lut;
  }

  /* ── Index frame pixels via LUT (O(1) per pixel) ─── */
  function _indexFrameLUT(data, pixelCount, lut) {
    const indexed = new Uint8Array(pixelCount);
    for (let i = 0; i < pixelCount; i++) {
      const o = i * 4;
      const k = ((data[o] >> 3) << 10) | ((data[o+1] >> 3) << 5) | (data[o+2] >> 3);
      indexed[i] = lut[k];
    }
    return indexed;
  }

  function _drawCover(ctx, img, w, h) {
    const s  = Math.max(w / img.width, h / img.height);
    const sw = img.width * s, sh = img.height * s;
    ctx.drawImage(img, (w - sw) / 2, (h - sh) / 2, sw, sh);
  }

  function _loadImg(src) {
    return new Promise((res, rej) => {
      const img = new Image();
      img.onload  = () => res(img);
      img.onerror = () => rej(new Error('Image load failed'));
      img.src = src;
    });
  }

  function download(name) {
    if (!lastBlob) return;
    U.download(lastBlob, name || `${window.PB_CFG?.sessionId || 'booth'}_boomerang.gif`, 'image/gif');
  }

  function getURL() { return lastURL; }
  function on(e, f) { ev.on(e, f); }

  return { generate, download, getURL, on };
})();
window.GifMaker = GifMaker;
