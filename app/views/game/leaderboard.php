<?php
$metrics = [
  'highest_revenue'   => ['Highest Revenue', 'coin'],
  'customers_served'  => ['Customers Served', 'happy'],
  'highest_combo'     => ['Highest Combo', 'flame'],
  'highest_level'     => ['Highest Level', 'star'],
  'best_satisfaction' => ['Best Satisfaction', 'crown'],
];
$curLabel = $metrics[$metric][0] ?? 'Highest Revenue';
?>
<div class="page">
  <h1 class="mb-3">Leaderboard</h1>
  <div class="tabs">
    <?php foreach ($metrics as $key => [$label, $ic]): ?>
      <a class="tab <?= $metric === $key ? 'active' : '' ?>" href="<?= url('game/leaderboard&metric=' . $key) ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <?php if (empty($rows)): ?>
      <div class="panel-pad muted text-center">No scores yet — go serve some coffee!</div>
    <?php else: foreach ($rows as $i => $r):
      $me = (int)$r['user_id'] === (int)$meId;
      $val = $r[$metric];
      if ($metric === 'highest_revenue') $val = number_format($val);
      elseif ($metric === 'best_satisfaction') $val .= '%';
    ?>
      <div class="lb-row <?= $me ? 'me' : '' ?>">
        <div class="lb-rank <?= $i < 3 ? 'top' : '' ?>"><?= $i + 1 ?></div>
        <div class="ic" style="width:40px;height:46px"><?= icon($r['avatar'] ?: 'a1','svg') ?></div>
        <div class="grow b"><?= e($r['username']) ?> <?= $me ? '<span class="small muted">(you)</span>' : '' ?></div>
        <div class="chip chip-dark"><?= $curLabel === 'Highest Revenue' ? icon('coin','svg') : '' ?><?= e($val) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
