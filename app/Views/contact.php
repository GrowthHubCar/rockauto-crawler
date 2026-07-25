<?php /** @var bool $sent @var array $errors @var array $old @var \App\Core\Controller $_controller */
$url = fn(string $p) => e($_controller->url($p));
$v   = fn(string $k) => e((string) ($old[$k] ?? ''));
$err = fn(string $k) => $errors[$k] ?? '';
$n   = fn($x) => number_format((int) $x);

$wa      = trim((string) (setting('contact_whatsapp', '') ?? ''));
$waUrl   = whatsapp_link('Hi, I need help finding a part.');
$email   = trim((string) (setting('contact_email', '') ?? ''));
$address = trim((string) (setting('contact_address', '') ?? ''));
$socials = social_links();
?>
<div class="ct-top">
  <!-- Left: pitch + direct channels -->
  <div class="ct-info">
    <h1 class="ct-h1">How can we help<br>you today?</h1>
    <p class="ct-lead">Send us a part number, a VIN, or a photo of the old part &mdash; someone who
       knows the catalog will confirm the right fit before you spend anything.</p>

    <ul class="cinfo">
      <?php if ($email !== ''): ?>
        <li>
          <span class="ci-ic"><svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="1"/><path d="m4 7 8 6 8-6"/></svg></span>
          <span class="ci-k">Email</span>
          <a class="ci-v" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
        </li>
      <?php endif; ?>
      <?php if ($wa !== ''): ?>
        <li class="is-wa">
          <span class="ci-ic"><svg width="17" height="17" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.15a8.2 8.2 0 0 1-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.22 8.22 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.25-8.23a8.2 8.2 0 0 1 8.24 8.24c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.19-.54.06-.25-.12-1.05-.39-2-1.23a7.5 7.5 0 0 1-1.38-1.72c-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.44.13-.15.17-.25.25-.41.09-.17.04-.31-.02-.44-.06-.12-.56-1.35-.77-1.85-.2-.48-.4-.42-.56-.43h-.47c-.16 0-.43.06-.65.31-.23.24-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.21 3.72.59.25 1.05.4 1.4.52.59.18 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.17-.47-.29Z"/></svg></span>
          <span class="ci-k">WhatsApp &mdash; fastest reply</span>
          <?php if ($waUrl !== ''): ?>
            <a class="ci-v" href="<?= e($waUrl) ?>" target="_blank" rel="noopener"><?= e($wa) ?></a>
          <?php else: ?>
            <span class="ci-v"><?= e($wa) ?></span>
          <?php endif; ?>
        </li>
      <?php endif; ?>
      <?php if ($address !== ''): ?>
        <li>
          <span class="ci-ic"><svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg></span>
          <span class="ci-k">Location</span>
          <span class="ci-v"><?= e($address) ?></span>
        </li>
      <?php endif; ?>
    </ul>

    <?php if ($socials): ?>
      <div class="ct-social"><?php include __DIR__ . '/_socials.php'; ?></div>
    <?php endif; ?>

    <?php if ($waUrl === '' && $email === '' && $address === '' && !$socials): ?>
      <p class="empty">Contact details have not been set yet. Add them in Admin &rsaquo; Settings.</p>
    <?php endif; ?>
  </div>

  <!-- Right: the form, in a panel -->
  <div class="ct-panel">
    <?php if ($sent): ?>
      <div class="formnote is-ok" role="status">
        <b>Message sent.</b>
        <span>We reply to most enquiries within one business day.
          <?php if ($waUrl !== ''): ?>Need it sooner? <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a>.<?php endif; ?>
        </span>
      </div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="formnote is-bad" role="alert">
        <b>That didn't send.</b><span>Check the highlighted fields and try again.</span>
      </div>
    <?php endif; ?>

    <form class="cform" method="post" action="<?= $url('/contact') ?>" novalidate>
      <div class="fgrid">
        <label class="fld<?= $err('first_name') ? ' has-err' : '' ?>">
          <span>First name <i>*</i></span>
          <input type="text" name="first_name" value="<?= $v('first_name') ?>" maxlength="60"
                 required autocomplete="given-name" placeholder="Alex">
          <?php if ($err('first_name')): ?><em><?= e($err('first_name')) ?></em><?php endif; ?>
        </label>

        <label class="fld<?= $err('last_name') ? ' has-err' : '' ?>">
          <span>Last name <i>*</i></span>
          <input type="text" name="last_name" value="<?= $v('last_name') ?>" maxlength="60"
                 required autocomplete="family-name" placeholder="Morgan">
          <?php if ($err('last_name')): ?><em><?= e($err('last_name')) ?></em><?php endif; ?>
        </label>

        <label class="fld is-wide<?= $err('email') ? ' has-err' : '' ?>">
          <span>Email <i>*</i></span>
          <input type="email" name="email" value="<?= $v('email') ?>" maxlength="190" required
                 autocomplete="email" placeholder="you@example.com">
          <?php if ($err('email')): ?><em><?= e($err('email')) ?></em><?php endif; ?>
        </label>

        <label class="fld<?= $err('phone') ? ' has-err' : '' ?>">
          <span>Phone <i class="opt">optional</i></span>
          <input type="tel" name="phone" value="<?= $v('phone') ?>" maxlength="60"
                 autocomplete="tel" placeholder="+1 555 010 1234">
          <?php if ($err('phone')): ?><em><?= e($err('phone')) ?></em><?php endif; ?>
        </label>

        <label class="fld<?= $err('vehicle') ? ' has-err' : '' ?>">
          <span>Vehicle or VIN <i class="opt">optional</i></span>
          <input type="text" name="vehicle" value="<?= $v('vehicle') ?>" maxlength="190"
                 placeholder="2004 Chevrolet Tahoe 5.3L">
          <?php if ($err('vehicle')): ?><em><?= e($err('vehicle')) ?></em><?php endif; ?>
        </label>

        <label class="fld is-wide<?= $err('message') ? ' has-err' : '' ?>">
          <span>Message <i>*</i></span>
          <textarea name="message" rows="6" maxlength="5000" required
                    placeholder="A question, the part numbers you're comparing, or your order number..."><?= $v('message') ?></textarea>
          <?php if ($err('message')): ?><em><?= e($err('message')) ?></em><?php endif; ?>
        </label>
      </div>

      <?php /* honeypot: hidden from people, irresistible to bots */ ?>
      <div class="hp" aria-hidden="true">
        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="cform-foot">
        <button class="btn btn-accent" type="submit">Send Message</button>
        <span>We reply within one business day. We never share your details.</span>
      </div>
    </form>
  </div>
</div>



