<?php /** @var array $categories @var array $parents @var string $q @var int $page @var int $total @var int $perPage @var string $csrf @var \App\Core\Controller $_controller */ ?>
<div class="adm-head-row">
  <h1 class="adm-h1">Categories <span class="adm-count"><?= number_format($total) ?></span></h1>
</div>

<form class="adm-inline-add" method="post" action="<?= e($_controller->url('/admin/categories')) ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="text" name="name" placeholder="New category name" required aria-label="New category name">
  <select name="parent_id" aria-label="Parent category">
    <option value="">Top level</option>
    <?php foreach ($parents as $p): ?>
      <option value="<?= (int)$p['id'] ?>"><?= e($p['slug']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="number" name="position" value="0" title="Sort position" aria-label="Sort position" style="width:80px">
  <button class="btn btn-primary" type="submit">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    Add category
  </button>
</form>

<div class="adm-toolbar">
  <form class="adm-search" method="get" action="<?= e($_controller->url('/admin/categories')) ?>" role="search">
    <div class="field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search categories" aria-label="Search categories">
      <button class="go" type="submit">Search</button>
    </div>
  </form>
  <?php if ($q !== ''): ?><a class="btn btn-sm btn-ghost" href="<?= e($_controller->url('/admin/categories')) ?>">Clear</a><?php endif; ?>
</div>

<?php if (!$categories): ?>
  <div class="adm-table-wrap"><div class="adm-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/></svg>
    <h3><?= $q !== '' ? 'No matching categories' : 'No categories yet' ?></h3>
    <p><?= $q !== '' ? 'Try a different search or clear the filter.' : 'Add your first category using the form above.' ?></p>
  </div></div>
<?php else: ?>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr><th>Name</th><th>Parent</th><th>Slug</th><th class="right">Pos</th><th class="right">Parts</th><th class="right">Markup %</th><th>Active</th><th aria-label="Actions"></th></tr></thead>
      <tbody>
        <?php foreach ($categories as $c): $fid = 'cat' . $c['id']; ?>
          <tr>
            <td>
              <form id="<?= $fid ?>" method="post" action="<?= e($_controller->url('/admin/categories/' . $c['id'])) ?>"></form>
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>" form="<?= $fid ?>">
              <input type="text" name="name" value="<?= e($c['name']) ?>" form="<?= $fid ?>" aria-label="Category name">
            </td>
            <td>
              <select name="parent_id" form="<?= $fid ?>" aria-label="Parent" class="cat-parent"
                      data-self="<?= (int)$c['id'] ?>" data-current="<?= (int)($c['parent_id'] ?? 0) ?>">
                <option value="">Top level</option>
                <?php if (!empty($c['parent_id'])): ?>
                  <option value="<?= (int)$c['parent_id'] ?>" selected><?= e($c['parent_name'] ?? ('#' . $c['parent_id'])) ?></option>
                <?php endif; ?>
              </select>
            </td>
            <td><span class="adm-none mono"><?= e($c['slug']) ?></span></td>
            <td class="right"><input type="number" name="position" value="<?= (int)$c['position'] ?>" form="<?= $fid ?>" style="width:64px" aria-label="Position"></td>
            <td class="right"><?= number_format((int) $c['parts']) ?></td>
            <td class="right"><input type="number" step="0.01" min="0" name="commission_pct"
                 value="<?= $c['commission_pct'] !== null ? e((string) (float) $c['commission_pct']) : '' ?>"
                 form="<?= $fid ?>" placeholder="<?= e($defaultPct) ?>" style="width:72px"
                 aria-label="Commission percent for <?= e($c['name']) ?>"
                 title="Blank = use the default (<?= e($defaultPct) ?>%)"></td>
            <td><input type="checkbox" name="is_active" <?= $c['is_active'] ? 'checked' : '' ?> form="<?= $fid ?>" aria-label="Active"></td>
            <td class="right nowrap">
              <button class="link" type="submit" form="<?= $fid ?>">Save</button>
              <form method="post" action="<?= e($_controller->url('/admin/categories/' . $c['id'] . '/delete')) ?>"
                    data-confirm="Delete the category <?= e($c['name']) ?>? Parts in it become uncategorized." class="inline-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="link danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php $pagerBase = '/admin/categories'; $pagerParams = ['q' => $q]; include __DIR__ . '/../_pager.php'; ?>

  <?php /* Full parent list rendered ONCE; each row's dropdown is filled lazily on focus. */ ?>
  <template id="cat-parents"><?php foreach ($parents as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['slug']) ?></option><?php endforeach; ?></template>
  <script>
    (function () {
      var tpl = document.getElementById('cat-parents');
      if (!tpl) return;
      document.querySelectorAll('select.cat-parent').forEach(function (sel) {
        var loaded = false;
        sel.addEventListener('focus', function () {
          if (loaded) return; loaded = true;
          var self = sel.getAttribute('data-self'), cur = sel.getAttribute('data-current');
          sel.innerHTML = '<option value="">Top level</option>';
          Array.prototype.forEach.call(tpl.content.querySelectorAll('option'), function (o) {
            if (o.value === self) return;
            sel.appendChild(o.cloneNode(true));
          });
          sel.value = (cur && cur !== '0') ? cur : '';
        });
      });
    })();
  </script>
<?php endif; ?>
