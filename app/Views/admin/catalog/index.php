<?php /** @var array $makes @var array $vehicles @var string $q @var \App\Core\Controller $_controller */ ?>
<h1 class="adm-h1">Vehicle Catalog</h1>
<p class="adm-sub">Vehicles are populated by the importers (vPIC / ACES). Manage them via <a class="link-orange" href="<?= e($_controller->url('/admin/imports')) ?>">Imports</a>.</p>

<div class="adm-two">
  <section class="adm-panel">
    <div class="adm-panel-head"><h2>Makes</h2><span class="adm-count"><?= number_format(count($makes)) ?></span></div>
    <div class="adm-table-wrap" style="border:0;border-radius:0;margin:0;max-height:520px;overflow-y:auto">
      <table class="adm-table">
        <thead><tr><th>Make</th><th class="right">Models</th><th class="right">Vehicles</th></tr></thead>
        <tbody>
          <?php foreach ($makes as $m): ?>
            <tr>
              <td><a class="link-orange inline" href="<?= e($_controller->url('/make/' . $m['slug'])) ?>" target="_blank" rel="noopener"><?= e($m['name']) ?></a></td>
              <td class="right"><?= number_format((int) $m['models']) ?></td>
              <td class="right"><?= number_format((int) $m['vehicles']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$makes): ?><tr><td colspan="3"><span class="adm-none">No makes yet. Run an import.</span></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="adm-panel">
    <div class="adm-panel-head"><h2>Find a vehicle</h2></div>
    <div style="padding:14px 18px 4px">
      <form class="adm-search" method="get" action="<?= e($_controller->url('/admin/catalog')) ?>" role="search" style="max-width:none">
        <div class="field">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input type="search" name="q" value="<?= e($q) ?>" placeholder="Make, model, or slug" aria-label="Search vehicles">
          <button class="go" type="submit">Search</button>
        </div>
      </form>
    </div>
    <?php if ($q !== ''): ?>
      <div class="adm-table-wrap" style="border:0;border-radius:0;margin:0">
        <table class="adm-table">
          <thead><tr><th>Year</th><th>Make</th><th>Model</th><th>Engine</th><th aria-label="Actions"></th></tr></thead>
          <tbody>
            <?php foreach ($vehicles as $v): ?>
              <tr>
                <td class="mono"><?= e((string)$v['year']) ?></td>
                <td><?= e($v['make']) ?></td>
                <td><?= e($v['model']) ?><?= $v['trim'] ? ' <span class="adm-none">'.e($v['trim']).'</span>' : '' ?></td>
                <td><?= $v['engine'] ? e($v['engine']) : '<span class="adm-none">Standard</span>' ?></td>
                <td class="right"><a class="link" href="<?= e($_controller->url('/vehicle/' . $v['slug'])) ?>" target="_blank" rel="noopener">View</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$vehicles): ?><tr><td colspan="5"><span class="adm-none">No matches for &ldquo;<?= e($q) ?>&rdquo;.</span></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="adm-empty" style="padding:40px 24px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <p>Search by make, model, or slug to find a vehicle.</p>
      </div>
    <?php endif; ?>
  </section>
</div>
