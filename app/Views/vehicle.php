<?php
/** @var array $vehicle @var array $categories @var \App\Core\Controller $_controller */
$label = trim($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']
    . ($vehicle['engine'] ? ' ' . $vehicle['engine'] : '')
    . ($vehicle['trim'] ? ' ' . $vehicle['trim'] : ''));
?>
<h1 class="page-title"><?= e($label) ?></h1>
<p class="subtitle">Select a category to see parts that fit this vehicle.</p>
<script>
/* remember this vehicle so the homepage "Resume" can return to it */
try{
  var m = location.pathname.match(/\/vehicle\/([^\/?#]+)/);
  if (m) localStorage.setItem('sp_last_vehicle', JSON.stringify({
    slug: decodeURIComponent(m[1]), label: <?= json_encode($label) ?>
  }));
}catch(e){}
</script>

<?php if (!$categories): ?>
  <p class="empty">No parts are loaded for this vehicle yet.</p>
<?php else: ?>
  <?php
    // Group leaf part-types under their RockAuto parent group ("Wiper & Washer"...).
    $groups = [];
    foreach ($categories as $c) { $groups[$c['group_name']][] = $c; }
  ?>
  <?php foreach ($groups as $gname => $cats): ?>
    <section class="cat-group">
      <h2 class="cat-group-title"><?= e($gname) ?></h2>
      <div class="cat-grid">
        <?php foreach ($cats as $c): ?>
          <a class="cat-card" href="<?= e($_controller->url('/products?vehicle=' . rawurlencode($vehicle['slug']) . '&category=' . rawurlencode($c['slug']))) ?>">
            <span class="cat-name"><?= e($c['name']) ?></span>
            <span class="cat-count"><?= (int)$c['n'] ?> part<?= $c['n'] == 1 ? '' : 's' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
