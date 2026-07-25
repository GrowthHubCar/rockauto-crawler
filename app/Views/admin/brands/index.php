<?php /** @var array $brands @var string $q @var int $page @var int $total @var int $perPage @var string $csrf @var \App\Core\Controller $_controller */ ?>
<div class="adm-head-row">
  <h1 class="adm-h1">Brands <span class="adm-count"><?= number_format($total) ?></span></h1>
</div>

<form class="adm-inline-add" method="post" action="<?= e($_controller->url('/admin/brands')) ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="text" name="name" placeholder="New brand name" required aria-label="New brand name">
  <button class="btn btn-primary" type="submit">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    Add brand
  </button>
</form>

<div class="adm-toolbar">
  <form class="adm-search" method="get" action="<?= e($_controller->url('/admin/brands')) ?>" role="search">
    <div class="field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search brands" aria-label="Search brands">
      <button class="go" type="submit">Search</button>
    </div>
  </form>
  <?php if ($q !== ''): ?><a class="btn btn-sm btn-ghost" href="<?= e($_controller->url('/admin/brands')) ?>">Clear</a><?php endif; ?>
</div>

<?php if (!$brands): ?>
  <div class="adm-table-wrap"><div class="adm-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0l-7-7A2 2 0 0 1 3 12V5a2 2 0 0 1 2-2h7a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.6z"/><circle cx="7.5" cy="7.5" r="1.4"/></svg>
    <h3><?= $q !== '' ? 'No matching brands' : 'No brands yet' ?></h3>
    <p><?= $q !== '' ? 'Try a different search or clear the filter.' : 'Add your first brand using the field above.' ?></p>
  </div></div>
<?php else: ?>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr><th>Name</th><th>Slug</th><th class="right">Parts</th><th>Active</th><th aria-label="Actions"></th></tr></thead>
      <tbody>
        <?php foreach ($brands as $b): $fid = 'brand' . $b['id']; ?>
          <tr>
            <td>
              <form id="<?= $fid ?>" method="post" action="<?= e($_controller->url('/admin/brands/' . $b['id'])) ?>"></form>
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>" form="<?= $fid ?>">
              <input type="text" name="name" value="<?= e($b['name']) ?>" form="<?= $fid ?>" aria-label="Brand name">
            </td>
            <td><span class="adm-none mono"><?= e($b['slug']) ?></span></td>
            <td class="right"><?= number_format((int) $b['parts']) ?></td>
            <td><input type="checkbox" name="is_active" <?= $b['is_active'] ? 'checked' : '' ?> form="<?= $fid ?>" aria-label="Active"></td>
            <td class="right nowrap">
              <button class="link" type="submit" form="<?= $fid ?>">Save</button>
              <form method="post" action="<?= e($_controller->url('/admin/brands/' . $b['id'] . '/delete')) ?>"
                    data-confirm="Delete the brand <?= e($b['name']) ?>? Parts keep their data but lose this brand." class="inline-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="link danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php $pagerBase = '/admin/brands'; $pagerParams = ['q' => $q]; include __DIR__ . '/../_pager.php'; ?>
<?php endif; ?>
