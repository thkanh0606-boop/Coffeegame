<?php $pageScript = 'panels';
$byCat = ['decor' => [], 'furniture' => [], 'exterior' => []];
foreach ($decorations as $d) { $byCat[$d['category']][] = $d; }
$catNames = ['decor' => 'Decor', 'furniture' => 'Furniture', 'exterior' => 'Exterior'];
?>
<div class="page">
  <div class="flex between items-center mb-3">
    <div>
      <h1>Decorate &amp; Shop</h1>
      <p class="muted small">Decorations raise customer satisfaction.</p>
    </div>
    <div class="flex gap-2">
      <span class="chip"><?= icon('happy','svg') ?><span data-sat><?= (int)$shop['satisfaction'] ?></span>%</span>
      <span class="chip chip-dark"><?= icon('coin','svg') ?><span data-coins><?= (int)$progress['coins'] ?></span></span>
    </div>
  </div>

  <div class="tabs" id="shopTabs">
    <?php $first = true; foreach ($catNames as $cat => $label): ?>
      <div class="tab <?= $first ? 'active' : '' ?>" data-tab="<?= $cat ?>"><?= $label ?></div>
    <?php $first = false; endforeach; ?>
  </div>

  <?php $first = true; foreach ($catNames as $cat => $label): ?>
    <div class="tab-panel <?= $first ? '' : 'hidden' ?>" data-panel="<?= $cat ?>">
      <div class="grid-3">
        <?php foreach ($byCat[$cat] as $d):
          $owned = (int)$d['owned'] === 1;
        ?>
          <div class="panel panel-pad text-center deco-card<?= $owned ? ' owned' : '' ?>" data-decoration="<?= e($d['code']) ?>">
            <div class="deco-ic"><?= icon($d['icon'],'svg') ?></div>
            <div class="b deco-name"><?= e($d['name']) ?></div>
            <div class="deco-bonus">+<?= (int)$d['satisfaction_bonus'] ?> satisfaction</div>
            <?php if ($owned): ?>
              <span class="chip deco-owned">Owned ✓</span>
            <?php else: ?>
              <button class="btn btn-dark btn-sm btn-block deco-buy" data-buy-decoration="<?= e($d['code']) ?>">
                <span class="coin-mini"><?= icon('coin','svg') ?></span>
                <span class="deco-price"><?= (int)$d['cost'] ?></span>
              </button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php $first = false; endforeach; ?>
</div>
