<?php /** @var array $subs @var int $total @var int $page @var int $per @var \App\Core\Controller $_controller */
$pages = max(1, (int) ceil($total / max(1, $per)));
?>
<h1 class="adm-h1">Subscribers</h1>
<p class="adm-sub">Emails captured by the storefront newsletter form. <strong><?= number_format($total) ?></strong> total.</p>

<?php if (!$subs): ?>
  <p class="adm-none">No subscribers yet.</p>
<?php else: ?>
  <div class="adm-panel">
    <table class="adm-table">
      <thead><tr><th>Email</th><th>Source</th><th>Subscribed</th></tr></thead>
      <tbody>
        <?php foreach ($subs as $s): ?>
          <tr>
            <td><a class="link" href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></td>
            <td><?= e($s['source'] ?? '') ?></td>
            <td><?= e($s['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pager" style="margin-top:20px">
      <?php if ($page > 1): ?>
        <a class="btn btn-sm" href="<?= e($_controller->url('/admin/subscribers?page=' . ($page - 1))) ?>">&larr; Previous</a>
      <?php endif; ?>
      <span class="pager-at">Page <?= (int) $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="btn btn-sm" href="<?= e($_controller->url('/admin/subscribers?page=' . ($page + 1))) ?>">Next &rarr;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
