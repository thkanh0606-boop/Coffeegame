<div class="page">
  <h1 class="mb-3">Achievements</h1>
  <div class="grid-2">
    <?php foreach ($achievements as $a):
      $unlocked = (int)$a['unlocked'] === 1;
      $pct = min(100, ($a['goal'] > 0 ? $a['progress'] / $a['goal'] * 100 : 0));
    ?>
      <div class="panel panel-pad ach <?= $unlocked ? '' : 'locked' ?>">
        <div class="medal"><?= icon($a['icon'],'svg') ?></div>
        <div class="grow">
          <div class="flex between items-center">
            <div class="b"><?= e($a['name']) ?></div>
            <span class="chip small"><?= icon('coin','svg') ?><?= (int)$a['reward_coins'] ?></span>
          </div>
          <div class="small muted mb-2"><?= e($a['description']) ?></div>
          <div class="progress-track"><span style="width:<?= $pct ?>%"></span></div>
          <div class="small muted mt-2">
            <?= $unlocked ? 'Unlocked ✓' : ((int)$a['progress'] . ' / ' . (int)$a['goal']) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
