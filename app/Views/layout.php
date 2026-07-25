<?php /** @var string $title @var string $content @var \App\Core\Controller $_controller */
$url = fn(string $p) => e($_controller->url($p));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"></noscript>
  <?php $cssv = @filemtime(dirname(__DIR__, 2) . '/assets/css/app.css') ?: date('YmdHis'); ?>
  <link rel="stylesheet" href="<?= $url('assets/css/app.css') ?>?v=<?= $cssv ?>">
  <link rel="icon" href="<?= $url('assets/img/favicon.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= $url('assets/img/apple-touch-icon.png') ?>">
</head>
<body>
<?php $u = $url; include __DIR__ . '/_header.php'; ?>

<?php /* Full-bleed page hero (e.g. shop) — outside .main so it reaches the viewport edges. */
if (!empty($pageHero)) {
    $ph_eyebrow = $pageHero['eyebrow'] ?? '';
    $ph_h1      = $pageHero['h1'] ?? '';
    $ph_lead    = $pageHero['lead'] ?? '';
    $ph_img     = $pageHero['img'] ?? '';
    $ph_rail    = $pageHero['rail'] ?? [];
    include __DIR__ . '/_phero.php';
}
?>

<main class="wrap main<?= !empty($pageHero) ? ' has-hero' : '' ?>">
  <?= $content ?>
</main>

<?php /* Outside .main on purpose: the grey band must reach the viewport edges,
         which a negative margin cannot do once .main hits its max-width. */ ?>
<?php if (!empty($showFaq)) { include __DIR__ . '/_faq.php'; } ?>

<?php $u = $url; include __DIR__ . '/_footer.php'; ?>
<script src="<?= $url('assets/js/app.js') ?>" defer></script>
</body>
</html>
