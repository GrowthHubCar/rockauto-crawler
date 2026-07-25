<?php /** @var array $orders @var int $total @var int $page @var int $per @var string $status @var array $counts @var float $revenue @var int $all @var \App\Core\Controller $_controller */
$pages = max(1, (int) ceil($total / max(1, $per)));
$u = fn(string $p) => e($_controller->url($p));
$money = fn($v) => '$' . number_format((float) $v, 2);
$tab = function (string $key, string $label, int $n) use ($status, $u) {
    $on = $status === $key ? ' active' : '';
    $q  = $key === '' ? '/admin/orders' : '/admin/orders?status=' . $key;
    return '<a class="adm-tab' . $on . '" href="' . $u($q) . '">' . e($label)
         . ' <span class="adm-tab-n">' . number_format($n) . '</span></a>';
};
?>
<h1 class="adm-h1">Orders</h1>
<p class="adm-sub"><strong><?= number_format($all) ?></strong> orders &middot; <strong><?= $money($revenue) ?></strong> revenue on fulfilled orders.</p>

<div class="adm-tabs">
  <?= $tab('', 'All', $all) ?>
  <?= $tab('pending', 'Pending', $counts['pending'] ?? 0) ?>
  <?= $tab('paid', 'Paid', $counts['paid'] ?? 0) ?>
  <?= $tab('processing', 'Processing', $counts['processing'] ?? 0) ?>
  <?= $tab('shipped', 'Shipped', $counts['shipped'] ?? 0) ?>
  <?= $tab('completed', 'Completed', $counts['completed'] ?? 0) ?>
  <?= $tab('cancelled', 'Cancelled', $counts['cancelled'] ?? 0) ?>
  <?= $tab('refunded', 'Refunded', $counts['refunded'] ?? 0) ?>
</div>

<?php if (!$orders): ?>
  <p class="adm-none">No orders<?= $status !== '' ? ' with this status' : ' yet' ?>.</p>
<?php else: ?>
  <div class="adm-panel">
    <table class="adm-table">
      <thead><tr>
        <th>Order</th><th>Date</th><th>Customer</th><th class="r">Items</th><th class="r">Total</th><th>Status</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><a class="link mono" href="<?= $u('/admin/orders/' . (int) $o['id']) ?>"><?= e($o['order_number']) ?></a></td>
            <td><?= e($o['placed_at']) ?></td>
            <td><?= $o['email'] !== '' ? e($o['email']) : '<span class="adm-muted">no email</span>' ?></td>
            <td class="r"><?= number_format((int) $o['n_items']) ?></td>
            <td class="r mono"><?= $money($o['grand_total']) ?></td>
            <td><span class="adm-pill adm-pill-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
            <td class="r"><a class="btn btn-sm" href="<?= $u('/admin/orders/' . (int) $o['id']) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pager" style="margin-top:20px">
      <?php $qs = $status !== '' ? '&status=' . urlencode($status) : ''; ?>
      <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= $u('/admin/orders?page=' . ($page - 1) . $qs) ?>">&larr; Previous</a><?php endif; ?>
      <span class="pager-at">Page <?= (int) $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= $u('/admin/orders?page=' . ($page + 1) . $qs) ?>">Next &rarr;</a><?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
