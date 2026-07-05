/* audio.js — Web Audio synthesized sound engine (no binary assets).
   All sounds are generated procedurally so nothing copyrighted ships. */
(function () {
  let ctx = null;
  const state = { music: true, sfx: true, musicVol: 0.4, sfxVol: 0.7 };
  let musicNodes = null;

  function ac() {
    if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }

  // A single enveloped oscillator "blip".
  function blip(freq, dur, type = 'sine', vol = 1, whenOffset = 0) {
    if (!state.sfx) return;
    const c = ac();
    const t = c.currentTime + whenOffset;
    const osc = c.createOscillator();
    const g = c.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(vol * state.sfxVol, t + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    osc.connect(g); g.connect(c.destination);
    osc.start(t); osc.stop(t + dur + 0.02);
  }

  // Filtered noise burst (steam / grind / hiss).
  function noise(dur, cutoff = 1200, vol = 0.5) {
    if (!state.sfx) return;
    const c = ac();
    const buf = c.createBuffer(1, c.sampleRate * dur, c.sampleRate);
    const d = buf.getChannelData(0);
    for (let i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1);
    const src = c.createBufferSource(); src.buffer = buf;
    const filt = c.createBiquadFilter(); filt.type = 'lowpass'; filt.frequency.value = cutoff;
    const g = c.createGain();
    const t = c.currentTime;
    g.gain.setValueAtTime(vol * state.sfxVol, t);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    src.connect(filt); filt.connect(g); g.connect(c.destination);
    src.start();
  }

  const SFX = {
    click:   () => blip(520, 0.06, 'square', 0.25),
    pour:    () => noise(0.35, 900, 0.35),
    steam:   () => noise(0.6, 2600, 0.3),
    grind:   () => noise(0.5, 600, 0.5),
    espresso:() => { noise(0.4, 700, 0.35); blip(180, 0.3, 'sawtooth', 0.15, 0.05); },
    ice:     () => { blip(1400, 0.05, 'triangle', 0.3); blip(1100, 0.05, 'triangle', 0.3, 0.06); },
    coin:    () => { blip(880, 0.08, 'square', 0.3); blip(1320, 0.12, 'square', 0.3, 0.07); },
    success: () => { [523, 659, 784, 1046].forEach((f, i) => blip(f, 0.14, 'triangle', 0.3, i * 0.08)); },
    combo:   () => { [659, 880, 1174].forEach((f, i) => blip(f, 0.12, 'square', 0.3, i * 0.06)); },
    wrong:   () => { blip(200, 0.25, 'sawtooth', 0.35); blip(150, 0.3, 'sawtooth', 0.3, 0.12); },
    angry:   () => { blip(160, 0.4, 'sawtooth', 0.4); },
    levelup: () => { [523, 659, 784, 1046, 1318].forEach((f, i) => blip(f, 0.16, 'triangle', 0.35, i * 0.1)); },
  };

  // Simple ambient music: soft arpeggio loop.
  function startMusic() {
    if (!state.music || musicNodes) return;
    const c = ac();
    const g = c.createGain();
    g.gain.value = state.musicVol * 0.25;
    g.connect(c.destination);
    const notes = [261.63, 329.63, 392.0, 329.63, 293.66, 349.23, 440.0, 349.23];
    let i = 0;
    const tick = () => {
      if (!musicNodes) return;
      const osc = c.createOscillator();
      const ng = c.createGain();
      osc.type = 'triangle';
      osc.frequency.value = notes[i % notes.length] / 2;
      const t = c.currentTime;
      ng.gain.setValueAtTime(0.0001, t);
      ng.gain.exponentialRampToValueAtTime(0.2, t + 0.05);
      ng.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
      osc.connect(ng); ng.connect(g);
      osc.start(); osc.stop(t + 0.55);
      i++;
    };
    const id = setInterval(tick, 480);
    musicNodes = { g, id };
  }
  function stopMusic() {
    if (musicNodes) { clearInterval(musicNodes.id); musicNodes.g.disconnect(); musicNodes = null; }
  }

  window.Sound = {
    play: (name) => { if (SFX[name]) SFX[name](); },
    configure: (s) => {
      Object.assign(state, s);
      if (state.music) startMusic(); else stopMusic();
    },
    startMusic, stopMusic,
    resume: () => ac(),
  };
})();
