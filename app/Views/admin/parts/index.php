<?php
/** @var array $parts @var string $q @var int $page @var int $total @var int $perPage
 *  @var string $sort @var string $dir @var \App\Core\Controller $_controller */

/* Sortable column header: link that toggles direction, marks aria-sort. */
$sortHead = function (string $label, string $key, bool $right = false) use ($_controller, $q, $sort, $dir) {
    $isActive = $sort === $key;
    $nextDir  = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
    $params   = array_filter(['q' => $q, 'sort' => $key, 'dir' => $nextDir], fn ($v) => $v !== '');
    $href     = e($_controller->url('/admin/parts?' . http_build_query($params)));
    $aria     = $isActive ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '';
    $caret    = $isActive
        ? ($dir === 'asc' ? '<path d="M6 15l6-6 6 6"/>' : '<path d="M6 9l6 6 6-6"/>')
        : '<path d="M8 9l4-4 4 4"/><path d="M8 15l4 4 4-4"/>';
    return '<th class="sortable' . ($right ? ' right' : '') . '"' . $aria . '><a href="' . $href . '">' . e($label)
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $caret . '</svg></a></th>';
};
?>
<div class="adm-head-row">
  <div>
    <h1 class="adm-h1">Parts <span class="adm-count"><?= number_format($total) ?></span></h1>
  </div>
  <a class="btn btn-primary" href="<?= e($_controller->url('/admin/parts/create')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    New part
  </a>
</div>

<div class="adm-toolbar">
  <form class="adm-search" method="get" action="<?= e($_controller->url('/admin/parts')) ?>" role="search">
    <div class="field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, part number, or SKU" aria-label="Search parts">
      <?php if ($sort !== 'updated'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><input type="hidden" name="dir" value="<?= e($dir) ?>"><?php endif; ?>
      <button class="go" type="submit">Search</button>
    </div>
  </form>
  <?php if ($q !== ''): ?><a class="btn btn-sm btn-ghost" href="<?= e($_controller->url('/admin/parts')) ?>">Clear</a><?php endif; ?>
</div>

<?php if (!$parts): ?>
  <div class="adm-table-wrap">
    <div class="adm-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg>
      <h3><?= $q !== '' ? 'No matching parts' : 'No parts yet' ?></h3>
      <p><?= $q !== '' ? 'Nothing matched &ldquo;' . e($q) . '&rdquo;. Try a different search or clear the filter.' : 'Parts appear here once the catalog is imported or added manually.' ?></p>
      <?php if ($q !== ''): ?><a class="btn btn-sm" href="<?= e($_controller->url('/admin/parts')) ?>">Clear search</a>
      <?php else: ?><a class="btn btn-primary btn-sm" href="<?= e($_controller->url('/admin/parts/create')) ?>">Add the first part</a><?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th aria-label="Image"></th>
          <?= $sortHead('Part', 'name') ?>
          <th>Brand</th>
          <th>Category</th>
          <?= $sortHead('Price', 'price', true) ?>
          <th class="right">Fits</th>
          <?= $sortHead('Status', 'status') ?>
          <th aria-label="Actions"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($parts as $p): ?>
          <tr>
            <td>
              <?php if (!empty($p['primary_image_path'])): ?>
                <img class="adm-thumb" src="<?= e(img_url($p['primary_image_path'])) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
              <?php else: ?>
                <span class="adm-thumb adm-thumb--empty" aria-hidden="true">&middot;</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= e($_controller->url('/admin/parts/' . $p['id'] . '/edit')) ?>"><?= e($p['name']) ?></a>
              <span class="sub">#<?= e($p['part_number']) ?> &middot; <?= e($p['sku']) ?></span>
            </td>
            <td><?= $p['brand'] ? e($p['brand']) : '<span class="adm-none">No brand</span>' ?></td>
            <td><?= $p['category'] ? e($p['category']) : '<span class="adm-none">Uncategorized</span>' ?></td>
            <td class="right"><?= money($p['price']) ?></td>
            <td class="right"><?= number_format((int) $p['fits']) ?></td>
            <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td class="right nowrap">
              <a class="link" href="<?= e($_controller->url('/admin/parts/' . $p['id'] . '/edit')) ?>">Edit</a>
              <form method="post" action="<?= e($_controller->url('/admin/parts/' . $p['id'] . '/delete')) ?>"
                    data-confirm="Delete this part? This cannot be undone." class="inline-form">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::token()) ?>">
                <button class="link danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php
  $pagerBase = '/admin/parts';
  $pagerParams = ['q' => $q, 'sort' => $sort === 'updated' ? '' : $sort, 'dir' => $sort === 'updated' ? '' : $dir];
  include __DIR__ . '/../_pager.php';
  ?>
<?php endif; ?>
