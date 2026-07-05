<?php
/**
 * Statistics dashboard — daily revenue breakdown + summary + chart.
 * Data source: $report (revenueReport) + $shop. All values from the DB.
 */
$rows = $report['daily_rows'];

// Build per-day series (ascending by day for the chart).
$days = [];
foreach ($rows as $r) {
    $rev   = (int)$r['gross'] + (int)$r['tips'];
    $exp   = (int)$r['expenses'];
    $days[] = [
        'day'     => (int)$r['day'],
        'revenue' => $rev,
        'orders'  => (int)$r['orders_completed'],
        'profit'  => $rev - $exp,
        'expenses'=> $exp,
        'tips'    => (int)$r['tips'],
    ];
}

// ---- Chart geometry (area + line of daily revenue) ----
$points = array_map(fn($d) => ['d' => $d['day'], 'v' => $d['revenue']], $days);
if (empty($points)) { $points = [['d' => 1, 'v' => 0]]; }
$max = 1;
foreach ($points as $p) { $max = max($max, $p['v']); }
$W = 680; $H = 240; $pad = 34;
$n = count($points);
$stepX = $n > 1 ? ($W - 2 * $pad) / ($n - 1) : 0;
$coords = [];
foreach ($points as $i => $p) {
    $x = $pad + ($n > 1 ? $i * $stepX : ($W - 2 * $pad) / 2);
    $y = $H - $pad - ($p['v'] / $max) * ($H - 2 * $pad);
    $coords[] = [$x, $y, $p];
}
$linePts = implode(' ', array_map(fn($c) => round($c[0], 1) . ',' . round($c[1], 1), $coords));
// closed polygon for the soft area fill
$areaPts = $coords[0][0] . ',' . ($H - $pad) . ' ' . $linePts . ' ' . end($coords)[0] . ',' . ($H - $pad);
reset($coords);
?>
<div class="page stats-page">
  <div class="flex between items-center mb-3 wrap gap-2">
    <div>
      <h1>Revenue &amp; Statistics</h1>
      <p class="muted small">Your café's performance, updated automatically from every order.</p>
    </div>
    <div class="flex gap-2">
      <span class="chip"><?= icon('happy','svg') ?><span data-sat><?= (int)$shop['satisfaction'] ?></span>%</span>
    </div>
  </div>

  <!-- ============ Summary tiles ============ -->
  <div class="stat-summary mb-3">
    <div class="panel stat-tile accent">
      <div class="v"><span class="coin-mini"><?= icon('coin','svg') ?></span> <?= number_format($report['gross'] + $report['tips']) ?></div>
      <div class="k">Total Revenue</div>
    </div>
    <div class="panel stat-tile">
      <div class="v"><?= number_format($report['profit']) ?></div>
      <div class="k">Net Profit</div>
    </div>
    <div class="panel stat-tile">
      <div class="v"><?= number_format($report['completed']) ?></div>
      <div class="k">Orders Served</div>
    </div>
    <div class="panel stat-tile">
      <div class="v"><?= number_format($report['tips']) ?></div>
      <div class="k">Tips Earned</div>
    </div>
    <div class="panel stat-tile">
      <div class="v"><?= number_format($report['expenses']) ?></div>
      <div class="k">Expenses</div>
    </div>
    <div class="panel stat-tile">
      <div class="v"><?= $report['avg_order'] ?></div>
      <div class="k">Avg Order</div>
    </div>
    <div class="panel stat-tile">
      <div class="v" style="font-size:20px"><?= e($report['popular_drink']) ?></div>
      <div class="k">Most Popular</div>
    </div>
    <div class="panel stat-tile">
      <div class="v"><?= number_format($report['cancelled']) ?></div>
      <div class="k">Cancelled</div>
    </div>
  </div>

  <!-- ============ Revenue chart ============ -->
  <div class="panel panel-pad chart-panel mb-3">
    <span class="label-tab panel-title">Revenue by Day</span>
    <div class="mt-4" style="overflow-x:auto">
      <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" style="max-width:760px;display:block" fill="none" role="img" aria-label="Daily revenue chart">
        <!-- horizontal gridlines -->
        <?php for ($g = 0; $g <= 4; $g++): $gy = $pad + ($g / 4) * ($H - 2 * $pad); ?>
          <line x1="<?= $pad ?>" y1="<?= round($gy,1) ?>" x2="<?= $W - $pad ?>" y2="<?= round($gy,1) ?>" stroke="#e6e6e6" stroke-width="1"/>
          <text x="<?= $pad - 8 ?>" y="<?= round($gy,1) + 3 ?>" font-size="10" text-anchor="end" fill="#aaa"><?= round($max * (1 - $g / 4)) ?></text>
        <?php endfor; ?>
        <!-- area fill -->
        <polygon points="<?= $areaPts ?>" fill="rgba(17,17,17,.07)"/>
        <!-- revenue line -->
        <polyline points="<?= $linePts ?>" stroke="#111" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
        <!-- points + day labels -->
        <?php foreach ($coords as $c): ?>
          <circle cx="<?= round($c[0],1) ?>" cy="<?= round($c[1],1) ?>" r="4" fill="#fff" stroke="#111" stroke-width="2"/>
          <text x="<?= round($c[0],1) ?>" y="<?= $H - $pad + 18 ?>" font-size="10" text-anchor="middle" fill="#666">Day <?= $c[2]['d'] ?></text>
        <?php endforeach; ?>
      </svg>
    </div>
    <?php if (empty($days)): ?>
      <p class="muted text-center mt-3">No revenue recorded yet — serve some customers to see your daily stats here.</p>
    <?php endif; ?>
  </div>

  <!-- ============ Daily breakdown cards (newest first) ============ -->
  <div class="flex between items-center mb-3">
    <h2 style="font-size:18px">Daily Breakdown</h2>
    <span class="small muted"><?= count($days) ?> day<?= count($days) === 1 ? '' : 's' ?> · newest first</span>
  </div>

  <?php if (empty($days)): ?>
    <div class="panel panel-pad text-center muted">Your daily revenue cards will appear here after your first day of service.</div>
  <?php else: ?>
    <div class="day-grid">
      <?php foreach (array_reverse($days) as $d): ?>
        <div class="panel day-card">
          <div class="day-card-head">
            <span class="day-badge">Day <?= $d['day'] ?></span>
            <span class="day-rev"><span class="coin-mini"><?= icon('coin','svg') ?></span> <?= number_format($d['revenue']) ?></span>
          </div>
          <div class="day-metrics">
            <div class="dm"><div class="dm-v"><?= number_format($d['orders']) ?></div><div class="dm-k">Orders</div></div>
            <div class="dm"><div class="dm-v"><?= number_format($d['profit']) ?></div><div class="dm-k">Profit</div></div>
            <div class="dm"><div class="dm-v"><?= number_format($d['expenses']) ?></div><div class="dm-k">Expenses</div></div>
            <div class="dm"><div class="dm-v"><?= number_format($d['tips']) ?></div><div class="dm-k">Tips</div></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
