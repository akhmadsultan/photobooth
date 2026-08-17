/* v2-api.js — Server API client v2
   Sends strip + each frame as separate small multipart fields.
   This avoids any single large JSON string that could hit PHP limits. */
'use strict';

const API = (() => {
  const base = () => window.PB_CFG?.apiBase || 'php/';

  /* ── Save strip + frames via multipart FormData ─────────
     Each field is sent individually so no single value is huge.
     PHP reads via $_POST which works with any server setup.     */
  async function saveStrip(stripDataURL, frameURLs, meta = {}) {
    const fd = new FormData();

    // Session metadata
    fd.append('session_id',  window.PB_CFG?.sessionId || 'UNKNOWN');
    fd.append('filter',      meta.filter     || 'none');
    fd.append('frame_style', meta.frameStyle || 'none');
    fd.append('timestamp',   String(Date.now()));

    // Convert dataURL → Blob, send as actual file upload (binary, not base64 text)
    // This is 33% smaller than base64 and avoids JSON size issues entirely
    const stripBlob = _dataURLtoBlob(stripDataURL);
    fd.append('strip_file', stripBlob, 'strip.png');

    frameURLs.forEach((dataURL, i) => {
      if (!dataURL) return;
      const blob = _dataURLtoBlob(dataURL);
      fd.append(`frame_file_${i}`, blob, `frame_${i}.jpg`);
    });

    let res;
    try {
      res = await fetch(base() + 'save_photo.php', { method: 'POST', body: fd });
    } catch(e) {
      throw new Error('Network error: ' + e.message);
    }

    // Read response as text first so we can show it if JSON parse fails
    const text = await res.text();
    if (!res.ok) {
      throw new Error(`Server ${res.status}: ${text.slice(0, 300)}`);
    }

    let json;
    try {
      json = JSON.parse(text);
    } catch(e) {
      // Show what PHP actually returned to help debug
      throw new Error('PHP returned non-JSON: ' + text.slice(0, 300));
    }
    return json;
  }

  /* ── Save GIF to server ─────────────────────────────────── */
  async function saveGif(gifBlob, sessionId) {
    const fd = new FormData();
    fd.append('session_id', sessionId || window.PB_CFG?.sessionId || 'UNKNOWN');
    fd.append('gif_file', gifBlob, 'boomerang.gif');

    let res;
    try {
      res = await fetch(base() + 'save_gif.php', { method: 'POST', body: fd });
    } catch(e) {
      throw new Error('Network error: ' + e.message);
    }

    const text = await res.text();
    if (!res.ok) throw new Error(`Server ${res.status}: ${text.slice(0, 300)}`);
    try { return JSON.parse(text); } catch(e) {
      throw new Error('PHP returned non-JSON: ' + text.slice(0, 300));
    }
  }

  /* ── Upload to Google Drive ──────────────────────────────── */
  async function uploadGDrive(sessionId) {
    const fd = new FormData();
    fd.append('session_id', sessionId || window.PB_CFG?.sessionId || 'UNKNOWN');

    let res;
    try {
      res = await fetch(base() + 'upload_gdrive.php', { method: 'POST', body: fd });
    } catch(e) {
      throw new Error('Network error (GDrive): ' + e.message);
    }

    const text = await res.text();
    if (!res.ok) throw new Error(`GDrive server ${res.status}: ${text.slice(0, 300)}`);
    try { return JSON.parse(text); } catch(e) {
      throw new Error('GDrive PHP non-JSON: ' + text.slice(0, 300));
    }
  }

  /* ── Send record to Supabase (Video Mapping) ─────────────── */
  async function sendToVideoMapping(payload) {
    let res;
    try {
      res = await fetch(base() + 'supabase_sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
    } catch(e) {
      throw new Error('Network error (Supabase): ' + e.message);
    }

    const text = await res.text();
    if (!res.ok) throw new Error(`Supabase server ${res.status}: ${text.slice(0, 300)}`);
    try { return JSON.parse(text); } catch(e) {
      throw new Error('Supabase PHP non-JSON: ' + text.slice(0, 300));
    }
  }

  /* Convert dataURL to binary Blob (bypasses base64 overhead) */
  function _dataURLtoBlob(dataURL) {
    const [header, b64] = dataURL.split(',');
    const mime = header.match(/:(.*?);/)[1];
    const bin  = atob(b64);
    const buf  = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
    return new Blob([buf], { type: mime });
  }

  /* ── Ping ─────────────────────────────────────────────── */
  async function ping() {
    const t = Date.now();
    try {
      const res = await fetch(base() + 'ping.php');
      if (!res.ok) return { ok: false };
      return { ok: true, ms: Date.now() - t };
    } catch(e) {
      return { ok: false, error: e.message };
    }
  }

  return { saveStrip, saveGif, uploadGDrive, sendToVideoMapping, ping };
})();
window.API = API;
