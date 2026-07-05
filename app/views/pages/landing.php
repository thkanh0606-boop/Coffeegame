<?php /** Opening Screen — the entry point of the game flow. */ ?>
<div class="opening-screen">
  <div class="opening-overlay"></div>

  <div class="opening-inner">
    <div class="opening-logo">
      <img src="<?= asset('pic/Cardmoi_PLT_Trang.png') ?>" alt="PLT">
    </div>

    <h1 class="opening-title"><?= APP_NAME ?></h1>
    <p class="opening-tagline">
      A cozy coffee-shop time-management game.<br>
      Serve customers, master recipes, and grow your café.
    </p>

    <div class="opening-actions">
      <?php if (!empty($loggedIn)): ?>
        <a class="btn btn-play" href="<?= url('game') ?>">▶ Continue Playing</a>
        <p class="opening-welcome">Welcome back, <b><?= e($username) ?></b>!</p>
        <a class="btn btn-light" href="<?= url('auth/logout') ?>">Logout</a>
      <?php else: ?>
        <a class="btn btn-play" href="<?= url('game') ?>">▶ Play</a>
        <a class="btn btn-light" href="<?= url('auth/login') ?>">Login</a>
        <a class="btn btn-light" href="<?= url('auth/register') ?>">Sign Up</a>
      <?php endif; ?>
    </div>

    <p class="opening-foot small">Made with ☕ by PLT Solutions</p>
  </div>
</div>
