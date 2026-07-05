<?php $pageScript = 'panels';
$byCat = ['equipment' => [], 'interior' => [], 'staff' => [], 'recipe' => []];
foreach ($upgrades as $u) { $byCat[$u['category']][] = $u; }
$catNames = ['equipment' => 'Equipment', 'interior' => 'Interior', 'staff' => 'Staff', 'recipe' => 'Recipe'];
?>
<div class="page">
  <div class="flex between items-center mb-3">
    <h1>Upgrades</h1>
    <span class="chip chip-dark"><?= icon('coin','svg') ?><span data-coins><?= (int)$progress['coins'] ?></span></span>
  </div>

  <div class="tabs" id="upgradeTabs">
    <?php $first = true; foreach ($catNames as $cat => $label): if (empty($byCat[$cat])) continue; ?>
      <div class="tab <?= $first ? 'active' : '' ?>" data-tab="<?= $cat ?>"><?= $label ?></div>
    <?php $first = false; endforeach; ?>
  </div>

  <?php $first = true; foreach ($catNames as $cat => $label): if (empty($byCat[$cat])) continue; ?>
    <div class="tab-panel <?= $first ? '' : 'hidden' ?>" data-panel="<?= $cat ?>">
      <?php foreach ($byCat[$cat] as $u):
        $lvl = (int)$u['level'];
        $maxed = $lvl >= (int)$u['max_level'];
        $cost = (int) round($u['base_cost'] * pow((float)$u['cost_growth'], $lvl));
        $stars = str_repeat('★', $lvl) . str_repeat('☆', max(0, (int)$u['max_level'] - $lvl));
      ?>
        <div class="row-item" data-upgrade="<?= e($u['code']) ?>">
          <div class="ic"><?= icon($u['icon'],'svg') ?></div>
          <div class="grow">
            <div class="b"><?= e($u['name']) ?> <span class="lv">Lv. <?= $lvl ?>/<?= (int)$u['max_level'] ?></span></div>
            <div class="stars"><?= $stars ?></div>
            <div class="small muted"><?= e($u['description']) ?></div>
          </div>
          <div class="text-center">
            <?php if ($maxed): ?>
              <span class="chip">MAX</span>
            <?php else: ?>
              <div class="chip mb-2"><?= icon('coin','svg') ?><span data-cost><?= $cost ?></span></div>
              <button class="btn btn-dark btn-sm" data-buy-upgrade="<?= e($u['code']) ?>">Upgrade</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php $first = false; endforeach; ?>
</div>
