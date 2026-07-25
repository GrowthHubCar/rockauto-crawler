<?php /** @var array $logs @var string $csrf @var bool $canRun @var \App\Core\Controller $_controller */ ?>
<h1 class="adm-h1">Imports</h1>
<p class="adm-sub">Load vehicle and parts data from official feeds. Bounded runs return quickly; use the CLI for full catalogs.</p>

<?php if (!$canRun): ?>
  <div class="flash flash-warn" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.5"/><path d="M12 16h.01"/></svg>
    <span><code>shell_exec</code> is disabled in PHP. Run importers from the CLI using the commands below.</span>
  </div>
<?php endif; ?>

<div class="adm-two">
  <section class="adm-panel">
    <div class="adm-panel-head"><h2>Vehicle data (NHTSA vPIC)</h2></div>
    <p class="adm-none" style="padding:14px 18px 0;margin:0">Pulls real makes, models, and years. The bounded run below returns quickly.</p>
    <form method="post" action="<?= e($_controller->url('/admin/imports/vpic')) ?>" class="adm-run-form" style="padding:0 18px">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label>Makes <input type="text" name="makes" value="Honda,Toyota" placeholder="Honda,Toyota"></label>
      <label>From <input type="number" name="from" value="2020" style="width:88px"></label>
      <label>To <input type="number" name="to" value="2021" style="width:88px"></label>
      <button class="btn btn-primary" type="submit" <?= $canRun ? '' : 'disabled' ?>>Run vPIC import</button>
    </form>
    <div class="cli"><code>python bin/import_vpic.py --makes Honda,Toyota --from 2015 --to 2025</code></div>
  </section>

  <section class="adm-panel">
    <div class="adm-panel-head"><h2>Parts (ACES / PIES feed)</h2></div>
    <p class="adm-none" style="padding:14px 18px 0;margin:0">Loads the sample ACES+PIES feed. Point at your licensed feed files via the CLI.</p>
    <form method="post" action="<?= e($_controller->url('/admin/imports/acespies')) ?>" class="adm-run-form" style="padding:0 18px">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="btn btn-primary" type="submit" <?= $canRun ? '' : 'disabled' ?>>Run sample ACES/PIES import</button>
    </form>
    <div class="cli"><code>python bin/ingest_acespies.py &lt;aces.xml&gt; &lt;pies.xml&gt; scraper/reference</code></div>
  </section>
</div>

<section class="adm-panel">
  <div class="adm-panel-head"><h2>Import history</h2><span class="adm-count"><?= number_format(count($logs)) ?></span></div>
  <div class="adm-table-wrap" style="border:0;border-radius:0;margin:0">
    <table class="adm-table">
      <thead><tr><th>When</th><th>Type</th><th>File</th><th class="right">Total</th><th class="right">OK</th><th class="right">Failed</th><th>Status</th><th>By</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $il): ?>
          <tr>
            <td class="nowrap mono"><?= e($il['created_at']) ?></td>
            <td><?= e($il['type']) ?></td>
            <td><?= $il['filename'] ? e($il['filename']) : '<span class="adm-none">No file</span>' ?></td>
            <td class="right"><?= number_format((int) $il['rows_total']) ?></td>
            <td class="right"><?= number_format((int) $il['rows_ok']) ?></td>
            <td class="right"><?= number_format((int) $il['rows_failed']) ?></td>
            <td><span class="badge badge-<?= e($il['status']) ?>"><?= e($il['status']) ?></span></td>
            <td><?= $il['admin_name'] ? e($il['admin_name']) : '<span class="adm-none">System</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
          <tr><td colspan="8" class="adm-empty-cell"><div class="adm-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 3v5h5"/></svg>
            <h3>No imports logged yet</h3><p>Run one of the importers above to see history here.</p>
          </div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
