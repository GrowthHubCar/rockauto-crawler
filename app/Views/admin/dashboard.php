<?php
/** @var array $counts @var array $recentParts @var array $recentImports @var \App\Core\Controller $_controller */
$ico = function (string $k): string {
  $p = [
    'box'    => '<path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/>',
    'link'   => '<path d="M9 15l6-6"/><path d="M11 7l1-1a3.5 3.5 0 0 1 5 5l-1 1"/><path d="M13 17l-1 1a3.5 3.5 0 0 1-5-5l1-1"/>',
    'car'    => '<path d="M5 13l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13"/><path d="M4 13h16v4H4z"/><circle cx="7.5" cy="16.5" r="1"/><circle cx="16.5" cy="16.5" r="1"/>',
    'tag'    => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0l-7-7A2 2 0 0 1 3 12V5a2 2 0 0 1 2-2h7a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.6z"/><circle cx="7.5" cy="7.5" r="1.4"/>',
    'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/>',
    'cart'   => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L21 7H6"/>',
  ];
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$k] ?? '') . '</svg>';
};
?>
<h1 class="adm-h1">Dashboard</h1>
<p class="adm-sub">Catalog and operations at a glance for the Supreme Parts storefront.</p>

<div class="adm-cards">
  <?php
  $cards = [
    ['Parts', $counts['parts'], '/admin/parts', 'box'],
    ['Fitments', $counts['fitments'], null, 'link'],
    ['Vehicles', $counts['vehicles'], '/admin/catalog', 'car'],
    ['Makes', $counts['makes'], '/admin/catalog', 'car'],
    ['Models', $counts['models'], null, 'car'],
    ['Brands', $counts['brands'], '/admin/brands', 'tag'],
    ['Categories', $counts['categories'], '/admin/categories', 'folder'],
    ['Orders', $counts['orders'], null, 'cart'],
  ];
  foreach ($cards as [$label, $n, $link, $glyph]): ?>
    <?php if ($link): ?><a class="adm-card" href="<?= e($_controller->url($link)) ?>"><?php else: ?><div class="adm-card"><?php endif; ?>
      <span class="adm-card-l"><?= $ico($glyph) ?><?= e($label) ?></span>
      <span class="adm-card-n"><?= number_format((int) $n) ?></span>
    <?php if ($link): ?></a><?php else: ?></div><?php endif; ?>
  <?php endforeach; ?>
</div>

<?php
/* --- Revenue area chart geometry (30-day series) --- */
$W = 560; $H = 168; $padX = 6; $padTop = 16; $padBot = 6;
$rev = array_column($revenueSeries, 'rev');
$maxRev = max($rev); if ($maxRev <= 0) { $maxRev = 1; }
$n = count($revenueSeries);
$fx = fn($i) => round($padX + ($n > 1 ? $i / ($n - 1) : 0) * ($W - 2 * $padX), 1);
$fy = fn($v) => round($padTop + (1 - $v / $maxRev) * ($H - $padTop - $padBot), 1);
$pts = [];
foreach ($rev as $i => $v) { $pts[] = $fx($i) . ',' . $fy($v); }
$linePath = 'M' . implode(' L', $pts);
$areaPath = $linePath . ' L' . $fx($n - 1) . ',' . ($H - $padBot) . ' L' . $fx(0) . ',' . ($H - $padBot) . ' Z';
$hasOrders = $orders30 > 0;

/* --- Priced-coverage donut geometry --- */
$prc = (int) ($priced['priced'] ?? 0); $unp = (int) ($priced['unpriced'] ?? 0);
$prcTot = max(1, $prc + $unp); $prcPct = (int) round(100 * $prc / $prcTot);
$circ = 2 * M_PI * 52; $seg = round($circ * $prc / $prcTot, 1);

