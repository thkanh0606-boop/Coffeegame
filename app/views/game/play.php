<?php $pageScript = 'game'; ?>
<script>
  // Server-hydrated boot state (read once by game.js).
  window.BREW_STATE = <?= json_encode($state) ?>;
  window.BREW_SVG = <?= json_encode(SvgLib::exportAll()) ?>;
  // Customer images (loaded from public/pic/anh*.png). Auto-discovers any
  // future anh*.png added to the folder so new customers just work.
<?php
  $charFiles = glob(PUBLIC_PATH . '/pic/anh*.png') ?: [];
  natsort($charFiles);
  $charImgs = array_values(array_map(fn($f) => 'pic/' . basename($f), $charFiles));
  if (empty($charImgs)) { $charImgs = ['pic/anh1.png', 'pic/anh2.png']; }
?>
  window.BREW_CUSTOMERS = <?= json_encode($charImgs) ?>;
  window.BREW_ASSET = "<?= BASE_URL ?>/";
</script>

<div class="board board-game">
  <!-- ==================== TOP STATUS BAR (full width) ==================== -->
  <div class="stage-head status-bar">
    <button class="hamburger" id="btnHamburger" title="Menu" aria-label="Open menu"><span></span><span></span><span></span></button>
    <div class="day-box">
      <div class="d">DAY <span data-day>1</span></div>
      <div class="t" data-clock>09:00</div>
    </div>
    <span class="chip chip-dark" title="Coins"><?= icon('coin','svg') ?><span data-coins>0</span></span>
    <span class="chip" title="Gems"><?= icon('gem','svg') ?><span data-gems><?= (int)($state['progress']['gems'] ?? 0) ?></span></span>
    <span class="chip" title="Reputation"><?= icon('happy','svg') ?><span data-sat>100</span>%</span>
    <span class="chip" id="comboChip" title="Combo"><?= icon('flame','svg') ?>x<span data-combo>0</span></span>
    <div class="status-right">
      <button class="btn btn-icon" id="btnPause" title="Pause / Resume"><?= icon('pause','svg') ?></button>
      <button class="btn btn-icon" id="btnSettings" title="Menu"><?= icon('gear','svg') ?></button>
    </div>
  </div>

  <!-- ==================== CAFÉ SCENE (left 60%) ==================== -->
  <div class="stage">
    <div class="scene" id="scene">
      <!-- Customers walk in and line up at the counter -->
      <div class="customer-row" id="customerRow"><!-- customers injected here --></div>

      <!-- Small floating quick-access toolbar (also in the menu) -->
      <div class="game-toolbar">
        <button class="tool-btn" id="btnInventory" title="Ingredients"><?= icon('milk','svg') ?><span>Stock</span></button>
        <button class="tool-btn" id="btnUpgrades" title="Upgrades"><?= icon('up','svg') ?><span>Upgrade</span></button>
        <button class="tool-btn" id="btnStats" title="Statistics"><?= icon('coin','svg') ?><span>Revenue</span></button>
      </div>

      <!-- Paused overlay -->
      <div class="pause-veil" id="pauseVeil"><div class="pause-card"><?= icon('pause','svg') ?><span>Paused</span></div></div>
    </div>
  </div>

  <!-- ==================== WORKSTATION (right 40%) ==================== -->
  <div class="work">
    <!-- Customer Order -->
    <div class="panel panel-pad">
      <span class="label-tab panel-title">Customer Order</span>
      <div class="order-list mt-3" id="orderQueue"></div>
      <p class="small muted mt-2">Build a cup, then tap the matching customer to serve.</p>
    </div>

    <!-- Drink Menu -->
    <div class="panel panel-pad">
      <span class="label-tab panel-title">Drink Menu</span>
      <div class="menu-grid mt-3" id="menuGrid"></div>
    </div>

    <!-- Item Detail / Recipe -->
    <div class="panel panel-pad">
      <span class="label-tab panel-title">Item Detail</span>
      <div id="itemDetail" class="mt-3 text-center">
        <p class="muted small">Tap a drink in the menu to see its recipe.</p>
      </div>
    </div>

    <!-- Coffee Making Area -->
    <div class="panel panel-pad make-panel">
      <span class="label-tab panel-title">Coffee Making</span>
      <div class="build-tray mt-3">
        <div class="build-cup" id="buildCup">
          <span class="muted small">empty</span>
        </div>
        <div class="made-list" id="madeList"></div>
        <div class="flex gap-2 wrap">
          <button class="btn btn-sm" id="btnNewCup"><?= icon('cupstack','svg') ?>New Cup</button>
          <button class="btn btn-sm" id="btnClear"><?= icon('trash','svg') ?>Trash</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== INGREDIENT BAR (full width, single-row carousel) ==================== -->
  <div class="bench-bar">
    <button class="bench-arrow" id="benchPrev" type="button" aria-label="Previous ingredients">◀</button>
    <div class="bench-viewport">
      <div class="bench" id="bench"><!-- ingredient stations injected --></div>
    </div>
    <button class="bench-arrow" id="benchNext" type="button" aria-label="Next ingredients">▶</button>
  </div>
