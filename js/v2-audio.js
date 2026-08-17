/* v2-audio.js — Web Audio API Synthesized SFX */
'use strict';

const SFX = (() => {
  let actx = null;

  function init() {
    if (!actx) {
      actx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (actx.state === 'suspended') {
      actx.resume();
    }
  }

  function playOsc(type, freqStart, freqEnd, dur, vol = 0.1) {
    if (!actx) init();
    if (!actx) return;
    const t = actx.currentTime;
    const osc = actx.createOscillator();
    const gain = actx.createGain();

    osc.type = type;
    osc.frequency.setValueAtTime(freqStart, t);
    if (freqEnd) {
      osc.frequency.exponentialRampToValueAtTime(freqEnd, t + dur);
    }

    gain.gain.setValueAtTime(vol, t);
    gain.gain.exponentialRampToValueAtTime(0.01, t + dur);

    osc.connect(gain);
    gain.connect(actx.destination);
    osc.start(t);
    osc.stop(t + dur);
  }

  function playNoise(dur, vol = 0.1) {
    if (!actx) init();
    if (!actx) return;
    const bufferSize = actx.sampleRate * dur;
    const buffer = actx.createBuffer(1, bufferSize, actx.sampleRate);
    const data = buffer.getChannelData(0);
    for (let i = 0; i < bufferSize; i++) {
      data[i] = Math.random() * 2 - 1;
    }
    const noise = actx.createBufferSource();
    noise.buffer = buffer;
    
    const gain = actx.createGain();
    const t = actx.currentTime;
    gain.gain.setValueAtTime(vol, t);
    gain.gain.exponentialRampToValueAtTime(0.01, t + dur);

    const filter = actx.createBiquadFilter();
    filter.type = 'highpass';
    filter.frequency.value = 1000;

    noise.connect(filter);
    filter.connect(gain);
    gain.connect(actx.destination);
    noise.start(t);
  }

  const sounds = {
    click:   () => playOsc('sine', 800, 1200, 0.05, 0.05),
    tick:    () => playOsc('square', 600, 400, 0.1, 0.05),
    shutter: () => {
      playNoise(0.15, 0.2);
      playOsc('square', 100, 50, 0.1, 0.1);
    },
    spawn:   () => playOsc('sine', 400, 800, 0.15, 0.1),
    del:     () => playOsc('sine', 600, 200, 0.2, 0.1),
    grab:    () => playOsc('triangle', 300, 300, 0.05, 0.03),
    drop:    () => playOsc('triangle', 200, 200, 0.08, 0.02),
    success: () => {
      playOsc('sine', 400, 400, 0.1, 0.05);
      setTimeout(() => playOsc('sine', 600, 600, 0.2, 0.05), 100);
    },
    error:   () => {
      playOsc('sawtooth', 150, 150, 0.2, 0.05);
      setTimeout(() => playOsc('sawtooth', 100, 100, 0.3, 0.05), 200);
    }
  };

  // Pre-bind interactions to unlock audio context on first click/touch
  window.addEventListener('pointerdown', init, { once: true });
  window.addEventListener('keydown', init, { once: true });

  return sounds;
})();
window.SFX = SFX;
