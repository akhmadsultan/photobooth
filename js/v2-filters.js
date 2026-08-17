/* v2-filters.js — Filter state & live video preview
   CSS filter applied to <video> for live preview.
   Pixel filter applied on capture canvas (same algorithm as v2-utils.js).
   Both use identical mapping so preview = captured output.  */
'use strict';

const Filters = (() => {
  /* Map filter name → CSS filter string (applied to video element) */
  const CSS_MAP = {
    none:    '',
    bw:      'grayscale(1) contrast(1.3)',
    sepia:   'sepia(.8) contrast(1.1)',
    vintage: 'sepia(.5) saturate(.75) contrast(1.1) brightness(.92)',
    fade:    'saturate(.6) contrast(.85) brightness(1.08)',
    warm:    'sepia(.15) saturate(1.4) hue-rotate(-12deg) brightness(1.04)'
  };

  let active='none';
  let videoEl=null;
  const ev = new U.Emitter();

  function init(video){
    videoEl=video;
    _bindButtons();
    apply('none');
  }

  function _bindButtons(){
    document.querySelectorAll('[data-filter]').forEach(btn=>{
      btn.addEventListener('click',()=>apply(btn.dataset.filter));
    });
  }

  function apply(name){
    if(!CSS_MAP.hasOwnProperty(name)) return;
    active=name;
    if(videoEl){
      videoEl.style.filter=CSS_MAP[name]||'none';
      videoEl.style.webkitFilter=CSS_MAP[name]||'none';
    }
    document.querySelectorAll('[data-filter]').forEach(b=>{
      b.classList.toggle('active', b.dataset.filter===name);
    });
    ev.emit('change', name);
  }

  function get(){ return active; }
  function getCSSString(){ return CSS_MAP[active]||''; }
  function on(e,f){ ev.on(e,f); }

  return { init, apply, get, getCSSString, on };
})();
window.Filters=Filters;