</div>

<!-- ==================== SETTINGS MODAL ==================== -->
<div class="modal-backdrop" id="settingsModal">
  <div class="modal">
    <div class="flex between items-center mb-3">
      <h2>Menu</h2>
      <button class="btn btn-sm" data-close-modal>✕</button>
    </div>

    <div class="field flex between items-center">
      <label style="margin:0">Music</label>
      <input type="checkbox" id="setMusic">
    </div>
    <div class="field flex between items-center">
      <label style="margin:0">Sound Effects</label>
      <input type="checkbox" id="setSfx">
    </div>
    <div class="field">
      <label>Music Volume</label>
      <input type="range" id="setMusicVol" min="0" max="1" step="0.05">
    </div>
    <div class="field">
      <label>SFX Volume</label>
      <input type="range" id="setSfxVol" min="0" max="1" step="0.05">
    </div>

    <div class="flex gap-2 mt-3">
      <button class="btn btn-dark grow" id="btnSaveGame"><?= icon('star','svg') ?>Save Game</button>
      <button class="btn grow" id="btnNextDay">End Day ▸</button>
    </div>
    <p class="small muted text-center mt-2" id="saveStatus">Auto-saves every 20s.</p>
  </div>
</div>

<!-- ==================== INGREDIENT MANAGEMENT (in-game) ==================== -->
<div class="modal-backdrop" id="stockModal">
  <div class="modal modal-wide">
    <div class="flex between items-center mb-2">
      <h2>📦 Storage Room</h2>
      <button class="btn btn-sm" data-close-modal>✕</button>
    </div>
    <p class="small muted mb-3">Search, sort and refill your shelves. Buying stock spends coins instantly.</p>
    <div class="storage-toolbar" id="storageToolbar"></div>
    <div id="stockList" class="storage-shelves"></div>
  </div>
</div>

<!-- ==================== UPGRADES (in-game overlay) ==================== -->
<div class="modal-backdrop" id="upgradeModal">
  <div class="modal modal-wide">
    <div class="flex between items-center mb-3">
      <h2>Upgrades</h2>
      <button class="btn btn-sm" data-close-modal>✕</button>
    </div>
    <div id="upgradeList"></div>
    <p class="small muted text-center mt-2">More categories on the <a href="<?= url('game/shop') ?>">Decorate</a> &amp; <a href="<?= url('game/upgrades') ?>">full Upgrades</a> pages.</p>
  </div>
</div>

<!-- ==================== REVENUE / STATS (in-game overlay) ==================== -->
<div class="modal-backdrop" id="statsModal">
  <div class="modal modal-wide">
    <div class="flex between items-center mb-3">
      <h2>Today &amp; Totals</h2>
      <button class="btn btn-sm" data-close-modal>✕</button>
    </div>
    <div id="statsBody"><p class="muted text-center">Loading…</p></div>
    <p class="small muted text-center mt-2">Full charts on the <a href="<?= url('game/stats') ?>">Statistics</a> page.</p>
  </div>
</div>

<!-- ==================== HAMBURGER SIDEBAR ==================== -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<aside class="game-sidebar" id="gameSidebar" aria-hidden="true">
  <div class="sb-head">
    <span class="sb-logo"><span class="logo-badge"><img src="<?= asset('pic/Cardmoi_PLT_Trang.png') ?>" alt="PLT"></span> Café Menu</span>
    <button class="sb-close" id="btnSidebarClose" aria-label="Close">✕</button>
  </div>
  <nav class="sb-nav">
    <button class="sb-item" data-sb="storage"><span class="sb-ic"><?= icon('box','svg') ?></span> Ingredient Storage</button>
    <button class="sb-item" data-sb="revenue"><span class="sb-ic"><?= icon('chart','svg') ?></span> Revenue</button>
    <button class="sb-item" data-sb="leaderboard"><span class="sb-ic"><?= icon('trophy','svg') ?></span> Leaderboard</button>
    <button class="sb-item" data-sb="achievements"><span class="sb-ic"><?= icon('medal','svg') ?></span> Achievements</button>
    <button class="sb-item" data-sb="aiscan"><span class="sb-ic"><?= icon('scan','svg') ?></span> AI Scan</button>
    <button class="sb-item" data-sb="tutorial"><span class="sb-ic"><?= icon('book','svg') ?></span> Replay Tutorial</button>
    <button class="sb-item" data-sb="settings"><span class="sb-ic"><?= icon('gear','svg') ?></span> Settings</button>
    <div class="sb-spacer"></div>
    <button class="sb-item danger" data-sb="logout"><span class="sb-ic"><?= icon('power','svg') ?></span> Logout</button>
  </nav>
