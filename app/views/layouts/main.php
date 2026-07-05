<?php /** Main game shell layout — nav + HUD + content. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? APP_NAME) ?></title>
  <link rel="icon" href="<?= asset('pic/coffeemascot.jpg') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('assets/css/game.css') ?>">
  <script>
    // Global config for JS modules (no inline styling — config only).
    window.BREW = {
      base: "<?= BASE_URL ?>",
      api:  "<?= BASE_URL ?>/index.php?url=api/",
      user: <?= json_encode(['id' => currentUserId(), 'name' => $_SESSION['username'] ?? '']) ?>
    };
  </script>
</head>
<body class="game-body<?= ($active ?? '') === 'play' ? ' play-fullscreen' : '' ?>">
  <?php if (($active ?? '') !== 'play'):
    /* Secondary pages: no top navbar — just the PLT logo + the shared minimalist sidebar. */
    $navItems = [
      ['box',    'Ingredient Storage', url('game') . '&open=storage'],
      ['chart',  'Revenue',            url('game/stats')],
      ['trophy', 'Leaderboard',        url('game/leaderboard')],
      ['medal',  'Achievements',       url('game/achievements')],
      ['scan',   'AI Scan',            url('aiscan')],
      ['book',   'Replay Tutorial',    url('game') . '&open=tutorial'],
      ['gear',   'Settings',           url('game') . '&open=settings'],
    ];
  ?>
  <header class="page-hud">
    <button class="hamburger" id="navHamburger" aria-label="Open menu"><span></span><span></span><span></span></button>
    <a class="page-logo" href="<?= url('game') ?>" aria-label="PLT"><span class="logo-badge"><img src="<?= asset('pic/Cardmoi_PLT_Trang.png') ?>" alt="PLT"></span></a>
  </header>
  <div class="sidebar-backdrop" id="navBackdrop"></div>
  <aside class="game-sidebar" id="navSidebar" aria-hidden="true">
    <div class="sb-head">
      <span class="sb-logo"><span class="logo-badge"><img src="<?= asset('pic/Cardmoi_PLT_Trang.png') ?>" alt="PLT"></span> Menu</span>
      <button class="sb-close" id="navClose" aria-label="Close">✕</button>
    </div>
    <nav class="sb-nav">
      <?php foreach ($navItems as [$ic, $label, $href]): ?>
        <a class="sb-item" href="<?= $href ?>"><span class="sb-ic"><?= icon($ic,'svg') ?></span> <?= $label ?></a>
      <?php endforeach; ?>
      <div class="sb-spacer"></div>
      <a class="sb-item danger" href="<?= url('auth/logout') ?>"><span class="sb-ic"><?= icon('power','svg') ?></span> Logout</a>
    </nav>
  </aside>
  <script>
  (function () {
    var sb = document.getElementById('navSidebar'), bd = document.getElementById('navBackdrop');
    var open = function () { sb.classList.add('show'); bd.classList.add('show'); try { localStorage.setItem('plt_nav_open','1'); } catch(e){} };
    var close = function () { sb.classList.remove('show'); bd.classList.remove('show'); try { localStorage.setItem('plt_nav_open','0'); } catch(e){} };
    document.getElementById('navHamburger').addEventListener('click', open);
    document.getElementById('navClose').addEventListener('click', close);
    bd.addEventListener('click', close);
    // Keep the sidebar visible across navigation (do not auto-close after opening a page).
    try { if (localStorage.getItem('plt_nav_open') === '1') { sb.classList.add('show'); bd.classList.add('show'); } } catch(e){}
  })();
  </script>
  <?php endif; ?>

  <div class="toast-wrap" id="toastWrap"></div>
  <div class="fx-layer" id="fxLayer"></div>

  <?= $content ?>

  <script src="<?= asset('assets/js/audio.js') ?>"></script>
  <script src="<?= asset('assets/js/api.js') ?>"></script>
  <script src="<?= asset('assets/js/ui.js') ?>"></script>
  <?php if (!empty($pageScript)): ?>
    <script src="<?= asset('assets/js/' . $pageScript . '.js') ?>"></script>
  <?php endif; ?>
</body>
</html>
