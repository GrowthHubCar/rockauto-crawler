<?php /** @var string $markup @var string $csrf @var array $contact @var array $socials @var array $delivery @var array $stripe @var \App\Core\Controller $_controller */ ?>
<h1 class="adm-h1">Settings</h1>
<p class="adm-sub">Pricing, delivery, contact details, and payment keys. Every customer-facing price is your RockAuto cost multiplied by the markup; costs stay stored as-is.</p>

<form method="post" action="<?= e($_controller->url('/admin/settings')) ?>" class="adm-form adm-settings" style="max-width:620px">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <div class="adm-tabs" id="setTabs" role="tablist">
    <button type="button" class="adm-tab active" role="tab" data-tab="pricing">Pricing &amp; Delivery</button>
    <button type="button" class="adm-tab" role="tab" data-tab="contact">Contact &amp; Social</button>
    <button type="button" class="adm-tab" role="tab" data-tab="payments">Payments</button>
  </div>

  <!-- Pricing & Delivery -->
  <div class="adm-tabpanel" data-panel="pricing">
    <div class="adm-form__section">
      <p class="adm-form__section-title">Default commission rate</p>
      <label class="adm-field">
        <span>Default commission</span>
        <span class="adm-inline">
          <input type="number" name="markup_pct" value="<?= e($markup) ?>"
                 min="0" max="100000" step="0.01" inputmode="decimal"
                 style="width:140px" oninput="rmPreview(this.value)" aria-describedby="rmPreview">
          <span>%</span>
        </span>
      </label>
      <small class="hint">Applied to every category that has no individual rate of its own. Set per-category rates on the <a href="<?= e($_controller->url('/admin/categories')) ?>">Categories</a> page.</small>
      <p class="hint" id="rmPreview" style="margin:10px 0 0">&nbsp;</p>
    </div>
    <div class="adm-form__section">
      <p class="adm-form__section-title">Delivery</p>
      <label class="adm-field">
        <span>Delivery charge ($)</span>
        <input type="number" name="delivery_flat" value="<?= e($delivery['flat']) ?>" min="0" step="0.01" inputmode="decimal">
        <small class="hint">Flat shipping fee added at checkout when the order is below the free-shipping threshold.</small>
      </label>
      <label class="adm-field">
        <span>Free shipping over ($)</span>
        <input type="number" name="delivery_free_over" value="<?= e($delivery['free_over']) ?>" min="0" step="0.01" inputmode="decimal">
        <small class="hint">Orders at or above this subtotal ship free. Set to 0 to always charge the delivery fee.</small>
      </label>
    </div>
  </div>

  <!-- Contact & Social -->
  <div class="adm-tabpanel" data-panel="contact" hidden>
    <div class="adm-form__section">
      <p class="adm-form__section-title">Contact details</p>
      <?php foreach ($contact as $key => $c): ?>
        <label class="adm-field">
          <span><?= e($c['label']) ?></span>
          <input type="text" name="contact_<?= e($key) ?>" value="<?= e($c['value']) ?>">
          <small class="hint"><?= e($c['hint']) ?></small>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="adm-form__section">
      <p class="adm-form__section-title">Social links</p>
      <p class="hint" style="margin:0 0 14px">Shown as icons in the storefront footer. Leave a field blank to hide that network.</p>
      <?php foreach ($socials as $key => $s): ?>
        <label class="adm-field">
          <span><?= e($s['label']) ?></span>
          <input type="url" name="social_<?= e($key) ?>" value="<?= e($s['value']) ?>"
                 inputmode="url" placeholder="https://<?= e($key === 'x' ? 'x' : $key) ?>.com/yourbrand">
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Payments -->
  <div class="adm-tabpanel" data-panel="payments" hidden>
    <div class="adm-form__section">
      <p class="adm-form__section-title">Payments (Stripe)</p>
      <p class="hint" style="margin:0 0 14px">
        <?php if ($stripe['enabled']): ?>
          <span class="adm-ok-tag">&#10003; Live &mdash; card payments are active at checkout.</span>
        <?php else: ?>
          <span class="adm-warn-tag">Not configured &mdash; checkout runs in test/mock mode until a secret key is saved.</span>
        <?php endif; ?>
        Find these in your Stripe Dashboard &rsaquo; Developers &rsaquo; API keys.
      </p>
      <label class="adm-field">
        <span>Publishable key</span>
        <input type="text" name="stripe_publishable" value="<?= e($stripe['publishable']) ?>"
               placeholder="pk_live_… or pk_test_…" autocomplete="off" spellcheck="false">
        <small class="hint">Public key, safe to display. Used by Stripe Checkout.</small>
      </label>
      <label class="adm-field">
        <span>Secret key</span>
        <input type="password" name="stripe_secret" value="" autocomplete="off" spellcheck="false"
               placeholder="<?= $stripe['has_secret'] ? '•••••••• saved — leave blank to keep' : 'sk_live_… or sk_test_…' ?>">
        <small class="hint">Write-only. Leave blank to keep the current key. Never shared with the browser.</small>
      </label>
      <label class="adm-field">
        <span>Webhook signing secret</span>
        <input type="password" name="stripe_webhook_secret" value="" autocomplete="off" spellcheck="false"
               placeholder="<?= $stripe['has_webhook'] ? '•••••••• saved — leave blank to keep' : 'whsec_…' ?>">
        <small class="hint">From your webhook endpoint. Verifies Stripe callbacks. Optional but recommended.</small>
      </label>
    </div>
  </div>

  <div class="adm-form-actions">
    <button class="btn btn-primary" type="submit">Save settings</button>
  </div>
</form>

<script>
  function rmPreview(v){
    var pct = parseFloat(v); if (isNaN(pct) || pct < 0) pct = 0;
    var f = 1 + pct/100;
    var ex = [10, 49.99, 250];
    document.getElementById('rmPreview').textContent =
      'Example: ' + ex.map(function(c){
        return '$' + c.toFixed(2) + ' cost becomes $' + (c*f).toFixed(2);
      }).join('   ·   ');
  }
  rmPreview(document.querySelector('[name=markup_pct]').value);

  (function(){
    var tabs = document.getElementById('setTabs');
    tabs.addEventListener('click', function(e){
      var t = e.target.closest('.adm-tab'); if (!t) return;
      tabs.querySelectorAll('.adm-tab').forEach(function(x){ x.classList.remove('active'); });
      t.classList.add('active');
      document.querySelectorAll('.adm-tabpanel').forEach(function(p){ p.hidden = p.dataset.panel !== t.dataset.tab; });
    });
  })();
</script>
