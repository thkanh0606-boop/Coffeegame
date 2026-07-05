/* ui.js — shared UI helpers: toasts, HUD, popups, modals. */
(function () {
  const wrap = () => document.getElementById('toastWrap');
  const fx = () => document.getElementById('fxLayer');

  function toast(msg, opts = {}) {
    const w = wrap(); if (!w) return;
    const el = document.createElement('div');
    el.className = 'toast' + (opts.light ? ' light' : '');
    el.innerHTML = (opts.icon ? opts.icon + ' ' : '') + msg;
    w.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, opts.ms || 2200);
    setTimeout(() => el.remove(), (opts.ms || 2200) + 400);
  }

  function coinPop(amount, x, y) {
    const layer = fx(); if (!layer) return;
    const el = document.createElement('div');
    el.className = 'coin-pop';
    el.textContent = (amount >= 0 ? '+' : '') + amount;
    el.style.left = (x || window.innerWidth / 2) + 'px';
    el.style.top = (y || window.innerHeight / 2) + 'px';
    layer.appendChild(el);
    setTimeout(() => el.remove(), 1000);
  }

  function comboPop(n) {
    const el = document.createElement('div');
    el.className = 'combo-pop';
    el.textContent = 'COMBO x' + n;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 800);
  }

  // HUD sync (elements with data-coins, data-level, data-combo, data-sat, data-day)
  function setHud(patch) {
    for (const key in patch) {
      document.querySelectorAll('[data-' + key + ']').forEach(n => n.textContent = patch[key]);
    }
  }

  function openModal(id) { const m = document.getElementById(id); if (m) m.classList.add('open'); }
  function closeModal(id) { const m = document.getElementById(id); if (m) m.classList.remove('open'); }

  // Global modal close buttons
  document.addEventListener('click', (e) => {
    if (e.target.matches('[data-close-modal]')) {
      const m = e.target.closest('.modal-backdrop');
      if (m) m.classList.remove('open');
    }
    if (e.target.classList.contains('modal-backdrop')) {
      e.target.classList.remove('open');
    }
  });

  window.UI = { toast, coinPop, comboPop, setHud, openModal, closeModal };

  // Passive HUD hydrate for pages that don't run their own state fetch.
  document.addEventListener('DOMContentLoaded', () => {
    if (!window.BREW_STATE && document.querySelector('[data-coins]') && window.API) {
      API.get('state').then(d => {
        if (d && d.ok) setHud({ coins: d.state.progress.coins, level: d.state.progress.level });
      }).catch(() => {});
    }
  });
})();
