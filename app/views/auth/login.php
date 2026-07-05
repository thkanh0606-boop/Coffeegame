<div class="auth-wrap">
  <div class="auth-card panel panel-pad">
    <div class="logo-hero"><img src="<?= asset('pic/Cardmoi_PLT_Trang.png') ?>" alt="PLT Solutions"></div>
    <h1 class="text-center"><?= APP_NAME ?></h1>
    <p class="text-center muted mb-3">Sign in and open your cafe.</p>

    <?php if (!empty($error)): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" action="<?= url('auth/login') ?>">
      <div class="field">
        <label>Username or Email</label>
        <input type="text" name="login" required autofocus>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button class="btn btn-dark btn-block" type="submit">Start Serving</button>
    </form>
    <p class="text-center mt-3 small">
      New barista? <a href="<?= url('auth/register') ?>"><b>Create an account</b></a>
    </p>
  </div>
</div>
