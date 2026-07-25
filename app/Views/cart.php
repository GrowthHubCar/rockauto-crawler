<?php /** @var array $items @var array $totals @var float $freeOver @var \App\Core\Controller $_controller */
$url = fn(string $p) => e($_controller->url($p));
$count = 0; foreach ($items as $it) { $count += (int) $it['quantity']; }
?>
<div class="cart-head">
  <h1 class="page-title">My Shopping Cart</h1>
  <ol class="cart-steps" aria-label="Checkout progress">
    <li class="on"><span class="n">1</span> Shopping Bag</li>
    <li><span class="n">2</span> Checkout</li>
  </ol>
</div>

<?php if (!$items): ?>
  <div class="cart-empty">
    <p class="empty">Your cart is empty.</p>
    <a class="btn btn-accent" href="<?= $url('/products') ?>">Browse all parts &rarr;</a>
  </div>
<?php else: ?>

<?php if ($freeOver > 0):
  $remaining = max(0, $freeOver - (float) $totals['subtotal']);
  $reached = $remaining <= 0;
?>
  <div class="cart-ship-note<?= $reached ? ' is-on' : '' ?>" data-free-note>
    <?php if ($reached): ?>
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
      <span data-free-text>Nice &mdash; your order qualifies for <b>free shipping</b>.</span>
    <?php else: ?>
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="1.6"/><circle cx="17.5" cy="17" r="1.6"/></svg>
      <span data-free-text>Add <b><?= money($remaining) ?></b> more to unlock <b>free shipping</b>.</span>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="cart-wrap">
  <div class="cart-lines">
    <div class="cart-lines-head">
      <span>Your Order (<span data-cart="count"><?= $count ?></span>)</span>
      <span class="cl-sub">Subtotal</span>
      <span class="cl-rm">Remove</span>
    </div>

    <?php foreach ($items as $it): ?>
      <div class="cart-line">
        <a class="cl-thumb" href="<?= $url('/part/' . rawurlencode($it['sku'])) ?>">
          <?php if (!empty($it['primary_image_path'])): ?>
            <img src="<?= e(img_url($it['primary_image_path'])) ?>" alt="<?= e($it['name']) ?>"
                 loading="lazy" onerror="this.style.opacity=0">
          <?php endif; ?>
        </a>

        <div class="cl-info">
          <a class="cl-name" href="<?= $url('/part/' . rawurlencode($it['sku'])) ?>"><?= e($it['name']) ?></a>
          <div class="cl-meta">
            <span>SKU: <?= e($it['part_number'] ?: $it['sku']) ?></span>
            <?php if (!empty($it['brand'])): ?><span>Brand: <?= e($it['brand']) ?></span><?php endif; ?>
            <?php if (!empty($it['variant_type'])): ?><span><?= e($it['variant_type']) ?></span><?php endif; ?>
          </div>
          <form class="cl-qty" method="post" action="<?= $url('/cart/update') ?>">
            <input type="hidden" name="part_id" value="<?= (int) $it['part_id'] ?>">
            <input type="hidden" name="variant_id" value="<?= (int) $it['variant_id'] ?>">
            <span class="qlab">Quantity:</span>
            <button class="qbtn" type="submit" name="qty" value="<?= max(1, (int) $it['quantity'] - 1) ?>" aria-label="Decrease">&minus;</button>
            <span class="qval"><?= str_pad((string) (int) $it['quantity'], 2, '0', STR_PAD_LEFT) ?></span>
            <button class="qbtn" type="submit" name="qty" value="<?= (int) $it['quantity'] + 1 ?>" aria-label="Increase">+</button>
          </form>
        </div>

        <div class="cl-price">
          <b><?= money($it['line_total']) ?></b>
          <span class="cl-ea"><?= money($it['unit_price']) ?> each</span>
        </div>

        <form method="post" action="<?= $url('/cart/remove') ?>" class="cl-remove">
          <input type="hidden" name="part_id" value="<?= (int) $it['part_id'] ?>">
          <input type="hidden" name="variant_id" value="<?= (int) $it['variant_id'] ?>">
          <button type="submit" aria-label="Remove item" title="Remove">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <aside class="cart-summary">
    <h2>Order Summary</h2>
    <div class="row"><span data-cart="count-label">Subtotal (<?= $count ?> item<?= $count === 1 ? '' : 's' ?>)</span><span data-cart="subtotal"><?= money($totals['subtotal']) ?></span></div>
    <div class="row"><span>Delivery charges</span><span data-cart="shipping"><?= $totals['shipping'] > 0 ? money($totals['shipping']) : '<b class="free">Free</b>' ?></span></div>
    <div class="row total"><span>Order total</span><span data-cart="total"><?= money($totals['grand']) ?></span></div>
    <div class="row pay"><span>Total Payment</span><span data-cart="pay"><?= money($totals['grand']) ?></span></div>
    <a class="btn btn-accent btn-block" href="<?= $url('/checkout') ?>">Proceed to checkout</a>
    <p class="secure-note">&#128274; Secure checkout &middot; SSL encrypted</p>
  </aside>
