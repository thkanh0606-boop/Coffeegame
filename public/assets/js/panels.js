/* panels.js — tab switching + buy actions for upgrades & shop pages. */
(function () {
  // Tabs
  document.querySelectorAll('.tabs').forEach(tabs => {
    tabs.addEventListener('click', (e) => {
      const tab = e.target.closest('.tab');
      if (!tab || !tab.dataset.tab) return;
      tabs.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      document.querySelectorAll('[data-panel]').forEach(p =>
        p.classList.toggle('hidden', p.dataset.panel !== tab.dataset.tab));
      Sound.play && Sound.play('click');
    });
  });

  // Buy upgrade
  document.body.addEventListener('click', async (e) => {
    const up = e.target.closest('[data-buy-upgrade]');
    if (up) {
      const code = up.dataset.buyUpgrade;
      const res = await API.post('upgrade', { code });
      if (!res.ok) {
        UI.toast(res.error === 'insufficient_coins' ? 'Not enough coins (need ' + res.need + ')'
          : res.error === 'maxed' ? 'Already max level' : 'Upgrade failed', { light: true });
        Sound.play('wrong');
        return;
      }
      Sound.play('levelup');
      UI.toast('Upgraded to Lv ' + res.level + ' ✓', {});
      UI.setHud({ coins: res.coins });
      setTimeout(() => location.reload(), 500);
      return;
    }

    const dec = e.target.closest('[data-buy-decoration]');
    if (dec) {
      const code = dec.dataset.buyDecoration;
      const res = await API.post('buyDecoration', { code });
      if (!res.ok) {
        UI.toast(res.error === 'insufficient_coins' ? 'Not enough coins (need ' + res.need + ')' : 'Purchase failed', { light: true });
        Sound.play('wrong');
        return;
      }
      Sound.play('coin');
      UI.toast('Placed in your cafe ✓', {});
      UI.setHud({ coins: res.coins });
      setTimeout(() => location.reload(), 500);
    }
  });

  // Hydrate HUD coins/level from server state
  API.get('state').then(d => {
    if (d.ok) UI.setHud({
      coins: d.state.progress.coins, level: d.state.progress.level,
    });
  });

  // First-gesture audio unlock
  const unlock = () => { Sound.resume(); document.removeEventListener('click', unlock); };
  document.addEventListener('click', unlock);
})();
