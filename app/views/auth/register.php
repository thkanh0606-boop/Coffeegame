<div class="auth-wrap">
  <div class="auth-card panel panel-pad">
    <div class="logo-hero"><img src="<?= asset('pic/Cardmoi_PLT_Trang.png') ?>" alt="PLT Solutions"></div>
    <h1 class="text-center">Create Account</h1>
    <p class="text-center muted mb-3">Your cafe starts with 120 coins.</p>

    <?php if (!empty($error)): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" action="<?= url('auth/register') ?>">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" minlength="3" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" minlength="6" required>
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <input type="password" name="confirm" minlength="6" required>
      </div>
      <button class="btn btn-dark btn-block" type="submit">Open My Cafe</button>
    </form>
    <p class="text-center mt-3 small">
      Already have one? <a href="<?= url('auth/login') ?>"><b>Sign in</b></a>
    </p>
  </div>
</div>
