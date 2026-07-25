<?php
/**
 * Shared admin pagination control.
 * Expects in scope: $page, $total, $perPage, $_controller,
 *   $pagerBase (route path e.g. '/admin/parts'),
 *   $pagerParams (assoc array of query params to preserve, e.g. ['q'=>$q]).
 * Renders "Showing X-Y of Z", Prev/Next, numbered pages with ellipsis, and a
 * rows-per-page selector. Preserves search/filter/sort params across pages.
 */
$pp    = max(1, (int) $perPage);
$total = (int) $total;
$pages = (int) max(1, ceil($total / $pp));
$page  = min(max(1, (int) $page), $pages);
$from  = $total ? ($page - 1) * $pp + 1 : 0;
$to    = min($total, $page * $pp);

$link = function (int $p, ?int $per = null) use ($_controller, $pagerBase, $pagerParams, $pp) {
    $q = array_filter($pagerParams ?? [], fn ($v) => $v !== '' && $v !== null);
    $q['page'] = $p;
    $q['per']  = $per ?? $pp;
    return $_controller->url($pagerBase . '?' . http_build_query($q));
};

/* Page-number window: 1 … (page-1, page, page+1) … last */
$win = [1];
for ($i = $page - 1; $i <= $page + 1; $i++) {
    if ($i > 1 && $i < $pages) { $win[] = $i; }
}
if ($pages > 1) { $win[] = $pages; }
$win = array_values(array_unique($win));
sort($win);
?>
<nav class="adm-pager" aria-label="Pagination">
  <p class="adm-pager__meta">Showing <strong><?= number_format($from) ?>-<?= number_format($to) ?></strong> of <strong><?= number_format($total) ?></strong></p>

  <?php if ($pages > 1): ?>
    <div class="adm-pager__controls">
      <?php if ($page > 1): ?>
        <a class="nav" href="<?= e($link($page - 1)) ?>" rel="prev">&lsaquo; Prev</a>
      <?php else: ?>
        <span class="nav disabled" aria-disabled="true">&lsaquo; Prev</span>
      <?php endif; ?>

      <?php $prev = 0; foreach ($win as $p): ?>
        <?php if ($prev && $p - $prev > 1): ?><span class="gap" aria-hidden="true">&hellip;</span><?php endif; ?>
        <?php if ($p === $page): ?>
          <span class="current" aria-current="page"><?= $p ?></span>
        <?php else: ?>
          <a href="<?= e($link($p)) ?>" aria-label="Page <?= $p ?>"><?= $p ?></a>
        <?php endif; ?>
        <?php $prev = $p; endforeach; ?>

      <?php if ($page < $pages): ?>
        <a class="nav" href="<?= e($link($page + 1)) ?>" rel="next">Next &rsaquo;</a>
      <?php else: ?>
        <span class="nav disabled" aria-disabled="true">Next &rsaquo;</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="adm-pager__rows">
    <label for="adm-per">Rows</label>
    <select id="adm-per" onchange="location.href=this.value" aria-label="Rows per page">
      <?php foreach ([25, 50, 100] as $n): ?>
        <option value="<?= e($link(1, $n)) ?>" <?= $pp === $n ? 'selected' : '' ?>><?= $n ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</nav>
