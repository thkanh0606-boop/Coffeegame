/* game.js — real-time coffee-shop gameplay engine (single integrated screen).
   Customers walk in, pause to think, then order; you build cups from stations
   and serve them. Recipe validation + rewards resolved on the server.
   Ingredient management, upgrades and revenue all happen in floating overlays
   so the player never leaves the café screen. */
(function () {
  const S = window.BREW_STATE;
  const SVG = window.BREW_SVG;
  const svg = (name) => SVG[name] || SVG.cup;

  // ---- Derived catalogue ----
  const drinksByCode = {};
  S.drinks.forEach(d => drinksByCode[d.code] = d);
  const ingByCode = {};
  S.inventory.forEach(i => ingByCode[i.code] = i);

  /* ---- Customer character images (public/pic/anh*.png) ---- */
  const CUSTOMER_IMGS = window.BREW_CUSTOMERS || [];
  function pickCustomerImg() {
    if (!CUSTOMER_IMGS.length) return null;
    return CUSTOMER_IMGS[Math.floor(Math.random() * CUSTOMER_IMGS.length)];
  }
  function custImg(c, cls) {
    // Customers are always drawn from the public/pic/anh*.png pool — no SVG models.
    if (!c.img) return '';
    return '<img class="' + cls + '" src="' + window.BREW_ASSET + c.img +
      '" alt="customer" decoding="async">';
  }

  /* ---- Natural-English order dialogue generation ----
     Turns a customer's chosen drinks into a spoken sentence with correct
     a/an articles, an Oxford comma, "and" before the last drink, and a
     varied opener so every customer sounds a little different. */
  function article(name) {
    return /^[aeiou]/i.test(name.trim()) ? 'an' : 'a';
  }
  function drinkPhrase(name) {
    return article(name) + ' ' + name;
  }
  function joinDrinks(names) {
    const parts = names.map(drinkPhrase);
    if (parts.length === 1) return parts[0];
    if (parts.length === 2) return parts[0] + ' and ' + parts[1];
    return parts.slice(0, -1).join(', ') + ', and ' + parts[parts.length - 1];
  }
  const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
  const DIALOGUE_PATTERNS = [
    list => "I'd like " + list + ", please!",
    list => "Could I get " + list + ", please?",
    list => "Can I have " + list + ", please?",
    list => "I'll have " + list + ", thanks!",
    list => "May I order " + list + ", please?",
    list => "Hi there! " + cap(list) + ", please!",
    list => "Just " + list + " for me, please!",
    list => "I feel like " + list + " today, please!",
  ];
  function makeOrderLine(drinkCodes) {
    const names = drinkCodes.map(c => drinksByCode[c].name);
    const list = joinDrinks(names);
    const pattern = DIALOGUE_PATTERNS[Math.floor(Math.random() * DIALOGUE_PATTERNS.length)];
    return pattern(list);
  }

  // Ingredient -> the SFX/animation flavour when dispensed
  const stationSfx = {
    espresso: 'espresso', water: 'pour', milk: 'steam', foam: 'steam',
    ice: 'ice', chocolate: 'pour', caramel: 'pour', vanilla: 'pour',
    mint: 'pour', matcha: 'grind', whip: 'pour', sugar: 'pour',
  };

  // ---- Tunables (customer pacing — slower & more natural) ----
  const PATIENCE_SCALE = 1.7;   // longer overall waiting window
  const PATIENCE_BONUS = 6;     // flat extra seconds
  const DRAIN_RATE     = 0.65;  // patience decreases more slowly
  const THINK_MIN      = 1.8;   // seconds a customer walks in / thinks before ordering
  const THINK_MAX      = 3.2;

  // ---- Live game model ----
  const G = {
    coins: +S.progress.coins || 0,
    level: +S.progress.level || 1,
    xp: +S.progress.xp || 0,
    day: +S.progress.current_day || 1,
    combo: 0,
    satisfaction: +(S.shop.satisfaction ?? 100),
    gameTime: +S.progress.game_time || 0,   // in-game seconds (09:00 start)
    customers: [],       // active customers
    cup: [],             // ingredients in the current build
    unlocked: S.progress.unlocked_recipes || [],
    inspecting: null,
    nextCustId: 1,
    settings: S.settings || { music_on: 1, sfx_on: 1, music_vol: 0.4, sfx_vol: 0.7 },
    dirty: false,
    paused: false,
    // Per-day tally for the end-of-day summary (reset each day).
    dayStats: { gross: 0, tips: 0, xp: 0, orders: 0, happy: 0, failed: 0, expenses: 0 },
  };
  function resetDayStats() {
    G.dayStats = { gross: 0, tips: 0, xp: 0, orders: 0, happy: 0, failed: 0, expenses: 0 };
  }

  const DIFFICULTY = { easy: 1.3, normal: 1.0, hard: 0.8 };
  const MAX_CUSTOMERS = 5;

  // Speed multiplier from equipment upgrades (higher level = faster stations)
  function speedMult() {
    const em = S.upgrades.find(u => u.code === 'espresso_machine');
    const lvl = em ? +em.level : 1;
    return Math.max(0.35, 1 - (lvl - 1) * 0.12);
  }
  function patienceMult() {
    const seats = S.upgrades.find(u => u.code === 'comfy_seats');
    const lvl = seats ? +seats.level : 0;
    return 1 + lvl * 0.10;
  }

  /* ============================================================
     ELEMENTS
     ============================================================ */
  const el = {
    row: document.getElementById('customerRow'),
    bench: document.getElementById('bench'),
    orders: document.getElementById('orderQueue'),
    menu: document.getElementById('menuGrid'),
    detail: document.getElementById('itemDetail'),
    buildCup: document.getElementById('buildCup'),
    madeList: document.getElementById('madeList'),
    stockList: document.getElementById('stockList'),
  };

  /* ============================================================
     DRINK MENU + RECIPE CARD
     ============================================================ */
  function unlockedDrinks() {
    return S.drinks.filter(d => G.unlocked.includes(d.code) || d.unlock_level <= G.level);
  }

  function renderMenu() {
    el.menu.innerHTML = '';
    S.drinks.forEach(d => {
      const locked = !(G.unlocked.includes(d.code) || d.unlock_level <= G.level);
      const item = document.createElement('div');
      item.className = 'menu-item' + (locked ? ' locked' : '');
      item.innerHTML = svg(d.icon) + '<div class="nm">' + d.name + '</div>' +
        (locked ? '<div class="small muted">Lv ' + d.unlock_level + '</div>' : '');
      if (!locked) item.addEventListener('click', () => inspectDrink(d.code));
      el.menu.appendChild(item);
    });
  }

  function inspectDrink(code) {
    G.inspecting = code;
    const d = drinksByCode[code];
    Sound.play('click');
    const steps = d.recipe.map(r =>
      '<div class="recipe-line"><span class="dot"></span>' + r.name + '</div>').join('');
    el.detail.innerHTML =
      '<div class="detail-cup">' + svg(d.icon) + '</div>' +
      '<h3>' + d.name + '</h3>' +
      '<div class="chip mt-2">' + svg('coin') + d.price + '</div>' +
      '<div class="mt-3" style="text-align:left">' + steps + '</div>';
  }

  /* ============================================================
     STATIONS (bench)
     ============================================================ */
  const STATIONS = [
    { code: 'espresso',  name: 'Espresso',  icon: 'machine' },
    { code: 'water',     name: 'Hot Water', icon: 'water' },
    { code: 'milk',      name: 'Milk',      icon: 'frother' },
    { code: 'foam',      name: 'Foam',      icon: 'foam' },
    { code: 'ice',       name: 'Ice',       icon: 'ice' },
    { code: 'chocolate', name: 'Chocolate', icon: 'choco' },
    { code: 'caramel',   name: 'Caramel',   icon: 'syrup' },
    { code: 'vanilla',   name: 'Vanilla',   icon: 'syrup' },
    { code: 'mint',      name: 'Mint',      icon: 'syrup' },
    { code: 'matcha',    name: 'Matcha',    icon: 'matchapowder' },
    { code: 'whip',      name: 'Whip',      icon: 'whip' },
    { code: 'sugar',     name: 'Sugar',     icon: 'sugar' },
  ];

  function renderBench() {
    el.bench.innerHTML = '';
    STATIONS.forEach(st => {
      const inv = ingByCode[st.code];
      const qty = inv ? inv.quantity : 0;
      const low = inv && qty <= inv.low_threshold;
      const node = document.createElement('div');
      node.className = 'station';
      node.dataset.code = st.code;
      node.innerHTML =
        '<div class="add" data-add="' + st.code + '" title="Refill ' + st.name + '">+</div>' +
        '<div class="work"><i></i></div>' +
        '<div class="svg">' + svg(st.icon) + '</div>' +
        '<div class="nm">' + st.name + '</div>' +
        '<div class="cnt' + (low ? ' low' : '') + '" data-cnt="' + st.code + '">' + qty + '</div>';
      // Left-click dispenses into the cup
      node.addEventListener('click', (e) => {
        if (e.target.closest('[data-add]')) return;   // the ＋ badge handles refill
        useStation(st, node);
      });
      // ＋ badge and right-click open the refill panel (in-game management)
      node.querySelector('[data-add]').addEventListener('click', (e) => {
        e.stopPropagation(); openStock(st.code);
      });
      node.addEventListener('contextmenu', (e) => { e.preventDefault(); openStock(st.code); });
      el.bench.appendChild(node);
    });
  }

  function refreshStock() {
    STATIONS.forEach(st => {
      const inv = ingByCode[st.code];
      const badge = el.bench.querySelector('[data-cnt="' + st.code + '"]');
      if (inv && badge) {
        badge.textContent = inv.quantity;
        badge.classList.toggle('low', inv.quantity <= inv.low_threshold);
      }
    });
  }

  /* ---- Ingredient carousel: single row, paged with ◀ ▶ arrows.
     Pure presentation — dispense/refill logic on each station is untouched. */
  function initBenchCarousel() {
    const track = el.bench;
    const viewport = track.parentElement;                 // .bench-viewport
    const prev = document.getElementById('benchPrev');
    const next = document.getElementById('benchNext');
    if (!prev || !next || !viewport) return;
    let page = 0;

    function metrics() {
      const first = track.querySelector('.station');
      if (!first) return null;
      const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      const cardW = first.getBoundingClientRect().width + gap;
      const vpW = viewport.clientWidth;
      const perPage = Math.max(1, Math.floor((vpW + gap) / cardW));
      const pages = Math.max(1, Math.ceil(track.children.length / perPage));
      return { cardW, gap, perPage, pages, vpW };
    }
    function update() {
      const m = metrics(); if (!m) return;
      page = Math.max(0, Math.min(page, m.pages - 1));
      const maxOffset = Math.max(0, track.scrollWidth - m.vpW);
      const offset = Math.min(page * m.perPage * m.cardW, maxOffset);
      track.style.transform = 'translateX(' + (-offset) + 'px)';
      prev.disabled = page <= 0;
      next.disabled = page >= m.pages - 1;
      prev.classList.toggle('is-disabled', prev.disabled);
      next.classList.toggle('is-disabled', next.disabled);
    }
    prev.addEventListener('click', () => { if (page > 0) { page--; update(); Sound.play('click'); } });
    next.addEventListener('click', () => { page++; update(); Sound.play('click'); });
    let rt;
    window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(update, 120); });
    // Highlight the last-picked ingredient (visual only, no logic change).
    track.addEventListener('click', (e) => {
      if (e.target.closest('[data-add]')) return;
      const st = e.target.closest('.station'); if (!st) return;
      track.querySelectorAll('.station.active').forEach(n => n.classList.remove('active'));
      st.classList.add('active');
    });
    update();
  }

  function useStation(st, node) {
    if (G.paused) return;                          // no making drinks while paused
    if (node.classList.contains('busy')) return;
    const inv = ingByCode[st.code];
    if (!inv || inv.quantity <= 0) {
      Sound.play('wrong');
      showRanOut(st.code, st.name);               // small warning: Open Storage / Continue
      return;
    }
    // animate machine working
    const dur = 300 + Math.random() * 250;
    node.classList.add('busy');
    const bar = node.querySelector('.work > i');
    bar.style.transition = 'width ' + dur + 'ms linear';
    requestAnimationFrame(() => bar.style.width = '100%');
    Sound.play(stationSfx[st.code] || 'pour');

    setTimeout(() => {
      node.classList.remove('busy');
      bar.style.transition = 'none'; bar.style.width = '0';
      inv.quantity = Math.max(0, inv.quantity - 1);   // local; server authoritative on serve
      refreshStock();
      addToCup(st);
    }, dur * speedMult());
  }

  function addToCup(st) {
    if (G.cup.length >= 6) { UI.toast('Cup is full', { light: true }); return; }
    G.cup.push(st.code);
    renderCup();
  }

  function renderCup() {
    if (G.cup.length === 0) {
      el.buildCup.innerHTML = '<span class="muted small">empty</span>';
    } else {
      const guess = matchDrink(G.cup);
      el.buildCup.innerHTML =
        '<div class="steam"><span></span><span></span><span></span></div>' +
        (guess ? svg(guess.icon) : svg('cup'));
    }
    el.madeList.innerHTML = G.cup.map(c =>
      '<span class="made-pill">' + (ingByCode[c] ? ingByCode[c].name : c) + '</span>').join('');
  }

  function matchDrink(cup) {
    const sorted = [...cup].sort().join(',');
    return S.drinks.find(d => [...d.recipe_codes].sort().join(',') === sorted);
  }

  /* ============================================================
     CUSTOMERS  (walk in -> think -> order -> serve/leave)
     ============================================================ */
  function spawnCustomer() {
    if (G.customers.length >= MAX_CUSTOMERS || G.paused) return;
    const pool = S.customers;
    const totW = pool.reduce((a, c) => a + +c.spawn_weight, 0);
    let r = Math.random() * totW, arche = pool[0];
    for (const c of pool) { r -= +c.spawn_weight; if (r <= 0) { arche = c; break; } }

    const avail = unlockedDrinks();
    const nDrinks = 1 + Math.floor(Math.random() * Math.min(3, Math.max(1, Math.ceil(G.level / 3))));
    const drinks = [];
    for (let i = 0; i < nDrinks; i++) {
      drinks.push(avail[Math.floor(Math.random() * avail.length)].code);
    }
    const diff = DIFFICULTY[G.settings.difficulty] || 1;
    // Longer, more forgiving patience window than before.
    const patience = (+arche.patience_base) * patienceMult() * diff * PATIENCE_SCALE + PATIENCE_BONUS;

    const cust = {
      id: G.nextCustId++,
      arche: arche,
      img: pickCustomerImg(),           // one random character image, kept for the whole visit
      line: makeOrderLine(drinks),      // natural-English spoken order
      drinks: drinks,
      served: drinks.map(() => null),   // null=pending, true=correct, false=wrong
      patienceMax: patience,
      patienceLeft: patience,
      tipFactor: +arche.tip_factor,
      // The customer first walks in and thinks before showing the order bubble.
      state: 'thinking',
      thinkLeft: THINK_MIN + Math.random() * (THINK_MAX - THINK_MIN),
    };
    G.customers.push(cust);
    renderCustomers();
    Sound.play('click');
  }

  // group a customer's drinks by code for a clear "Latte ×2" display
  function orderGroups(c) {
    const g = {};
    c.drinks.forEach((code, i) => {
      if (!g[code]) g[code] = { code: code, total: 0, remaining: 0 };
      g[code].total++;
      if (c.served[i] === null) g[code].remaining++;
    });
    return Object.values(g);
  }

  function fmtTime(s) {
    s = Math.max(0, Math.ceil(s));
    return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
  }

  function renderCustomers() {
    // Scene (visual busts at the counter)
    el.row.innerHTML = '';
    G.customers.forEach(c => el.row.appendChild(customerNode(c)));
    // Side panel (detailed order cards)
    renderOrders();
  }

  function customerNode(c) {
    const node = document.createElement('div');
    node.className = 'customer' + (c.state === 'thinking' ? ' thinking' : '');
    node.dataset.id = c.id;

    let bubbleInner, speechClass;
    if (c.state === 'thinking') {
      bubbleInner = '<span class="think-dots">·</span>';
      speechClass = ' thinking';
    } else if (c.served.every(s => s !== null)) {
      bubbleInner = '<span class="bubble-thanks">Thank you! ☕</span>';
      speechClass = ' speech';
    } else {
      // natural spoken order sentence
      bubbleInner = '<span class="bubble-line">' + c.line + '</span>';
      speechClass = ' speech';
    }
    const pct = Math.max(0, (c.patienceLeft / c.patienceMax) * 100);
    node.innerHTML =
      '<div class="cust-tag">#' + c.id + '</div>' +
      '<div class="bubble' + speechClass + '">' + bubbleInner + '</div>' +
      '<div class="avatar">' + custImg(c, 'cust-photo') + '</div>' +
      '<div class="pat bar thin"><span style="width:' + (c.state === 'thinking' ? 100 : pct) + '%"></span></div>';
    node.addEventListener('click', () => serveTo(c.id));
    return node;
  }

  function renderOrders() {
    el.orders.innerHTML = '';
    if (!G.customers.length) {
      el.orders.innerHTML = '<p class="small muted">Waiting for customers…</p>';
      return;
    }
    G.customers.forEach(c => {
      const card = document.createElement('div');
      card.className = 'order-card';
      card.dataset.oid = c.id;

      if (c.state === 'thinking') {
        card.innerHTML =
          '<div class="oc-head"><div class="oc-av">' + custImg(c, 'oc-photo') + '</div>' +
          '<div class="oc-name">Customer #' + c.id + '</div>' +
          '<div class="oc-timer muted">…</div></div>' +
          '<div class="small muted">Looking at the menu…</div>';
      } else {
        const lines = orderGroups(c).map(g => {
          const d = drinksByCode[g.code];
          const done = g.remaining === 0;
          return '<div class="order-drink' + (done ? ' done' : '') + '">' +
            '<span class="di">' + svg(d.icon) + '</span>' +
            '<span>' + d.name + '</span>' +
            '<span class="qty">×' + g.total + '</span></div>';
        }).join('');
        const pct = Math.max(0, (c.patienceLeft / c.patienceMax) * 100);
        card.innerHTML =
          '<div class="oc-head"><div class="oc-av">' + custImg(c, 'oc-photo') + '</div>' +
          '<div class="oc-name">Customer #' + c.id + ' <span class="small muted">' + c.arche.name + '</span></div>' +
          '<div class="oc-timer" data-timer>' + fmtTime(c.patienceLeft) + '</div></div>' +
          lines +
          '<div class="pat bar thin"><span style="width:' + pct + '%"></span></div>';
      }
      el.orders.appendChild(card);
    });
  }

  // Serve the current cup to a customer
  async function serveTo(custId) {
    const c = G.customers.find(x => x.id === custId);
    if (!c || c._leaving || c._serving) return;
    if (c.state === 'thinking') { UI.toast('They are still deciding…', { light: true }); return; }
    if (G.cup.length === 0) { UI.toast('Build a cup first!', { light: true }); return; }

    const cupKey = [...G.cup].sort().join(',');
    let targetIdx = c.drinks.findIndex((dc, i) =>
      c.served[i] === null && [...drinksByCode[dc].recipe_codes].sort().join(',') === cupKey);
    if (targetIdx === -1) {
      targetIdx = c.served.findIndex(s => s === null);
      if (targetIdx === -1) return;
    }
    const drinkCode = c.drinks[targetIdx];
    const patienceFrac = c.patienceLeft / c.patienceMax;

    const made = [...G.cup];
    G.cup = [];
    renderCup();

    c._serving = true;                       // guard against duplicate serve requests
    let res;
    try {
      res = await API.post('serve', {
        drink_code: drinkCode, made: made,
        patience: patienceFrac, combo: G.combo, tip_factor: c.tipFactor,
      });
    } catch (e) { res = { ok: false }; }
    c._serving = false;

    if (!res || !res.ok) { UI.toast('Serve failed', { light: true }); return; }

    c.served[targetIdx] = res.correct;
    applyServeResult(res, c);

    if (c.served.every(s => s !== null)) finishCustomer(c);
    else renderCustomers();
  }

  function applyServeResult(res, c) {
    G.coins = res.coins;
    G.combo = res.combo;
    G.satisfaction = res.satisfaction;
    G.level = res.level;
    // Tally the day for the end-of-day summary.
    G.dayStats.gross += res.earn || 0;
    G.dayStats.tips += res.tip || 0;
    G.dayStats.xp += res.xp || 0;
    G.dayStats.orders += 1;
    if (res.correct) G.dayStats.happy += 1; else G.dayStats.failed += 1;
    syncHud();

    const node = el.row.querySelector('[data-id="' + c.id + '"]');
    const rect = node ? node.getBoundingClientRect() : { left: innerWidth / 2, top: 200 };

    if (res.correct) {
      Sound.play('success'); Sound.play('coin');
      UI.coinPop(res.total, rect.left + 40, rect.top);
      if (res.combo >= 2) { UI.comboPop(res.combo); Sound.play('combo'); }
    } else {
      Sound.play('wrong');
      UI.toast('Wrong drink! 😖', { light: true });
    }
    if (res.leveled) { Sound.play('levelup'); UI.toast('Level Up! Lv ' + res.level, {}); }
    (res.unlocked_recipes || []).forEach(code => {
      if (drinksByCode[code]) UI.toast('New recipe: ' + drinksByCode[code].name, { light: true });
      if (!G.unlocked.includes(code)) G.unlocked.push(code);
    });
    (res.unlocked_achievements || []).forEach(a => UI.toast('🏆 ' + a.name + ' (+' + a.reward + ')', {}));
    if (res.unlocked_recipes && res.unlocked_recipes.length) renderMenu();
    G.dirty = true;
  }

  function finishCustomer(c) {
    const node = el.row.querySelector('[data-id="' + c.id + '"]');
    if (node) node.classList.add('leaving');
    Sound.play('success');
    setTimeout(() => {
      G.customers = G.customers.filter(x => x.id !== c.id);
      renderCustomers();
    }, 480);
  }

  async function customerLeavesAngry(c) {
    const node = el.row.querySelector('[data-id="' + c.id + '"]');
    if (node) node.classList.add('angry', 'leaving');
    Sound.play('angry');
    G.combo = 0;
    G.dayStats.failed += 1;
    let res;
    try { res = await API.post('cancel', {}); } catch (e) { res = null; }
    if (res && res.ok) { G.satisfaction = res.satisfaction; syncHud(); }
    UI.toast(c.arche.name + ' left unhappy…', { light: true });
    setTimeout(() => {
      G.customers = G.customers.filter(x => x.id !== c.id);
      renderCustomers();
    }, 480);
    G.dirty = true;
  }

  /* ============================================================
     HUD + clock + loops
     ============================================================ */
  function syncHud() {
    UI.setHud({
      coins: G.coins, level: G.level, combo: G.combo,
      sat: Math.round(G.satisfaction), day: G.day,
    });
    const chip = document.getElementById('comboChip');
    if (chip) chip.style.transform = G.combo >= 2 ? 'scale(1.08)' : 'scale(1)';
  }

  function clockStr() {
    const startMin = 9 * 60;
    const totalMin = startMin + Math.floor(G.gameTime * 3);
    const h = Math.floor(totalMin / 60) % 24;
    const m = totalMin % 60;
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
  }

  // Patience / think tick (10fps) — updates only dynamic bits for performance.
  function tickPatience() {
    if (G.paused) return;
    const dt = 0.1;
    G.customers.forEach(c => {
      if (c._leaving) return;
      if (c.state === 'thinking') {
        c.thinkLeft -= dt;
        if (c.thinkLeft <= 0) { c.state = 'ordering'; renderCustomers(); Sound.play('click'); }
        return;
      }
      c.patienceLeft -= dt * DRAIN_RATE;              // slower drain
      const pct = Math.max(0, (c.patienceLeft / c.patienceMax) * 100);
      const sceneBar = el.row.querySelector('[data-id="' + c.id + '"] .pat > span');
      if (sceneBar) sceneBar.style.width = pct + '%';
      const card = el.orders.querySelector('[data-oid="' + c.id + '"]');
      if (card) {
        const t = card.querySelector('[data-timer]'); if (t) t.textContent = fmtTime(c.patienceLeft);
        const b = card.querySelector('.pat > span'); if (b) b.style.width = pct + '%';
        card.classList.toggle('urgent', pct < 25);
      }
      if (c.patienceLeft <= 0 && !c._leaving) { c._leaving = true; customerLeavesAngry(c); }
    });
  }

  // Second tick: clock + spawn logic
  let spawnCooldown = 3;
  function tickSecond() {
    if (G.paused) return;
    G.gameTime++;
    document.querySelector('[data-clock]').textContent = clockStr();

    spawnCooldown -= 1;
    const desired = Math.min(MAX_CUSTOMERS, 2 + Math.floor(G.level / 4));
    if (spawnCooldown <= 0 && G.customers.length < desired) {
      spawnCustomer();
      spawnCooldown = Math.max(3, 6 - Math.floor(G.level / 3));   // a touch calmer
    }
    const startMin = 9 * 60;
    if (startMin + G.gameTime * 3 >= 18 * 60) endDay();
  }

  // Open the café for the current day: unpause and let customers arrive.
  function beginDay() {
    G.paused = false;
    G._dayEnding = false;
    spawnCooldown = 2;
    resetDayStats();
    spawnCustomer();
    setTimeout(() => { if (!G.paused) spawnCustomer(); }, 2000);
  }

  // Shop closes when the clock runs out: stop everything and show the summary.
  // The day does NOT advance until the player presses "Continue".
  async function endDay() {
    if (G._dayEnding) return;
    G._dayEnding = true;
    G.paused = true;
    await saveGame();
    G.customers = []; renderCustomers();
    Sound.play('levelup');
    showDaySummary();
  }

  // Advance to the next day (called by the summary's Continue button).
  async function continueToNextDay() {
    let res;
    try { res = await API.post('advanceDay', {}); } catch (e) { res = null; }
    if (res && res.ok) { G.day = res.day; G.gameTime = 0; G.combo = 0; syncHud(); }
    hideFlow('daySummaryOverlay');
    document.querySelector('[data-clock]').textContent = clockStr();
    // Before opening the café, remind the player if anything is running low.
    const low = lowStockNames();
    if (low.length) {
      document.getElementById('lowStockList').textContent =
        'Low on ' + low.slice(0, 5).join(', ') + (low.length > 5 ? ' and more' : '') +
        '. Would you like to refill before opening today’s café?';
      document.getElementById('lowStockReminder').classList.add('show');
    } else {
      UI.toast('Day ' + G.day + ' begins! ☕', {});
      beginDay();
    }
  }

  /* ============================================================
     SAVE
     ============================================================ */
  async function saveGame(manual) {
    let res;
    try {
      res = await API.post('save', {
        coins: G.coins, xp: G.xp, level: G.level, current_day: G.day,
        game_time: G.gameTime, best_combo: Math.max(G.combo, +S.progress.best_combo || 0),
      });
    } catch (e) { res = null; }
    G.dirty = false;
    const st = document.getElementById('saveStatus');
    if (st && res && res.ok) st.textContent = 'Saved at ' + res.saved_at;
    if (manual) UI.toast('Game saved ✓', {});
  }

  /* ============================================================
     IN-GAME INGREDIENT MANAGEMENT (refill / buy / adjust / empty)
     ============================================================ */
  // Group ingredients onto cozy "shelves" by category.
  const ING_CATEGORY = {
    espresso: 'Coffee & Tea', matcha: 'Coffee & Tea', water: 'Coffee & Tea', tea: 'Coffee & Tea', beans: 'Coffee & Tea',
    milk: 'Dairy', foam: 'Dairy', whip: 'Dairy', cream: 'Dairy',
    sugar: 'Sweeteners', caramel: 'Sweeteners', vanilla: 'Sweeteners',
    ice: 'Extras', chocolate: 'Extras', mint: 'Extras', fruit: 'Extras',
  };
  const SHELF_ORDER = ['Coffee & Tea', 'Dairy', 'Sweeteners', 'Extras', 'Other'];
  const ingCategory = (code) => ING_CATEGORY[code] || 'Other';
  const storageUI = { q: '', cat: 'all', sort: 'name' };

  function ingCardHtml(inv) {
    const low = inv.quantity <= inv.low_threshold;
    const pct = inv.capacity ? Math.round(inv.quantity / inv.capacity * 100) : 0;
    return '<div class="ing-card' + (low ? ' low' : '') + '" data-sc="' + inv.code + '">' +
      (low ? '<span class="low-badge">⚠ Low</span>' : '') +
      '<div class="ic-head"><div class="ic-ic">' + svg(inv.icon) + '</div>' +
      '<div class="grow"><div class="ic-name">' + inv.name + '</div>' +
      '<div class="ic-qty">' + inv.quantity + ' / ' + inv.capacity + ' · ' + pct + '%</div></div></div>' +
      '<div class="bar thin"><span style="width:' + pct + '%"></span></div>' +
      '<div class="quick-row">' +
        '<button class="q" data-quick="10" data-ing="' + inv.code + '">+10</button>' +
        '<button class="q" data-quick="20" data-ing="' + inv.code + '">+20</button>' +
        '<button class="q" data-quick="50" data-ing="' + inv.code + '">+50</button>' +
      '</div>' +
      '<div class="adj-row">' +
        '<button data-step="-1" data-ing="' + inv.code + '">−</button>' +
        '<span class="qv">' + inv.quantity + '</span>' +
        '<button data-step="1" data-ing="' + inv.code + '">+</button>' +
        '<button class="fillbtn" data-fill="' + inv.code + '">Refill</button>' +
      '</div>' +
      '<div class="ic-qty" style="margin-top:6px">@' + inv.unit_cost + ' coins each</div>' +
    '</div>';
  }

  function renderStorage(focusCode) {
    const q = storageUI.q.toLowerCase();
    let list = S.inventory.filter(inv =>
      (storageUI.cat === 'all' || ingCategory(inv.code) === storageUI.cat) &&
      (!q || inv.name.toLowerCase().includes(q)));
    const sorters = {
      name: (a, b) => a.name.localeCompare(b.name),
      qty: (a, b) => b.quantity - a.quantity,
      stock: (a, b) => (a.quantity / a.capacity) - (b.quantity / b.capacity),
    };
    list = list.slice().sort(sorters[storageUI.sort] || sorters.name);

    if (!list.length) { el.stockList.innerHTML = '<div class="storage-empty">No ingredients match your search.</div>'; return; }

    let html = '';
    if (storageUI.cat === 'all' && storageUI.sort === 'name') {
      const groups = {};
      list.forEach(inv => { const c = ingCategory(inv.code); (groups[c] = groups[c] || []).push(inv); });
      SHELF_ORDER.forEach(cat => {
        if (!groups[cat]) return;
        html += '<div class="shelf"><span class="shelf-label">' + cat + '</span>' +
          '<div class="shelf-board">' + groups[cat].map(ingCardHtml).join('') + '</div></div>';
      });
    } else {
      html = '<div class="shelf"><div class="shelf-board">' + list.map(ingCardHtml).join('') + '</div></div>';
    }
    el.stockList.innerHTML = html;
    if (focusCode) {
      const card = el.stockList.querySelector('[data-sc="' + focusCode + '"]');
      if (card) { card.scrollIntoView({ block: 'center' }); card.classList.add('armed'); }
    }
  }

  function buildStorageToolbar() {
    const bar = document.getElementById('storageToolbar');
    if (!bar || bar.dataset.built) return;
    bar.dataset.built = '1';
    const cats = ['all'].concat(SHELF_ORDER.filter(c => S.inventory.some(i => ingCategory(i.code) === c)));
    bar.innerHTML =
      '<input id="storageSearch" placeholder="🔍 Search ingredient…">' +
      '<select id="storageCat">' + cats.map(c => '<option value="' + c + '">' + (c === 'all' ? 'All shelves' : c) + '</option>').join('') + '</select>' +
      '<select id="storageSort"><option value="name">Sort: Name</option><option value="qty">Sort: Quantity</option><option value="stock">Sort: Low stock first</option></select>';
    bar.querySelector('#storageSearch').addEventListener('input', e => { storageUI.q = e.target.value; renderStorage(); });
    bar.querySelector('#storageCat').addEventListener('change', e => { storageUI.cat = e.target.value; renderStorage(); });
    bar.querySelector('#storageSort').addEventListener('change', e => { storageUI.sort = e.target.value; renderStorage(); });
  }

  /* ---- Ingredient Storage Room — ingredients placed on the storage.png cabinet.
     Reuses doStockChange for the actual (unchanged) refill/DB logic. ---- */
  const STORAGE_COLS = [25, 50, 75];       // x% of the 3 cabinet columns
  const STORAGE_ROWS = [30, 45, 60, 74];   // y% shelf lines (item bottoms sit here)
  const STORAGE_PER_PAGE = STORAGE_COLS.length * STORAGE_ROWS.length; // 12
  // Gameplay categories for the storage filters.
  const ING_CAT = {
    espresso: 'Coffee', water: 'Others', milk: 'Milk', foam: 'Topping', ice: 'Others',
    chocolate: 'Powder', caramel: 'Syrup', vanilla: 'Syrup', mint: 'Syrup',
    matcha: 'Powder', whip: 'Topping', sugar: 'Others',
  };
  const ingCat = (code) => ING_CAT[code] || 'Others';
  const LOW_FRAC = 0.25;                                 // below 25% of capacity = low
  const isLow = (inv) => inv.capacity > 0 && inv.quantity < inv.capacity * LOW_FRAC;
  const lowStockNames = () => S.inventory.filter(isLow).map((i) => i.name);

  let storagePage = 0, storageSel = null, refillPending = 0, storageResume = false;
  let storageSearch = '', storageCat = 'all', pendingDayStart = false, ranOutCode = null;

  function storageFiltered() {
    const q = storageSearch.trim().toLowerCase();
    return S.inventory.filter((inv) =>
      (storageCat === 'all' || ingCat(inv.code) === storageCat) &&
      (!q || inv.name.toLowerCase().includes(q)));
  }
  const storagePages = () => Math.max(1, Math.ceil(storageFiltered().length / STORAGE_PER_PAGE));

  function renderCabinet() {
    const cab = document.getElementById('storageCabinet');
    if (!cab) return;
    const list = storageFiltered();
    const pages = Math.max(1, Math.ceil(list.length / STORAGE_PER_PAGE));
    if (storagePage > pages - 1) storagePage = pages - 1;
    const start = storagePage * STORAGE_PER_PAGE;
    const items = list.slice(start, start + STORAGE_PER_PAGE);
    if (!items.length) {
      cab.innerHTML = '<div class="cab-empty">No ingredients match.</div>';
    } else {
      cab.innerHTML = items.map((inv, i) => {
        const col = i % STORAGE_COLS.length, row = Math.floor(i / STORAGE_COLS.length);
        const low = isLow(inv);
        return '<button class="ing-slot' + (low ? ' low' : '') + (inv.code === storageSel ? ' selected' : '') +
          '" data-slot="' + inv.code + '" style="left:' + STORAGE_COLS[col] + '%; top:' + STORAGE_ROWS[row] + '%;">' +
          '<span class="slot-ic">' + svg(inv.icon) + '</span>' +
          '<span class="slot-nm">' + inv.name + '</span>' +
          '<span class="slot-qty' + (low ? ' low' : '') + '">' + (low ? '⚠ ' : '') + inv.quantity + ' / ' + inv.capacity + '</span>' +
        '</button>';
      }).join('');
    }
    const prev = document.getElementById('cabPrev'), next = document.getElementById('cabNext');
    if (prev) prev.disabled = storagePage <= 0;
    if (next) next.disabled = storagePage >= pages - 1;
  }

  function selectSlot(code) {
    storageSel = code; refillPending = 0;
    renderCabinet(); showRefill(); Sound.play('click');
  }

  function refillPreview() {
    const inv = ingByCode[storageSel]; if (!inv) return;
    const room = inv.capacity - inv.quantity;
    if (refillPending > room) refillPending = room;
    document.getElementById('rpCost').textContent = refillPending * inv.unit_cost;
    document.getElementById('rpPreview').textContent = refillPending > 0
      ? ('New total: ' + (inv.quantity + refillPending) + ' / ' + inv.capacity)
      : 'Select an amount to buy';
  }

  function showRefill() {
    const inv = ingByCode[storageSel]; if (!inv) return;
    const panel = document.getElementById('refillPanel');
    document.getElementById('rpEmpty').classList.add('hidden');
    document.getElementById('rpContent').classList.remove('hidden');
    document.getElementById('rpIc').innerHTML = svg(inv.icon);
    document.getElementById('rpName').textContent = inv.name;
    document.getElementById('rpCur').textContent = inv.quantity;
    document.getElementById('rpMax').textContent = inv.capacity;
    panel.classList.toggle('low', isLow(inv));
    document.querySelectorAll('.rp-amt').forEach((b) => b.classList.remove('active'));
    refillPending = 0; refillPreview();
  }
  function hideRefill() {
    storageSel = null; refillPending = 0;
    const c = document.getElementById('rpContent'), e = document.getElementById('rpEmpty');
    if (c) c.classList.add('hidden');
    if (e) e.classList.remove('hidden');
    renderCabinet();
  }

  function openStock(focusCode) {                 // opens the Ingredient Storage room
    storagePage = 0; storageSel = null; refillPending = 0;
    storageSearch = ''; storageCat = 'all';
    const si = document.getElementById('storageSearch'); if (si) si.value = '';
    document.querySelectorAll('.cat-chip').forEach((c) => c.classList.toggle('active', c.dataset.cat === 'all'));
    hideRefill();
    if (focusCode && ingByCode[focusCode]) {
      const idx = storageFiltered().findIndex((i) => i.code === focusCode);
      if (idx >= 0) storagePage = Math.floor(idx / STORAGE_PER_PAGE);
    }
    renderCabinet();
    document.getElementById('storageRoom').classList.add('show');
    storageResume = !G.paused; G.paused = true;   // pause while managing stock
    if (focusCode && ingByCode[focusCode]) selectSlot(focusCode);
  }
  function closeStorageRoom() {
    document.getElementById('storageRoom').classList.remove('show');
    hideRefill();
    if (storageResume) G.paused = false;
    if (pendingDayStart) { pendingDayStart = false; beginDay(); }   // came here from the day-start reminder
  }

  function showRanOut(code, name) {
    ranOutCode = code;
    document.getElementById('ranOutMsg').textContent = '⚠ ' + name + ' has run out.';
    document.getElementById('ranOutPop').classList.add('show');
  }

  function initStorageRoom() {
    const cab = document.getElementById('storageCabinet');
    if (!cab) return;
    cab.addEventListener('click', (e) => {
      const slot = e.target.closest('.ing-slot'); if (slot) selectSlot(slot.dataset.slot);
    });
    document.getElementById('cabPrev').addEventListener('click', () => {
      if (storagePage > 0) { storagePage--; renderCabinet(); Sound.play('click'); }
    });
    document.getElementById('cabNext').addEventListener('click', () => {
      if (storagePage < storagePages() - 1) { storagePage++; renderCabinet(); Sound.play('click'); }
    });
    document.getElementById('storageClose').addEventListener('click', closeStorageRoom);
    document.getElementById('storageRoom').addEventListener('click', (e) => {
      if (e.target.id === 'storageRoom') closeStorageRoom();
    });
    // instant search
    const si = document.getElementById('storageSearch');
    if (si) si.addEventListener('input', () => { storageSearch = si.value; storagePage = 0; renderCabinet(); });
    // category filters
    document.querySelectorAll('.cat-chip').forEach((chip) => {
      chip.addEventListener('click', () => {
        storageCat = chip.dataset.cat; storagePage = 0;
        document.querySelectorAll('.cat-chip').forEach((c) => c.classList.remove('active'));
        chip.classList.add('active');
        renderCabinet(); Sound.play('click');
      });
    });
    // refill amount buttons
    document.querySelectorAll('.rp-amt').forEach((btn) => {
      btn.addEventListener('click', () => {
        const inv = ingByCode[storageSel]; if (!inv) return;
        const room = inv.capacity - inv.quantity;
        refillPending = btn.dataset.amt === 'max' ? room
          : Math.min(room, refillPending + parseInt(btn.dataset.amt, 10));
        document.querySelectorAll('.rp-amt').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        refillPreview(); Sound.play('click');
      });
    });
    document.getElementById('rpConfirm').addEventListener('click', async () => {
      if (!storageSel || refillPending <= 0) { UI.toast('Pick an amount first', { light: true }); return; }
      const res = await doStockChange(storageSel, { quick: refillPending });
      if (res) { UI.toast('Refilled ✓', {}); showRefill(); renderCabinet(); }
    });
    document.getElementById('rpCancel').addEventListener('click', hideRefill);

    // Start-of-day low-stock reminder
    document.getElementById('lowGoStorage').addEventListener('click', () => {
      document.getElementById('lowStockReminder').classList.remove('show');
      pendingDayStart = true; openStock();
    });
    document.getElementById('lowStartAnyway').addEventListener('click', () => {
      document.getElementById('lowStockReminder').classList.remove('show');
      UI.toast('Day ' + G.day + ' begins! ☕', {}); beginDay();
    });
    // In-game "ran out" warning
    document.getElementById('ranOutStorage').addEventListener('click', () => {
      document.getElementById('ranOutPop').classList.remove('show'); openStock(ranOutCode);
    });
    document.getElementById('ranOutContinue').addEventListener('click', () => {
      document.getElementById('ranOutPop').classList.remove('show');
    });
  }

  // Shared stock mutation used by the Storage Room AND the Prepare screen.
  // Buys the delta via setStock (server clamps + charges coins); tallies expenses.
  async function doStockChange(code, opts) {
    const inv = ingByCode[code];
    if (!inv) return null;
    let res;
    if (opts.fill) {
      res = await API.post('setStock', { ingredient: code, qty: inv.capacity });
    } else {
      const delta = opts.quick != null ? opts.quick : opts.step;
      const next = Math.max(0, Math.min(inv.capacity, inv.quantity + delta));
      if (next === inv.quantity) return null;
      res = await API.post('setStock', { ingredient: code, qty: next });
    }
    if (!res) return null;
    if (!res.ok) {
      UI.toast(res.error === 'insufficient_coins' ? 'Not enough coins' : 'Action failed', { light: true });
      Sound.play('wrong'); return null;
    }
    G.dayStats.expenses += Math.max(0, G.coins - res.coins);
    inv.quantity = res.quantity; G.coins = res.coins;
    Sound.play('coin'); syncHud(); refreshStock();
    return res;
  }

  function replaceIngCard(root, code) {
    const inv = ingByCode[code];
    const card = root.querySelector('[data-sc="' + code + '"]');
    if (inv && card) card.outerHTML = ingCardHtml(inv);
  }

  // One delegated handler powers both the storage room and the prepare grid.
  function bindStockActions(root, afterEach) {
    root.addEventListener('click', async (e) => {
      const t = e.target.closest('button'); if (!t) return;
      const code = t.dataset.ing || t.dataset.fill; if (!code) return;
      const opts = t.dataset.quick ? { quick: +t.dataset.quick }
        : t.dataset.step ? { step: parseInt(t.dataset.step, 10) }
        : t.dataset.fill ? { fill: true } : null;
      if (!opts) return;
      const res = await doStockChange(code, opts);
      if (res) { replaceIngCard(root, code); if (afterEach) afterEach(); }
    });
  }
  bindStockActions(el.stockList);

  /* ============================================================
     IN-GAME UPGRADES OVERLAY
     ============================================================ */
  function upgradeCost(u) {
    return Math.round(u.base_cost * Math.pow(parseFloat(u.cost_growth), +u.level));
  }
  function renderUpgrades() {
    const list = document.getElementById('upgradeList');
    // Only equipment/interior here for the quick in-game panel.
    const items = S.upgrades.filter(u => ['equipment', 'interior', 'staff'].includes(u.category));
    list.innerHTML = items.map(u => {
      const lvl = +u.level, maxed = lvl >= +u.max_level;
      const cost = upgradeCost(u);
      const stars = '★'.repeat(lvl) + '☆'.repeat(Math.max(0, +u.max_level - lvl));
      return '<div class="row-item">' +
        '<div class="ic">' + svg(u.icon) + '</div>' +
        '<div class="grow"><div class="b">' + u.name + ' <span class="lv">Lv.' + lvl + '/' + u.max_level + '</span></div>' +
        '<div class="stars">' + stars + '</div>' +
        '<div class="small muted">' + (u.description || '') + '</div></div>' +
        (maxed ? '<span class="chip">MAX</span>'
               : '<button class="btn btn-dark btn-sm" data-upg="' + u.code + '">' + cost + ' ' + '⬆</button>') +
        '</div>';
    }).join('');
  }
  document.getElementById('upgradeList').addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-upg]'); if (!btn) return;
    let res;
    try { res = await API.post('upgrade', { code: btn.dataset.upg }); } catch (x) { res = null; }
    if (!res || !res.ok) {
      UI.toast(res && res.error === 'insufficient_coins' ? 'Not enough coins (need ' + res.need + ')'
        : res && res.error === 'maxed' ? 'Already max level' : 'Upgrade failed', { light: true });
      Sound.play('wrong');
      return;
    }
    // Update the local upgrade level + coins, then keep inventory in sync.
    const u = S.upgrades.find(x => x.code === btn.dataset.upg);
    if (u) u.level = res.level;
    G.coins = res.coins;
    Sound.play('levelup'); UI.toast('Upgraded to Lv ' + res.level + ' ✓', {});
    syncHud(); renderUpgrades();
    refreshFromServer();          // capacity upgrades expand inventory server-side
  });

  // Pull fresh inventory/upgrades from the server (keeps everything in sync).
  async function refreshFromServer() {
    let d;
    try { d = await API.get('state'); } catch (e) { return; }
    if (!d || !d.ok) return;
    S.inventory = d.state.inventory;
    S.inventory.forEach(i => ingByCode[i.code] = i);
    S.upgrades = d.state.upgrades;
    G.coins = +d.state.progress.coins;
    refreshStock(); syncHud();
  }

  /* ============================================================
     IN-GAME REVENUE / STATS OVERLAY
     ============================================================ */
  async function openStats() {
    UI.openModal('statsModal');
    const body = document.getElementById('statsBody');
    body.innerHTML = '<p class="muted text-center">Loading…</p>';
    let d;
    try { d = await API.get('revenue'); } catch (e) { d = null; }
    if (!d || !d.ok) { body.innerHTML = '<p class="text-center">Could not load stats.</p>'; return; }
    const r = d.report;
    const tile = (v, k) => '<div class="panel stat-tile"><div class="v">' + v + '</div><div class="k">' + k + '</div></div>';
    body.innerHTML = '<div class="grid-3">' +
      tile((r.gross + r.tips), 'Earnings') +
      tile(r.completed, 'Completed') +
      tile(r.cancelled, 'Cancelled') +
      tile(r.tips, 'Tips') +
      tile(r.expenses, 'Expenses') +
      tile(r.profit, 'Profit') +
      tile(r.avg_order, 'Avg Order') +
      tile(r.popular_drink, 'Popular') +
      tile(Math.round(G.satisfaction) + '%', 'Satisfaction') +
      '</div>';
  }

  /* ============================================================
     SETTINGS + WIRE UP CONTROLS
     ============================================================ */
  document.getElementById('btnNewCup').addEventListener('click', () => { G.cup = []; renderCup(); Sound.play('click'); });
  document.getElementById('btnClear').addEventListener('click', () => { G.cup = []; renderCup(); Sound.play('click'); });
  document.getElementById('btnSettings').addEventListener('click', () => { Sound.play('click'); openSettings(); });
  document.getElementById('btnSaveGame').addEventListener('click', () => saveGame(true));
  document.getElementById('btnNextDay').addEventListener('click', () => { UI.closeModal('settingsModal'); endDay(); });
  document.getElementById('btnInventory').addEventListener('click', () => { Sound.play('click'); openStock(); });
  document.getElementById('btnUpgrades').addEventListener('click', () => { Sound.play('click'); renderUpgrades(); UI.openModal('upgradeModal'); });
  document.getElementById('btnStats').addEventListener('click', () => { Sound.play('click'); openStats(); });

  // Pause / resume — toggles the existing G.paused flag the game loop already honours.
  const btnPause = document.getElementById('btnPause');
  const pauseVeil = document.getElementById('pauseVeil');
  if (btnPause) {
    btnPause.addEventListener('click', () => {
      G.paused = !G.paused;
      btnPause.classList.toggle('active', G.paused);
      if (pauseVeil) pauseVeil.classList.toggle('show', G.paused);
      Sound.play('click');
    });
  }

  function openSettings() {
    document.getElementById('setMusic').checked = !!+G.settings.music_on;
    document.getElementById('setSfx').checked = !!+G.settings.sfx_on;
    document.getElementById('setMusicVol').value = G.settings.music_vol;
    document.getElementById('setSfxVol').value = G.settings.sfx_vol;
    UI.openModal('settingsModal');
  }
  function applyAudio() {
    Sound.configure({
      music: !!+G.settings.music_on, sfx: !!+G.settings.sfx_on,
      musicVol: +G.settings.music_vol, sfxVol: +G.settings.sfx_vol,
    });
  }
  ['setMusic', 'setSfx', 'setMusicVol', 'setSfxVol'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
      G.settings.music_on = document.getElementById('setMusic').checked ? 1 : 0;
      G.settings.sfx_on = document.getElementById('setSfx').checked ? 1 : 0;
      G.settings.music_vol = +document.getElementById('setMusicVol').value;
      G.settings.sfx_vol = +document.getElementById('setSfxVol').value;
      applyAudio();
      API.post('settings', G.settings);
    });
  });

  // Resume audio on first user gesture (browser autoplay policy)
  function firstGesture() {
    Sound.resume(); applyAudio();
    document.removeEventListener('click', firstGesture);
    document.removeEventListener('keydown', firstGesture);
  }
  document.addEventListener('click', firstGesture);
  document.addEventListener('keydown', firstGesture);

  /* ============================================================
     GAME FLOW — story ▸ tutorial ▸ prepare ▸ start day,
     end-of-day summary, and the slide-out sidebar menu.
     ============================================================ */
  const FLOW = {
    uid: (window.BREW && window.BREW.user && window.BREW.user.id) || 0,
    key() { return 'brew_onboarded_' + this.uid; },
    done() { try { return localStorage.getItem(this.key()) === '1'; } catch (e) { return false; } },
    markDone() { try { localStorage.setItem(this.key(), '1'); } catch (e) {} },
  };
  const $ = (id) => document.getElementById(id);
  const showFlow = (id) => { const o = $(id); if (o) o.classList.add('show'); };
  function hideFlow(id) { const o = $(id); if (o) o.classList.remove('show'); }

  /* ---- typewriter text ---- */
  let typeTimer = null, typingDone = true, typingEl = null, typingFull = '';
  function typeText(elm, text) {
    clearInterval(typeTimer);
    typingEl = elm; typingFull = text; typingDone = false;
    elm.classList.add('typing-caret');
    let i = 0; elm.textContent = '';
    typeTimer = setInterval(() => {
      elm.textContent = text.slice(0, ++i);
      if (i >= text.length) { finishTyping(); }
    }, 22);
  }
  function finishTyping() {
    clearInterval(typeTimer);
    if (typingEl) { typingEl.textContent = typingFull; typingEl.classList.remove('typing-caret'); }
    typingDone = true;
  }

  /* ---- Story ---- */
  const STORY_PAGES = [
    { name: 'A Letter Arrives', text: "You've just inherited a small coffee shop from your family. The café has been closed for a very long time…" },
    { name: 'Your Dream', text: "Your dream is to rebuild it into the most popular coffee shop in town. Every single customer matters." },
    { name: 'Become the Best', text: "Manage your ingredients wisely, prepare delicious drinks, decorate your café, and become the best barista in town!" },
  ];
  let storyIdx = 0;
  function renderStory() {
    const p = STORY_PAGES[storyIdx];
    $('storyName').textContent = p.name;
    typeText($('storyText'), p.text);
    $('storyDots').innerHTML = STORY_PAGES.map((_, i) => '<i class="' + (i === storyIdx ? 'on' : '') + '"></i>').join('');
    $('btnStoryNext').textContent = storyIdx >= STORY_PAGES.length - 1 ? 'Begin ▸' : 'Next ▸';
  }

  /* ---- Tutorial ---- */
  const TUT_STEPS = [
    { ic: '🧑‍🍳', title: 'Customer Orders', text: 'Customers walk in and speak their order in a bubble. It also appears in the Customer Queue on the right.' },
    { ic: '🥤', title: 'Prepare Drinks', text: 'Tap ingredient stations on the bench to drop them into the cup, then tap the matching customer to serve.' },
    { ic: '📖', title: 'Recipe Book', text: 'Not sure what goes in a drink? Open the floating Recipe Book (bottom-right) and tap any menu drink to read its recipe.' },
    { ic: '☕', title: 'Coffee Machine', text: 'Each station dispenses one ingredient and takes a moment to work — plan your cups so orders keep flowing.' },
    { ic: '🫙', title: 'Ingredients', text: 'Every ingredient you use is consumed from your stock. Refill from Storage before a shelf runs empty.' },
    { ic: '⏳', title: 'Customer Patience', text: 'Each customer has a patience bar. Serve them before it empties, or they leave unhappy and your reputation drops.' },
    { ic: '🪙', title: 'Coins', text: 'Correct drinks earn coins and tips. Faster service and serving in a combo streak earns even more!' },
    { ic: '⭐', title: 'Reputation', text: 'Happy customers raise your reputation; failed orders lower it. Keep it high to grow the best café in town.' },
  ];
  let tutIdx = 0, tutStandalone = false;
  function renderTut() {
    const s = TUT_STEPS[tutIdx];
    $('tutIcon').textContent = s.ic;
    $('tutTitle').textContent = s.title;
    $('tutText').textContent = s.text;
    $('tutStep').textContent = (tutIdx + 1) + ' / ' + TUT_STEPS.length;
    $('btnTutPrev').style.visibility = tutIdx === 0 ? 'hidden' : 'visible';
    $('btnTutNext').textContent = tutIdx >= TUT_STEPS.length - 1 ? 'Finish ▸' : 'Next ▸';
  }
  function finishTutorial() {
    hideFlow('tutorialOverlay');
    if (tutStandalone) { tutStandalone = false; if (!FLOW.done()) return; G.paused = false; return; }
    showPrepare();
  }

  /* ---- Prepare Your Café ---- */
  let prepareBound = false;
  function showPrepare() {
    $('prepareGrid').innerHTML = S.inventory.map(ingCardHtml).join('');
    if (!prepareBound) { bindStockActions($('prepareGrid'), null); prepareBound = true; }
    showFlow('prepareOverlay');
  }

  /* ---- Day Summary ---- */
  function starHtml(sat) {
    const n = Math.max(1, Math.min(5, Math.round(sat / 20)));
    return '★'.repeat(n) + '<span class="off">' + '☆'.repeat(5 - n) + '</span>';
  }
  function showDaySummary() {
    const d = G.dayStats;
    const revenue = d.gross + d.tips;
    const profit = revenue - d.expenses;
    $('sumDay').textContent = G.day;
    $('sumNextDay').textContent = G.day + 1;
    $('sumRating').innerHTML = starHtml(G.satisfaction);
    const row = (k, v, hl) => '<div class="sum-row' + (hl ? ' hl' : '') + '"><span class="k">' + k + '</span><span class="v">' + v + '</span></div>';
    $('summaryGrid').innerHTML =
      row('Revenue', '$' + revenue) +
      row('Orders', d.orders) +
      row('Happy', d.happy) +
      row('Failed', d.failed) +
      row('Tips', '$' + d.tips) +
      row('Expenses', '$' + d.expenses) +
      row('EXP Earned', '+' + d.xp) +
      row('Coins Earned', '$' + revenue) +
      row('Profit', '$' + profit, true);
    showFlow('daySummaryOverlay');
  }

  /* ---- Sidebar ---- */
  let menuResume = false;
  function openSidebar() {
    $('gameSidebar').classList.add('show');
    $('sidebarBackdrop').classList.add('show');
    const pp = $('menuPausePopup'); if (pp) pp.classList.add('show');
    menuResume = !G.paused; G.paused = true;      // pause the whole game while the menu is open
  }
  function closeSidebar() {
    $('gameSidebar').classList.remove('show');
    $('sidebarBackdrop').classList.remove('show');
    const pp = $('menuPausePopup'); if (pp) pp.classList.remove('show');
    if (menuResume) { G.paused = false; menuResume = false; }
  }
  function nav(route) { location.href = (window.BREW.base || '') + '/index.php' + (route ? '?url=' + route : ''); }

  function startOnboarding() {
    G.paused = true;
    storyIdx = 0;
    showFlow('storyOverlay');
    renderStory();
  }

  // Wire every flow control once.
  function initFlow() {
    // Story
    $('btnStoryNext').addEventListener('click', () => {
      if (!typingDone) { finishTyping(); return; }
      if (storyIdx < STORY_PAGES.length - 1) { storyIdx++; renderStory(); }
      else { hideFlow('storyOverlay'); tutIdx = 0; showFlow('tutorialOverlay'); renderTut(); }
    });
    $('btnStorySkip').addEventListener('click', () => {
      finishTyping(); hideFlow('storyOverlay'); tutIdx = 0; showFlow('tutorialOverlay'); renderTut();
    });
    // Tutorial
    $('btnTutNext').addEventListener('click', () => {
      if (tutIdx < TUT_STEPS.length - 1) { tutIdx++; renderTut(); } else finishTutorial();
    });
    $('btnTutPrev').addEventListener('click', () => { if (tutIdx > 0) { tutIdx--; renderTut(); } });
    $('btnTutSkip').addEventListener('click', finishTutorial);
    // Prepare
    $('btnOpenShop').addEventListener('click', () => {
      $('confirmDay').textContent = G.day; $('confirmDay2').textContent = G.day;
      showFlow('startDayOverlay');
    });
    // Start-day confirmation
    $('btnStartDay').addEventListener('click', () => {
      hideFlow('startDayOverlay'); hideFlow('prepareOverlay');
      FLOW.markDone();
      document.querySelector('[data-clock]').textContent = clockStr();
      beginDay();
      Sound.play('levelup');
    });
    $('btnCancelStart').addEventListener('click', () => hideFlow('startDayOverlay'));
    // Day summary
    $('btnContinueDay').addEventListener('click', () => continueToNextDay());
    $('btnSummaryMenu').addEventListener('click', () => { saveGame(); nav(''); });
    // Sidebar
    $('btnHamburger').addEventListener('click', openSidebar);
    $('btnSidebarClose').addEventListener('click', closeSidebar);
    $('sidebarBackdrop').addEventListener('click', closeSidebar);
    // "Game Paused" popup — both buttons close the menu and resume
    if ($('ppResume')) $('ppResume').addEventListener('click', closeSidebar);
    if ($('ppClose')) $('ppClose').addEventListener('click', closeSidebar);
    initStorageRoom();
    document.querySelectorAll('.sb-item').forEach(btn => {
      btn.addEventListener('click', () => {
        const what = btn.dataset.sb;
        closeSidebar();
        Sound.play('click');
        switch (what) {
          case 'storage': openStock(); break;                   // Ingredient Storage room
          case 'revenue': saveGame(); nav('game/stats'); break;
          case 'leaderboard': saveGame(); nav('game/leaderboard'); break;
          case 'achievements': saveGame(); nav('game/achievements'); break;
          case 'aiscan': saveGame(); nav('aiscan'); break;
          case 'tutorial': tutStandalone = true; tutIdx = 0; showFlow('tutorialOverlay'); renderTut(); break;
          case 'settings': openSettings(); break;
          case 'logout': saveGame(); nav('auth/logout'); break;
        }
      });
    });
  }

  /* ============================================================
     BOOT
     ============================================================ */
  function boot() {
    renderMenu(); renderBench(); renderCup(); renderCustomers();
    initBenchCarousel();
    syncHud();
    document.querySelector('[data-clock]').textContent = clockStr();
    const first = unlockedDrinks()[0];
    if (first) inspectDrink(first.code);

    initFlow();      // wire story / tutorial / prepare / day-summary / sidebar

    setInterval(tickPatience, 100);
    setInterval(tickSecond, 1000);
    setInterval(() => { if (G.dirty) saveGame(); }, 20000);
    window.addEventListener('beforeunload', () => {
      navigator.sendBeacon && navigator.sendBeacon(window.BREW.api + 'save', new Blob([JSON.stringify({
        coins: G.coins, xp: G.xp, level: G.level, current_day: G.day, game_time: G.gameTime,
      })], { type: 'application/json' }));
    });

    // First-time players get the full onboarding flow; everyone else opens up.
    if (!FLOW.done() && +G.day === 1 && +G.gameTime === 0) {
      startOnboarding();
    } else {
      beginDay();
    }

    // Deep-link from the secondary-page sidebar (?open=storage|settings|tutorial).
    try {
      const op = new URLSearchParams(location.search).get('open');
      if (op && FLOW.done()) {
        if (op === 'storage') openStock();
        else if (op === 'settings') openSettings();
        else if (op === 'tutorial') { tutStandalone = true; tutIdx = 0; showFlow('tutorialOverlay'); renderTut(); }
      }
    } catch (e) {}
  }
  boot();
})();
