<?php
/** Shared site footer — included by layout.php and by the standalone home.php.
 *  Two columns only: the same navigation as the masthead, and contact details.
 *  Contact rows come from Admin > Settings and each is omitted when blank. */
$f = $u ?? (isset($_controller)
        ? (fn(string $p) => e($_controller->url($p)))
        : (fn(string $p) => e(rtrim($base ?? '', '/') . '/' . ltrim($p, '/'))));

$fWaNum = trim((string) (setting('contact_whatsapp', '') ?? ''));
$fWa    = whatsapp_link('Hi, I need help finding a part.');
$fEmail = trim((string) (setting('contact_email', '') ?? ''));
$fAddr  = trim((string) (setting('contact_address', '') ?? ''));
?>
<footer class="site-footer">
  <div class="wrap footer-inner">
    <div class="fbrand">
      <a class="brand" href="<?= $f('/') ?>" aria-label="Supreme Parts">
        <img class="brand-logo" src="<?= $f('assets/img/site-logo.png') ?>" alt="Supreme Parts">
      </a>
      <p>Replacement and performance parts matched to your exact vehicle by verified fitment data
         &mdash; so what you order is what fits.</p>
    </div>

    <nav class="fnav" aria-label="Footer">
      <h5>Quick Links</h5>
      <ul>
        <li><a href="<?= $f('/') ?>">Home</a></li>
        <li><a href="<?= $f('/products') ?>">Shop</a></li>
        <li><a href="<?= $f('/brands') ?>">Brands</a></li>
        <li><a href="<?= $f('/about') ?>">About Us</a></li>
        <li><a href="<?= $f('/contact') ?>">Contact Us</a></li>
      </ul>
    </nav>

    <div class="fcontactcol">
      <h5>Contact</h5>
      <ul class="fcontact">
        <?php if ($fWaNum !== ''): ?>
          <li><span class="fc-ico"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg></span>
            <a href="<?= e($fWa !== '' ? $fWa : '#') ?>"<?= $fWa !== '' ? ' target="_blank" rel="noopener"' : '' ?>><?= e($fWaNum) ?></a></li>
        <?php endif; ?>
        <?php if ($fEmail !== ''): ?>
          <li><span class="fc-ico"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="1"/><path d="m4 7 8 6 8-6"/></svg></span>
            <a href="mailto:<?= e($fEmail) ?>"><?= e($fEmail) ?></a></li>
        <?php endif; ?>
        <?php if ($fAddr !== ''): ?>
          <li><span class="fc-ico"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg></span>
            <span><?= e($fAddr) ?></span></li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="fnews">
      <h5>Newsletter</h5>
      <p>New arrivals and fitment tips. No spam &mdash; unsubscribe any time.</p>
      <form class="fnews-form" method="post" action="<?= $f('/newsletter') ?>" novalidate>
        <input type="email" name="email" placeholder="Enter your email" aria-label="Email address" required>
        <button class="btn btn-accent" type="submit">Subscribe</button>
      </form>
      <p class="fnews-msg" id="fnewsMsg" role="status" hidden></p>
    </div>
  </div>

  <div class="footer-base"><div class="wrap">
    <span>&copy; <?= date('Y') ?> Supreme Parts. Every part matched to your vehicle by verified fitment data.</span>
    <span class="sp"></span>
    <div class="fpay">
      <img src="<?= $f('assets/img/pay/visa.svg') ?>" alt="Visa" height="20">
      <img src="<?= $f('assets/img/pay/mastercard.svg') ?>" alt="Mastercard" height="20">
      <img src="<?= $f('assets/img/pay/amex.svg') ?>" alt="American Express" height="20">
      <img src="<?= $f('assets/img/pay/paypal.svg') ?>" alt="PayPal" height="20">
    </div>
    <?php include __DIR__ . '/_socials.php'; ?>
  </div></div>
</footer>

<script>
/* Newsletter: AJAX submit with an inline message, so the visitor never sees the
   raw JSON response. Falls back to a normal POST if fetch is unavailable. */
(function(){
  var form = document.querySelector('.fnews-form'), msg = document.getElementById('fnewsMsg');
  if (!form || !window.fetch) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var btn = form.querySelector('button');
    btn.disabled = true;
    fetch(form.action, {method:'POST', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, body:new FormData(form)})
      .then(function(r){ return r.json(); })
      .then(function(d){
        btn.disabled = false;
        msg.hidden = false;
        if (d && d.ok) { msg.className = 'fnews-msg is-ok'; msg.textContent = 'Thanks — you’re subscribed.'; form.reset(); }
        else { msg.className = 'fnews-msg is-bad'; msg.textContent = (d && d.error) || 'Could not subscribe. Try again.'; }
      })
      .catch(function(){ btn.disabled = false; msg.hidden = false; msg.className = 'fnews-msg is-bad'; msg.textContent = 'Could not subscribe. Try again.'; });
  });
})();
</script>