$maxCat = count($topCats) ? max(array_column($topCats, 'n')) : 1;
$fmtMoney = fn($v) => '$' . number_format((float) $v, 2);
?>
<div class="adm-chart-grid">
  <section class="adm-chart-card">
    <div class="adm-chart-card__head"><h2>Revenue</h2><span class="tag">Last 30 days</span></div>
    <div class="adm-chart__kpis">
      <div><div class="adm-kpi__label">Revenue (30d)</div><div class="adm-kpi__value accent"><?= $fmtMoney($revenue30) ?></div></div>
      <div><div class="adm-kpi__label">Orders (30d)</div><div class="adm-kpi__value"><?= number_format((int) $orders30) ?></div></div>
      <div><div class="adm-kpi__label">Lifetime revenue</div><div class="adm-kpi__value"><?= $fmtMoney($lifetime['total'] ?? 0) ?></div></div>
    </div>
    <div class="adm-chart__plot">
      <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Daily revenue over the last 30 days">
        <defs><linearGradient id="revfill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#ea2804" stop-opacity=".16"/><stop offset="1" stop-color="#ea2804" stop-opacity="0"/>
        </linearGradient></defs>
        <path d="<?= $areaPath ?>" fill="url(#revfill)"/>
        <path d="<?= $linePath ?>" fill="none" stroke="#ea2804" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
      </svg>
      <?php if (!$hasOrders): ?>
        <div class="adm-chart__empty"><strong>No orders yet</strong><span>Revenue will chart here once orders come in.</span></div>
      <?php endif; ?>
    </div>
    <div class="adm-chart__axis"><span><?= e(date('M j', strtotime($revenueSeries[0]['date']))) ?></span><span>Today</span></div>
  </section>

  <section class="adm-chart-card">
    <div class="adm-chart-card__head"><h2>Priced coverage</h2><span class="tag"><?= number_format($prcTot) ?> parts</span></div>
    <div class="adm-donut" style="margin-top:18px">
      <div style="position:relative">
        <svg viewBox="0 0 120 120" role="img" aria-label="<?= $prcPct ?>% of parts are priced">
          <circle cx="60" cy="60" r="52" fill="none" stroke="#f3f0e8" stroke-width="14"/>
          <circle cx="60" cy="60" r="52" fill="none" stroke="#ea2804" stroke-width="14" stroke-linecap="round"
                  stroke-dasharray="<?= $seg ?> <?= round($circ - $seg, 1) ?>"/>
        </svg>
        <div class="adm-donut__center" style="position:absolute; inset:0; display:grid; place-items:center; text-align:center">
          <div><div style="font-size:23px; font-weight:600; color:var(--ink)"><?= $prcPct ?>%</div><div style="font-size:10px; color:var(--mute); letter-spacing:.04em">PRICED</div></div>
        </div>
      </div>
      <div class="adm-donut__legend">
        <div class="adm-donut__row"><span class="adm-donut__dot" style="background:#ea2804"></span>Priced <strong><?= number_format($prc) ?></strong></div>
        <div class="adm-donut__row"><span class="adm-donut__dot" style="background:#e8e2d5"></span>Unpriced <strong><?= number_format($unp) ?></strong></div>
      </div>
    </div>
  </section>
</div>

<div class="adm-chart-grid">
  <section class="adm-chart-card">
    <div class="adm-chart-card__head"><h2>Top categories by parts</h2><span class="tag">Catalog</span></div>
    <?php if ($topCats): ?>
      <div class="adm-bars">
        <?php foreach ($topCats as $c): $w = max(2, round(100 * $c['n'] / $maxCat)); ?>
          <div class="adm-bar__row">
            <span class="adm-bar__label"><?= e($c['name']) ?></span>
            <span class="adm-bar__val"><?= number_format((int) $c['n']) ?></span>
            <div class="adm-bar__track"><div class="adm-bar__fill" style="width:<?= $w ?>%"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="adm-none" style="margin:18px 0 0">No categorized parts yet.</p>
    <?php endif; ?>
  </section>

  <section class="adm-panel" style="margin:0">
    <div class="adm-panel-head"><h2>Recent imports</h2><a href="<?= e($_controller->url('/admin/imports')) ?>">Imports</a></div>
    <div class="adm-table-wrap" style="border:0;border-radius:0;margin:0">
      <table class="adm-table">
        <thead><tr><th>Type</th><th class="right">OK</th><th class="right">Failed</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentImports as $il): ?>
            <tr>
              <td><?= e($il['type']) ?><span class="sub"><?= e($il['filename'] ?? '') ?></span></td>
              <td class="right"><?= number_format((int) $il['rows_ok']) ?></td>
              <td class="right"><?= number_format((int) $il['rows_failed']) ?></td>
              <td><span class="badge badge-<?= e($il['status']) ?>"><?= e($il['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentImports): ?><tr><td colspan="4"><span class="adm-none">No imports logged yet.</span></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<section class="adm-panel">
  <div class="adm-panel-head"><h2>Recently updated parts</h2><a href="<?= e($_controller->url('/admin/parts')) ?>">All parts</a></div>
  <div class="adm-table-wrap" style="border:0;border-radius:0;margin:0">
    <table class="adm-table">
      <thead><tr><th>Part</th><th>Brand</th><th class="right">Price</th></tr></thead>
      <tbody>
        <?php foreach ($recentParts as $p): ?>
          <tr>
            <td><a href="<?= e($_controller->url('/admin/parts')) ?>"><?= e($p['name']) ?></a><span class="sub"><?= e($p['sku']) ?></span></td>
            <td><?= $p['brand'] ? e($p['brand']) : '<span class="adm-none">No brand</span>' ?></td>
            <td class="right"><?= money($p['price']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentParts): ?><tr><td colspan="3"><span class="adm-none">No parts yet.</span></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
