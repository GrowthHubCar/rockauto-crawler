<?php /** @var array $order @var array $items @var array $addresses @var ?array $payment @var array $statuses @var \App\Core\Controller $_controller */
$u = fn(string $p) => e($_controller->url($p));
$money = fn($v) => '$' . number_format((float) $v, 2);
$ship = $addresses['shipping'] ?? null;
$bill = $addresses['billing'] ?? null;
?>
<p class="adm-back"><a class="link" href="<?= $u('/admin/orders') ?>">&larr; All orders</a></p>

<div class="adm-order-head">
  <div>
    <h1 class="adm-h1 mono"><?= e($order['order_number']) ?></h1>
    <p class="adm-sub">Placed <?= e($order['placed_at']) ?>
      <?= $order['email'] !== '' ? '&middot; ' . e($order['email']) : '' ?></p>
  </div>
  <span class="adm-pill adm-pill-lg adm-pill-<?= e($order['status']) ?>"><?= e($order['status']) ?></span>
</div>

<div class="adm-order-grid">
  <div class="adm-order-main">
    <div class="adm-panel">
      <table class="adm-table">
        <thead><tr><th>Item</th><th>Part #</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Total</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= e($it['name']) ?></td>
              <td class="mono"><?= e($it['part_number'] ?? '') ?></td>
              <td class="r"><?= (int) $it['quantity'] ?></td>
              <td class="r mono"><?= $money($it['unit_price']) ?></td>
              <td class="r mono"><?= $money($it['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="4" class="r">Subtotal</td><td class="r mono"><?= $money($order['subtotal']) ?></td></tr>
          <tr><td colspan="4" class="r">Shipping</td><td class="r mono"><?= $money($order['shipping_total']) ?></td></tr>
          <?php if ((float) $order['tax_total'] > 0): ?>
            <tr><td colspan="4" class="r">Tax</td><td class="r mono"><?= $money($order['tax_total']) ?></td></tr>
          <?php endif; ?>
          <tr class="adm-total"><td colspan="4" class="r"><b>Total</b></td><td class="r mono"><b><?= $money($order['grand_total']) ?></b></td></tr>
        </tfoot>
      </table>
    </div>
  </div>

  <aside class="adm-order-side">
    <div class="adm-card">
      <h3>Update status</h3>
      <form method="post" action="<?= $u('/admin/orders/' . (int) $order['id'] . '/status') ?>" class="adm-status-form">
        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::token()) ?>">
        <select name="status">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>"<?= $s === $order['status'] ? ' selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm" type="submit">Save</button>
      </form>
    </div>

    <div class="adm-card">
      <h3>Payment</h3>
      <?php if ($payment): ?>
        <dl class="adm-kv">
          <dt>Gateway</dt><dd><?= e(ucfirst($payment['gateway'])) ?></dd>
          <dt>Status</dt><dd><?= e($payment['status']) ?></dd>
          <dt>Amount</dt><dd class="mono"><?= $money($payment['amount']) ?></dd>
          <?php if (!empty($payment['gateway_txn_id'])): ?>
            <dt>Txn</dt><dd class="mono adm-break"><?= e($payment['gateway_txn_id']) ?></dd>
          <?php endif; ?>
        </dl>
      <?php else: ?>
        <p class="adm-muted">No payment recorded.</p>
      <?php endif; ?>
    </div>

    <div class="adm-card">
      <h3>Shipping address</h3>
      <?php if ($ship): ?>
        <address class="adm-addr">
          <?php if (!empty($ship['name'])): ?><?= e($ship['name']) ?><br><?php endif; ?>
          <?= e($ship['line1']) ?><br>
          <?php if (!empty($ship['line2'])): ?><?= e($ship['line2']) ?><br><?php endif; ?>
          <?= e(trim($ship['city'] . ', ' . $ship['state'] . ' ' . $ship['postal_code'], ', ')) ?><br>
          <?= e($ship['country']) ?>
        </address>
      <?php else: ?>
        <p class="adm-muted">No shipping address.</p>
      <?php endif; ?>
    </div>

    <?php if ($bill): ?>
    <div class="adm-card">
      <h3>Billing address</h3>
      <address class="adm-addr">
        <?php if (!empty($bill['name'])): ?><?= e($bill['name']) ?><br><?php endif; ?>
        <?= e($bill['line1']) ?><br>
        <?php if (!empty($bill['line2'])): ?><?= e($bill['line2']) ?><br><?php endif; ?>
        <?= e(trim($bill['city'] . ', ' . $bill['state'] . ' ' . $bill['postal_code'], ', ')) ?><br>
        <?= e($bill['country']) ?>
      </address>
    </div>
    <?php endif; ?>
  </aside>
</div>
