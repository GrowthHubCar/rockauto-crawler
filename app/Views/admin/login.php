<?php /** @var ?string $error @var string $csrf @var \App\Core\Controller $_controller */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"></noscript>
  <?php $__av = @filemtime(dirname(__DIR__, 3) . '/assets/css/app.css') ?: time();
        $__dv = @filemtime(dirname(__DIR__, 3) . '/assets/css/admin.css') ?: time(); ?>
  <link rel="stylesheet" href="<?= e($_controller->url('assets/css/app.css')) ?>?v=<?= $__av ?>">
  <link rel="stylesheet" href="<?= e($_controller->url('assets/css/admin.css')) ?>?v=<?= $__dv ?>">
  <link rel="icon" href="<?= e($_controller->url('assets/img/favicon.png')) ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= e($_controller->url('assets/img/apple-touch-icon.png')) ?>">
</head>
<body class="admin adm-login-body">
  <form class="adm-login" method="post" action="<?= e($_controller->url('/admin/login')) ?>">
    <div class="adm-login-brand">
      <img class="brand-logo" src="<?= e($_controller->url('assets/img/site-logo.png')) ?>" alt="Supreme Motors Equipments Limited" width="1801" height="904">
    </div>
    <h1>Sign in</h1>
    <?php if ($error): ?>
      <div class="flash flash-error" role="alert">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.5"/><path d="M12 16h.01"/></svg>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>Email<input type="email" name="email" required autofocus autocomplete="username"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit" class="btn btn-primary">Sign in</button>
  </form>
</body>
</html>