</aside>

<!-- ==================== GAME PAUSED POPUP (menu open) ==================== -->
<div class="pause-popup" id="menuPausePopup">
  <div class="pp-card">
    <div class="pp-ic"><?= icon('pause','svg') ?></div>
    <h3>Game Paused</h3>
    <p class="muted">The game is temporarily paused while the menu is open.</p>
    <div class="pp-btns">
      <button class="btn btn-dark" id="ppResume">Resume</button>
      <button class="btn btn-light" id="ppClose">Close Menu</button>
    </div>
  </div>
</div>

<!-- ==================== INGREDIENT STORAGE ROOM ==================== -->
<div class="flow-overlay storage-room" id="storageRoom">
  <button class="storage-close" id="storageClose" aria-label="Close">✕</button>
  <div class="storage-inner">
    <!-- LEFT 60%: header + search/filter + cabinet -->
    <div class="storage-left">
      <div class="storage-head">
        <div class="sh-title">Storage Room</div>
        <span class="chip chip-dark coin-chip"><?= icon('coin','svg') ?> <span data-coins>0</span></span>
      </div>
      <div class="storage-tools">
        <input type="text" id="storageSearch" placeholder="Search ingredient…" autocomplete="off">
        <div class="storage-cats" id="storageCats">
          <button class="cat-chip active" data-cat="all">All</button>
          <button class="cat-chip" data-cat="Coffee">Coffee</button>
          <button class="cat-chip" data-cat="Milk">Milk</button>
          <button class="cat-chip" data-cat="Syrup">Syrup</button>
          <button class="cat-chip" data-cat="Powder">Powder</button>
          <button class="cat-chip" data-cat="Topping">Topping</button>
          <button class="cat-chip" data-cat="Others">Others</button>
        </div>
      </div>
      <div class="storage-stage">
        <button class="cab-nav prev" id="cabPrev" aria-label="Previous page">◀</button>
        <div class="storage-cabinet" id="storageCabinet"><!-- ingredient slots injected --></div>
        <button class="cab-nav next" id="cabNext" aria-label="Next page">▶</button>
      </div>
    </div>

    <!-- RIGHT 40%: refill panel (always present) -->
    <aside class="refill-panel" id="refillPanel">
      <div class="rp-empty" id="rpEmpty">
        <div class="rp-empty-ic"><?= icon('box','svg') ?></div>
        <p class="muted">Tap an ingredient on the shelf to check and refill it.</p>
      </div>
      <div class="rp-content hidden" id="rpContent">
        <div class="rp-ic" id="rpIc"></div>
        <h3 id="rpName">—</h3>
        <div class="rp-stock"><span id="rpCur">0</span> <span class="muted">/</span> <span id="rpMax">0</span></div>
        <div class="rp-warn" id="rpWarn">⚠ Low Stock</div>
        <div class="rp-cost">Refill cost: <span class="coin-mini"><?= icon('coin','svg') ?></span> <span id="rpCost">0</span></div>
        <div class="rp-amounts">
          <button class="rp-amt" data-amt="10">+10</button>
          <button class="rp-amt" data-amt="20">+20</button>
          <button class="rp-amt" data-amt="50">+50</button>
          <button class="rp-amt" data-amt="max">MAX</button>
        </div>
        <div class="rp-preview" id="rpPreview">Select an amount to buy</div>
        <div class="rp-confirm">
          <button class="btn btn-dark btn-block" id="rpConfirm">Confirm</button>
          <button class="btn btn-light btn-block" id="rpCancel">Cancel</button>
        </div>
      </div>
    </aside>
  </div>
</div>

