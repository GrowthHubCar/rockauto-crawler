<?php /** @var array $brands @var \App\Core\Controller $_controller */
$url = fn(string $p) => e($_controller->url($p));
?>
<h1 class="page-title">Brands</h1>
<p class="subtitle">Every manufacturer we stock. Pick one to see its parts.</p>

<?php if (!$brands): ?>
  <p class="empty">No brands available yet.</p>
<?php else: ?>
  <div class="brandgrid">
    <?php foreach ($brands as $b): ?>
      <a class="brandcard" href="<?= $url('/products?brand=' . urlencode($b['slug'])) ?>">
        <span class="brandcard-name"><?= e($b['name']) ?></span>
        <span class="brandcard-n"><?= number_format((int) $b['n']) ?> parts</span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