</div>

<script>
/* AJAX cart: qty +/- and remove update in place. Without JS the forms POST
   normally and redirect, so the cart still works. */
(function(){
  var lines = document.querySelector('.cart-lines');
  if (!lines) return;
  var badge = document.querySelector('.cart-badge, .iconbtn.cart .badge');

  function post(url, data){
    return fetch(url, {
      method: 'POST',
      headers: {'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
      body: new URLSearchParams(data)
    }).then(function(r){ return r.json(); });
  }
  function pad(n){ n = String(n); return n.length < 2 ? '0'+n : n; }
  function applyTotals(d){
    document.querySelectorAll('[data-cart="subtotal"]').forEach(function(el){ el.textContent = d.subtotal; });
    document.querySelectorAll('[data-cart="total"]').forEach(function(el){ el.textContent = d.grand; });
    document.querySelectorAll('[data-cart="pay"]').forEach(function(el){ el.textContent = d.grand; });
    var sh = document.querySelector('[data-cart="shipping"]');
    if (sh) sh.innerHTML = d.shippingFree ? '<b class="free">Free</b>' : d.shipping;
    var cnt = document.querySelector('[data-cart="count"]');
    if (cnt) cnt.textContent = d.count;
    var cl = document.querySelector('[data-cart="count-label"]');
    if (cl) cl.textContent = 'Subtotal (' + d.count + ' item' + (d.count === 1 ? '' : 's') + ')';
    if (badge) badge.textContent = d.count;

    // free-shipping banner
    var note = document.querySelector('[data-free-note]');
    if (note && d.freeEligible) {
      var txt = note.querySelector('[data-free-text]');
      if (d.freeReached) {
        note.classList.add('is-on');
        txt.innerHTML = 'Nice &mdash; your order qualifies for <b>free shipping</b>.';
      } else {
        note.classList.remove('is-on');
        txt.innerHTML = 'Add <b>' + d.freeRemaining + '</b> more to unlock <b>free shipping</b>.';
      }
    }
  }

  lines.addEventListener('submit', function(e){
    var form = e.target;
    var isQty = form.classList.contains('cl-qty');
    var isRm  = form.classList.contains('cl-remove');
    if (!isQty && !isRm) return;
    e.preventDefault();

    var pid = form.querySelector('[name=part_id]').value;
    var vid = form.querySelector('[name=variant_id]').value;
    var body = {part_id: pid, variant_id: vid};
    if (isQty) { body.qty = (e.submitter && e.submitter.value) || form.querySelector('[name=qty]').value; }

    var line = form.closest('.cart-line');
    line.style.opacity = .5;
    post(form.action, body).then(function(d){
      if (!d || !d.ok) { line.style.opacity = 1; return; }
      if (d.empty) { location.reload(); return; }
      if (d.removed || isRm) { line.remove(); }
      else {
        line.style.opacity = 1;
        line.querySelector('.qval').textContent = pad(d.quantity);
        var btns = form.querySelectorAll('.qbtn');
        btns[0].value = Math.max(1, d.quantity - 1);
        btns[1].value = d.quantity + 1;
        line.querySelector('.cl-price b').textContent = d.lineTotal;
      }
      applyTotals(d);
    }).catch(function(){ line.style.opacity = 1; });
  });
})();
</script>
<?php endif; ?>
