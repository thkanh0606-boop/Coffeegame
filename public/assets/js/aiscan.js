/* aiscan.js — upload or capture a coffee photo, measure its colours in the
   browser (this PHP build has no GD), and fetch a game-accurate prediction. */
(function () {
  const $ = (id) => document.getElementById(id);
  const dz = $('dropzone'), input = $('fileInput'), previewWrap = $('previewWrap'),
        preview = $('preview'), btnAnalyze = $('btnAnalyze'), result = $('scanResult'),
        btnCamera = $('btnCamera'), camWrap = $('cameraWrap'), video = $('camVideo'),
        btnCapture = $('btnCapture'), btnStopCam = $('btnStopCam');
  let file = null, features = null, stream = null;
  const beep = (n) => { try { if (window.Sound) Sound.play(n); } catch (e) {} };

  /* ---------- Upload ---------- */
  dz.addEventListener('click', () => input.click());
  dz.addEventListener('dragover', (e) => { e.preventDefault(); dz.classList.add('drag'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('drag'));
  dz.addEventListener('drop', (e) => {
    e.preventDefault(); dz.classList.remove('drag');
    if (e.dataTransfer.files[0]) useFile(e.dataTransfer.files[0]);
  });
  input.addEventListener('change', () => { if (input.files[0]) useFile(input.files[0]); });

  function useFile(f) {
    if (!/^image\//.test(f.type)) {
      showNote("That file isn't an image — pick a coffee photo (JPG / PNG / WEBP).");
      return;
    }
    file = f; features = null;
    const url = URL.createObjectURL(f);
    preview.onload = () => { features = measure(preview); URL.revokeObjectURL(url); };
    preview.src = url;
    previewWrap.classList.remove('hidden');
    stopCamera();
    beep('click');
  }

  /* ---------- Camera capture ---------- */
  btnCamera.addEventListener('click', async () => {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      input.setAttribute('capture', 'environment'); input.click(); return;   // mobile fallback
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      video.srcObject = stream; await video.play();
      camWrap.classList.remove('hidden'); previewWrap.classList.add('hidden');
      beep('click');
    } catch (e) {
      showNote("Couldn't open the camera — you can upload a photo instead.");
    }
  });
  btnCapture.addEventListener('click', () => {
    const c = document.createElement('canvas');
    c.width = video.videoWidth || 480; c.height = video.videoHeight || 360;
    c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
    features = measure(c);
    c.toBlob((blob) => {
      if (!blob) { showNote("Capture failed — try again or upload a photo."); return; }
      file = new File([blob], 'capture.jpg', { type: 'image/jpeg' });
      preview.src = URL.createObjectURL(blob);
      previewWrap.classList.remove('hidden');
      stopCamera();
      beep('click');
    }, 'image/jpeg', 0.9);
  });
  btnStopCam.addEventListener('click', stopCamera);
  function stopCamera() {
    if (stream) { stream.getTracks().forEach((t) => t.stop()); stream = null; }
    if (video) video.srcObject = null;
    camWrap.classList.add('hidden');
  }

  /* ---------- Measure the central region's average colour (the cup contents) ---------- */
  function measure(src) {
    try {
      const S = 56, c = document.createElement('canvas'); c.width = S; c.height = S;
      const ctx = c.getContext('2d'); ctx.drawImage(src, 0, 0, S, S);
      const d = ctx.getImageData(0, 0, S, S).data;
      const lo = Math.floor(S * 0.22), hi = Math.ceil(S * 0.78);
      let r = 0, g = 0, b = 0, n = 0;
      for (let y = lo; y < hi; y++) for (let x = lo; x < hi; x++) {
        const i = (y * S + x) * 4; r += d[i]; g += d[i + 1]; b += d[i + 2]; n++;
      }
      r /= n; g /= n; b /= n;
      return {
        brightness: Math.round(0.299 * r + 0.587 * g + 0.114 * b),
        warmth: Math.round(r - b),
        green: Math.round(g - (r + b) / 2),
      };
    } catch (e) { return null; }
  }

  /* ---------- Analyze ---------- */
  btnAnalyze.addEventListener('click', async () => {
    if (!file) return;
    btnAnalyze.disabled = true;
    result.innerHTML = '<p class="text-center muted">Analyzing image…</p>';
    beep('grind');
    if (!features) features = measure(preview);

    const fd = new FormData();
    fd.append('image', file);
    if (features) fd.append('features', JSON.stringify(features));
    let res;
    try {
      const r = await fetch(window.BREW.base + '/index.php?url=aiscan/upload', { method: 'POST', body: fd });
      res = await r.json();
    } catch (e) { res = { ok: false }; }
    render(res);
    if (res && res.ok) setTimeout(() => location.reload(), 3200);   // refresh history
    btnAnalyze.disabled = false;
  });

  function showNote(msg) {
    result.innerHTML = '<div class="scan-note"><div class="scan-note-ic">☕</div><p>' + msg + '</p></div>';
    beep('wrong');
  }

  function render(res) {
    if (!res || !res.ok) {
      showNote((res && res.message) ||
        "The scan didn't work this time. Try a clearer, close-up photo of a single coffee cup.");
      return;
    }
    beep('success');
    const conf = res.confidence;
    const hedge = res.sure ? '' :
      '<p class="small muted text-center mt-2">I’m not fully sure — this is my best guess. A clearer close-up helps.</p>';
    const ings = (res.ingredients || []).map((i) => '<span class="made-pill">' + i + '</span>').join(' ')
      || '<span class="muted small">—</span>';
    const steps = (res.steps || []).map((s, i) =>
      '<div class="recipe-line"><span class="dot"></span>' + (i + 1) + '. ' + s + '</div>').join('');
    const needed = (res.inventory_needed || []).map((n) => {
      const low = n.quantity !== null && +n.quantity <= (+n.low_threshold || 0);
      return '<span class="chip' + (low ? ' need-low' : '') + '">' + n.name + (low ? ' ⚠' : '') + '</span>';
    }).join(' ') || '<span class="muted small">You have everything in stock ✓</span>';

    result.innerHTML =
      '<div class="text-center"><div class="scan-verdict">' + (res.sure ? 'Detected' : 'Best guess') + '</div>' +
      '<h2>' + res.drink_name + '</h2>' +
      '<div class="chip chip-dark mt-2">Confidence ' + conf + '%</div></div>' +
      '<div class="progress-track mt-3"><span style="width:' + conf + '%"></span></div>' + hedge +
      '<h4 class="mt-3">Required Ingredients</h4><div class="flex wrap gap-2 mt-2">' + ings + '</div>' +
      '<h4 class="mt-3">Recipe &amp; Steps</h4><div class="mt-2">' + steps + '</div>' +
      '<h4 class="mt-3">Need to Restock</h4><div class="flex wrap gap-2 mt-2">' + needed + '</div>';
  }

  const unlock = () => { try { if (window.Sound) Sound.resume(); } catch (e) {} document.removeEventListener('click', unlock); };
  document.addEventListener('click', unlock);
  window.addEventListener('beforeunload', stopCamera);
})();
