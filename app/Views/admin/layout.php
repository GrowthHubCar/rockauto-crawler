<?php
/** @var string $title @var string $content @var array $_user @var string $_active @var ?array $_flash @var \App\Core\Controller $_controller */

/* Nav grouped into sections; each item carries an inline icon key. */
$nav = [
  'Overview' => [
    'dashboard' => ['Dashboard', '/admin', 'grid'],
    'orders'    => ['Orders', '/admin/orders', 'cart'],
  ],
  'Catalog' => [
    'parts'      => ['Parts', '/admin/parts', 'box'],
    'brands'     => ['Brands', '/admin/brands', 'tag'],
    'categories' => ['Categories', '/admin/categories', 'folder'],
    'catalog'    => ['Vehicle Catalog', '/admin/catalog', 'car'],
  ],
  'Operations' => [
    'imports'     => ['Imports', '/admin/imports', 'sync'],
    'subscribers' => ['Subscribers', '/admin/subscribers', 'tag'],
    'messages'    => ['Messages', '/admin/messages', 'tag'],
    'settings'    => ['Settings', '/admin/settings', 'dollar'],
  ],
];

/** Minimal, consistent 1.75-stroke line icons (functional nav glyphs). */
$icon = function (string $k): string {
  $p = [
    'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'box'    => '<path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>',
    'tag'    => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0l-7-7A2 2 0 0 1 3 12V4a1 1 0 0 1 1-1h8a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.6z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/>',
    'car'    => '<path d="M5 13l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13"/><path d="M4 13h16a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-1H9v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1z"/><circle cx="7.5" cy="16" r="1"/><circle cx="16.5" cy="16" r="1"/>',
    'sync'   => '<path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 3v5h5"/><path d="M21 21v-5h-5"/>',
    'dollar' => '<path d="M12 3v18"/><path d="M17 6.5C17 4.6 14.8 3.5 12 3.5S7 4.6 7 6.5 9.2 9.5 12 9.5s5 1.1 5 3-2.2 3-5 3-5-1.1-5-3"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
    'menu'   => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
    'ext'    => '<path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    'check'  => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
    'alert'  => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4.5"/><path d="M12 16h.01"/>',
    'cart'   => '<path d="M6 6h15l-1.6 9H7.5z"/><circle cx="9.5" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M6 6 5 3H2"/>',
  ];
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$k] ?? '') . '</svg>';
};
$flashIcon = ($_flash['type'] ?? '') === 'ok' ? $icon('check') : $icon('alert');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap"></noscript>
  <?php $__av = @filemtime(dirname(__DIR__, 3) . '/assets/css/app.css') ?: time();
        $__dv = @filemtime(dirname(__DIR__, 3) . '/assets/css/admin.css') ?: time(); ?>
  <link rel="stylesheet" href="<?= e($_controller->url('assets/css/app.css')) ?>?v=<?= $__av ?>">
  <link rel="stylesheet" href="<?= e($_controller->url('assets/css/admin.css')) ?>?v=<?= $__dv ?>">
  <link rel="icon" href="<?= e($_controller->url('assets/img/favicon.png')) ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= e($_controller->url('assets/img/apple-touch-icon.png')) ?>">
</head>
<body class="admin">
<a class="skip-link" href="#adm-main">Skip to content</a>

<header class="adm-top">
  <button class="adm-sidebar-toggle" type="button" aria-label="Toggle navigation" aria-controls="adm-side" aria-expanded="false"><?= $icon('menu') ?></button>
  <a class="brand" href="<?= e($_controller->url('/admin')) ?>" aria-label="Supreme Spare Parts admin home">
    <img class="brand-logo" src="<?= e($_controller->url('assets/img/site-logo.png')) ?>" alt="Supreme Spare Parts" width="220" height="110">
  </a>
  <div class="adm-top-right">
    <a class="adm-viewsite" href="<?= e($_controller->url('/')) ?>" target="_blank" rel="noopener"><span>View store</span> <?= $icon('ext') ?></a>
    <a class="adm-user" href="<?= e($_controller->url('/admin/account')) ?>" title="Edit my account">
      <span class="avatar" aria-hidden="true"><?= e(strtoupper(substr((string)($_user['name'] ?? 'A'), 0, 1))) ?></span>
      <span class="name"><?= e($_user['name'] ?? '') ?></span>
    </a>
    <form method="post" action="<?= e($_controller->url('/admin/logout')) ?>" class="adm-logout">
      <button type="submit" class="btn btn-sm">Sign out</button>
    </form>
  </div>
