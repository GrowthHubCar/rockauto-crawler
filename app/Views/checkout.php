<?php /** @var array $items @var array $totals @var array $errors @var array $old @var bool $stripeLive @var string $csrf @var \App\Core\Controller $_controller */
$url = fn(string $p) => e($_controller->url($p));
$v   = fn(string $k) => e((string) ($old[$k] ?? ''));
$err = fn(string $k) => $errors[$k] ?? '';
$states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC'];
?>
<h1 class="page-title">Checkout</h1>
<p class="subtitle">Enter your contact and shipping details.<?= $stripeLive ? ' Payment is handled securely by Stripe on the next step.' : '' ?></p>

<form class="co" method="post" action="<?= $url('/checkout') ?>" novalidate>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <div class="co-main">
    <?php if ($errors): ?>
      <div class="formnote is-bad" role="alert"><b>Check your details.</b><span>Some fields below need attention.</span></div>
    <?php endif; ?>

    <section class="co-sec">
      <h2 class="secline"><span>Contact</span></h2>
      <label class="fld is-wide<?= $err('email') ? ' has-err' : '' ?>">
        <span>Email <i>*</i></span>
        <input type="email" name="email" value="<?= $v('email') ?>" autocomplete="email" placeholder="you@example.com">
        <?php if ($err('email')): ?><em><?= e($err('email')) ?></em><?php endif; ?>
      </label>
    </section>

    <section class="co-sec">
      <h2 class="secline"><span>Shipping address</span></h2>
      <div class="fgrid">
        <label class="fld<?= $err('first') ? ' has-err' : '' ?>">
          <span>First name <i>*</i></span>
          <input type="text" name="first" value="<?= $v('first') ?>" autocomplete="given-name">
          <?php if ($err('first')): ?><em><?= e($err('first')) ?></em><?php endif; ?>
        </label>
        <label class="fld<?= $err('last') ? ' has-err' : '' ?>">
          <span>Last name <i>*</i></span>
          <input type="text" name="last" value="<?= $v('last') ?>" autocomplete="family-name">
          <?php if ($err('last')): ?><em><?= e($err('last')) ?></em><?php endif; ?>
        </label>
        <label class="fld is-wide<?= $err('line1') ? ' has-err' : '' ?>">
          <span>Street address <i>*</i></span>
          <input type="text" name="line1" value="<?= $v('line1') ?>" autocomplete="address-line1" placeholder="1200 Motorway Drive">
          <?php if ($err('line1')): ?><em><?= e($err('line1')) ?></em><?php endif; ?>
        </label>
        <label class="fld is-wide">
          <span>Apartment, suite, etc. <i class="opt">optional</i></span>
          <input type="text" name="line2" value="<?= $v('line2') ?>" autocomplete="address-line2">
        </label>
        <label class="fld<?= $err('city') ? ' has-err' : '' ?>">
          <span>City <i>*</i></span>
          <input type="text" name="city" value="<?= $v('city') ?>" autocomplete="address-level2">
          <?php if ($err('city')): ?><em><?= e($err('city')) ?></em><?php endif; ?>
        </label>
        <label class="fld<?= $err('state') ? ' has-err' : '' ?>">
          <span>State <i>*</i></span>
          <select name="state">
            <option value="">Select…</option>
            <?php foreach ($states as $s): ?>
              <option value="<?= $s ?>"<?= ($old['state'] ?? '') === $s ? ' selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($err('state')): ?><em><?= e($err('state')) ?></em><?php endif; ?>
        </label>
        <label class="fld<?= $err('zip') ? ' has-err' : '' ?>">
          <span>ZIP code <i>*</i></span>
          <input type="text" name="zip" value="<?= $v('zip') ?>" autocomplete="postal-code" inputmode="numeric">
          <?php if ($err('zip')): ?><em><?= e($err('zip')) ?></em><?php endif; ?>
        </label>
        <label class="fld">
          <span>Phone <i class="opt">optional</i></span>
          <input type="tel" name="phone" value="<?= $v('phone') ?>" autocomplete="tel">
        </label>
        <input type="hidden" name="country" value="US">
      </div>
    </section>
  </div>

  <aside class="co-side">
    <div class="co-summary">
      <h2>Order summary</h2>
      <ul class="co-items">
        <?php foreach ($items as $it): ?>
          <li>
            <span class="co-q"><?= (int) $it['quantity'] ?>&times;</span>
            <span class="co-nm"><?= e($it['name']) ?></span>
            <span class="co-pr"><?= money($it['line_total']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="co-row"><span>Subtotal</span><span><?= money($totals['subtotal']) ?></span></div>
      <div class="co-row"><span>Shipping</span><span><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'FREE' ?></span></div>
      <div class="co-row co-tot"><span>Total</span><span><?= money($totals['grand']) ?></span></div>

      <button class="btn btn-accent btn-block" type="submit">
        <?= $stripeLive ? 'Continue to payment &rarr;' : 'Place order &rarr;' ?>
      </button>
      <p class="co-secure">
        <?php if ($stripeLive): ?>
          &#128274; Card details are entered on Stripe&rsquo;s secure page. We never see them.
        <?php else: ?>
          Test mode &mdash; no live payment is taken. Add Stripe keys in Admin &rsaquo; Settings to accept cards.
        <?php endif; ?>
      </p>
      <p class="co-back"><a class="link" href="<?= $url('/cart') ?>">&larr; Back to cart</a></p>
    </div>
  </aside>
</form>