<!-- ==================== START-OF-DAY LOW STOCK REMINDER ==================== -->
<div class="flow-overlay confirm-overlay" id="lowStockReminder">
  <div class="confirm-card">
    <div class="confirm-ic">🫙</div>
    <h2>Some ingredients are running low</h2>
    <p class="muted" id="lowStockList">Would you like to refill before opening today's café?</p>
    <div class="confirm-btns">
      <button class="btn btn-dark" id="lowGoStorage">Go to Storage</button>
      <button class="btn btn-light" id="lowStartAnyway">Start Day Anyway</button>
    </div>
  </div>
</div>

<!-- ==================== IN-GAME "RAN OUT" WARNING ==================== -->
<div class="ran-out-pop" id="ranOutPop">
  <span class="ro-msg" id="ranOutMsg">⚠ An ingredient has run out.</span>
  <div class="ro-btns">
    <button class="btn btn-sm btn-dark" id="ranOutStorage">Open Storage</button>
    <button class="btn btn-sm" id="ranOutContinue">Continue</button>
  </div>
</div>

<!-- ==================== STORY INTRODUCTION ==================== -->
<div class="flow-overlay story-overlay" id="storyOverlay">
  <div class="story-scene">
    <div class="story-char"><img id="storyCharImg" src="<?= asset('pic/anh1.png') ?>" alt="barista"></div>
    <div class="story-box">
      <div class="story-name" id="storyName">Your Story</div>
      <p class="story-text" id="storyText"></p>
      <div class="story-controls">
        <button class="btn btn-light" id="btnStorySkip">Skip ⏩</button>
        <div class="story-dots" id="storyDots"></div>
        <button class="btn btn-dark" id="btnStoryNext">Next ▸</button>
      </div>
    </div>
  </div>
</div>

<!-- ==================== INTERACTIVE TUTORIAL ==================== -->
<div class="flow-overlay tutorial-overlay" id="tutorialOverlay">
  <div class="tut-card">
    <div class="tut-top"><span class="tut-badge" id="tutBadge">Tutorial</span><span class="tut-step" id="tutStep">1 / 8</span></div>
    <div class="tut-ic" id="tutIcon">☕</div>
    <h2 id="tutTitle">Welcome!</h2>
    <p id="tutText"></p>
    <div class="tut-controls">
      <button class="btn btn-light btn-sm" id="btnTutSkip">Skip</button>
      <span class="grow"></span>
      <button class="btn btn-sm" id="btnTutPrev">◂ Back</button>
      <button class="btn btn-dark btn-sm" id="btnTutNext">Next ▸</button>
    </div>
  </div>
</div>

<!-- ==================== PREPARE YOUR CAFÉ ==================== -->
<div class="flow-overlay prepare-overlay" id="prepareOverlay">
  <div class="prepare-panel">
    <div class="prepare-head">
      <div>
        <h1>Prepare Your Café</h1>
        <p class="muted">Stock up on ingredients before you open the doors.</p>
      </div>
      <span class="chip chip-dark coin-chip"><?= icon('coin','svg') ?> <span data-coins>0</span></span>
    </div>
    <div class="prepare-grid" id="prepareGrid"></div>
    <div class="prepare-foot">
      <p class="small muted" id="prepareHint">Tip: click +10 / +20 / +50 to buy ingredients. Don't run out mid-day!</p>
      <button class="btn btn-dark" id="btnOpenShop">I'm Ready — Open Café ▸</button>
    </div>
  </div>
</div>

<!-- ==================== START DAY CONFIRMATION ==================== -->
<div class="flow-overlay confirm-overlay" id="startDayOverlay">
  <div class="confirm-card">
    <div class="confirm-ic">☕</div>
    <h2>Are you ready to open your coffee shop?</h2>
    <p class="muted">Customers are waiting outside for Day <span id="confirmDay">1</span>.</p>
    <div class="confirm-btns">
      <button class="btn btn-dark" id="btnStartDay">Start Day <span id="confirmDay2">1</span></button>
      <button class="btn btn-light" id="btnCancelStart">Cancel</button>
    </div>
  </div>
</div>

<!-- ==================== DAY SUMMARY ==================== -->
<div class="flow-overlay summary-overlay" id="daySummaryOverlay">
  <div class="summary-card">
    <div class="summary-ribbon">Day <span id="sumDay">1</span> Complete</div>
    <div class="summary-rating" id="sumRating">★★★★★</div>
    <div class="summary-grid" id="summaryGrid"></div>
    <div class="summary-btns">
      <button class="btn btn-dark" id="btnContinueDay">Continue to Day <span id="sumNextDay">2</span> ▸</button>
      <button class="btn btn-light" id="btnSummaryMenu">Return to Main Menu</button>
    </div>
  </div>
</div>