</header>

<div class="adm-shell">
  <nav class="adm-side" id="adm-side" aria-label="Admin sections">
    <?php foreach ($nav as $section => $items): ?>
      <div class="adm-side__label"><?= e($section) ?></div>
      <?php foreach ($items as $key => [$label, $path, $ic]): ?>
        <a class="<?= $_active === $key ? 'active' : '' ?>" href="<?= e($_controller->url($path)) ?>"
           <?= $_active === $key ? 'aria-current="page"' : '' ?>><?= $icon($ic) ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="adm-side__foot">Supreme Spare Parts admin<br>Reseller storefront console</div>
  </nav>
  <div class="adm-backdrop" hidden></div>

  <main class="adm-main" id="adm-main" tabindex="-1">
    <?php if ($_flash): ?>
      <div class="flash flash-<?= e($_flash['type']) ?>" role="<?= ($_flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>">
        <?= $flashIcon ?><span><?= e($_flash['msg']) ?></span>
      </div>
    <?php endif; ?>
    <?= $content ?>
  </main>
</div>

<!-- Shared confirm dialog — replaces native confirm() for destructive actions on every page. -->
<div class="adm-modal" id="adm-confirm" role="dialog" aria-modal="true" aria-labelledby="adm-confirm-title" aria-describedby="adm-confirm-msg" hidden>
  <div class="adm-modal__backdrop" data-close></div>
  <div class="adm-modal__card">
    <div class="adm-modal__icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
    </div>
    <h2 id="adm-confirm-title">Delete this item?</h2>
    <p id="adm-confirm-msg">This action cannot be undone.</p>
    <div class="adm-modal__actions">
      <button type="button" class="btn btn-ghost" data-close>Cancel</button>
      <button type="button" class="btn btn-danger-solid" id="adm-confirm-ok">Delete</button>
    </div>
  </div>
</div>

<script>
  (function () {
    var body = document.body,
        btn  = document.querySelector('.adm-sidebar-toggle'),
        back = document.querySelector('.adm-backdrop');

    function setNav(open){ body.classList.toggle('nav-open', open); if (btn) btn.setAttribute('aria-expanded', open); if (back) back.hidden = !open; }
    if (btn)  btn.addEventListener('click', function(){ setNav(!body.classList.contains('nav-open')); });
    if (back) back.addEventListener('click', function(){ setNav(false); });

    /* Styled confirmation for any form carrying data-confirm="message". */
    var modal = document.getElementById('adm-confirm'),
        okBtn = document.getElementById('adm-confirm-ok'),
        msgEl = document.getElementById('adm-confirm-msg'),
        pending = null, lastFocus = null;

    function closeModal(){ if (!modal) return; modal.classList.remove('open'); modal.hidden = true; pending = null; if (lastFocus && lastFocus.focus) lastFocus.focus(); }
    function openModal(form){
      if (!modal){ form.submit(); return; }
      pending = form; lastFocus = document.activeElement;
      msgEl.textContent = form.getAttribute('data-confirm') || 'This action cannot be undone.';
      modal.hidden = false; modal.classList.add('open');
      if (okBtn) okBtn.focus();
    }
    document.addEventListener('submit', function(e){
      var f = e.target;
      if (f && f.matches && f.matches('[data-confirm]')){ e.preventDefault(); openModal(f); }
    });
    if (okBtn) okBtn.addEventListener('click', function(){ var f = pending; closeModal(); if (f) f.submit(); }); // form.submit() skips the submit event, so no loop
    if (modal) modal.querySelectorAll('[data-close]').forEach(function(el){ el.addEventListener('click', closeModal); });

    document.addEventListener('keydown', function(e){
      if (e.key !== 'Escape') return;
      if (modal && modal.classList.contains('open')) closeModal(); else setNav(false);
    });
  })();
</script>
</body>
</html>
