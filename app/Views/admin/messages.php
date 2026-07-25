<?php /** @var array $msgs @var int $total @var int $page @var int $per @var string $status @var array $counts @var \App\Core\Controller $_controller */
$pages = max(1, (int) ceil($total / max(1, $per)));
$u = fn(string $p) => e($_controller->url($p));
$tab = function (string $key, string $label, int $n) use ($status, $u) {
    $on = $status === $key ? ' active' : '';
    $q  = $key === '' ? '/admin/messages' : '/admin/messages?status=' . $key;
    return '<a class="adm-tab' . $on . '" href="' . $u($q) . '">' . e($label)
         . ' <span class="adm-tab-n">' . number_format($n) . '</span></a>';
};
?>
<h1 class="adm-h1">Messages</h1>
<p class="adm-sub">Enquiries sent through the Contact Us form. <strong><?= number_format($total) ?></strong> shown.</p>

<div class="adm-tabs">
  <?= $tab('', 'All', array_sum($counts)) ?>
  <?= $tab('new', 'New', $counts['new'] ?? 0) ?>
  <?= $tab('read', 'Read', $counts['read'] ?? 0) ?>
  <?= $tab('archived', 'Archived', $counts['archived'] ?? 0) ?>
</div>

<?php if (!$msgs): ?>
  <p class="adm-none">No messages<?= $status !== '' ? ' with this status' : '' ?> yet.</p>
<?php else: ?>
  <div class="adm-msgs">
    <?php foreach ($msgs as $m): ?>
      <article class="adm-msg<?= $m['status'] === 'new' ? ' is-new' : '' ?>">
        <header>
          <div class="adm-msg-who">
            <b><?= e($m['name']) ?></b>
            <a class="link" href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
            <?php if (!empty($m['phone'])): ?><span><?= e($m['phone']) ?></span><?php endif; ?>
          </div>
          <div class="adm-msg-meta">
            <span class="adm-pill adm-pill-<?= e($m['status']) ?>"><?= e($m['status']) ?></span>
            <time><?= e($m['created_at']) ?></time>
          </div>
        </header>
        <?php if (!empty($m['vehicle'])): ?>
          <p class="adm-msg-veh"><span>Vehicle / VIN</span><?= e($m['vehicle']) ?></p>
        <?php endif; ?>
        <p class="adm-msg-body"><?= nl2br(e($m['message'])) ?></p>
        <footer>
          <?php foreach (['new' => 'Mark new', 'read' => 'Mark read', 'archived' => 'Archive'] as $k => $lbl): ?>
            <?php if ($m['status'] !== $k): ?>
              <form method="post" action="<?= $u('/admin/messages/' . (int) $m['id'] . '/status') ?>">
                <input type="hidden" name="status" value="<?= e($k) ?>">
                <input type="hidden" name="back" value="<?= e($status) ?>">
                <button class="btn btn-sm" type="submit"><?= e($lbl) ?></button>
              </form>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="btn btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: your enquiry — Supreme Parts') ?>">Reply</a>
          <form method="post" action="<?= $u('/admin/messages/' . (int) $m['id'] . '/delete') ?>"
                onsubmit="return confirm('Delete this message permanently?')">
            <button class="btn btn-sm adm-danger" type="submit">Delete</button>
          </form>
        </footer>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pager" style="margin-top:20px">
      <?php $qs = $status !== '' ? '&status=' . urlencode($status) : ''; ?>
      <?php if ($page > 1): ?>
        <a class="btn btn-sm" href="<?= $u('/admin/messages?page=' . ($page - 1) . $qs) ?>">&larr; Previous</a>
      <?php endif; ?>
      <span class="pager-at">Page <?= (int) $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="btn btn-sm" href="<?= $u('/admin/messages?page=' . ($page + 1) . $qs) ?>">Next &rarr;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
