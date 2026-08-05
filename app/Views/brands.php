<?php /** @var array $brands @var \App\Core\Controller $_controller */
$url = fn(string $p) => e($_controller->url($p));
?>
<h1 class="page-title">Brands</h1>
<p class="subtitle">Every manufacturer we stock. Pick one to see its parts.</p>

<?php if (!$brands): ?>
  <p class="empty">No brands available yet.</p>
<?php else: ?>
  <?php /* Filtering is client-side on purpose: the whole brand list is already in the
           DOM, so there is nothing to fetch and no reason to make the user wait. */ ?>
  <div class="brandfilter">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg>
    <input type="search" id="brandq" autocomplete="off"
           placeholder="Search <?= count($brands) ?> brands" aria-label="Search brands"
           aria-controls="brandgrid">
    <button type="button" id="brandqx" aria-label="Clear search" hidden>&times;</button>
  </div>
  <p class="brandcount" id="brandcount" role="status" aria-live="polite"></p>

  <div class="brandgrid" id="brandgrid">
    <?php foreach ($brands as $b): ?>
      <a class="brandcard" href="<?= $url('/products?brand=' . urlencode($b['slug'])) ?>"
         data-name="<?= e(mb_strtolower($b['name'])) ?>">
        <span class="brandcard-name"><?= e($b['name']) ?></span>
        <span class="brandcard-n"><?= number_format((int) $b['n']) ?> parts</span>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="empty" id="brandnone" hidden>No brand matches that search.</p>

  <script>
  (function () {
    var q = document.getElementById('brandq'),
        x = document.getElementById('brandqx'),
        grid = document.getElementById('brandgrid'),
        none = document.getElementById('brandnone'),
        count = document.getElementById('brandcount'),
        cards = [].slice.call(grid.querySelectorAll('.brandcard')),
        total = cards.length;
    function apply() {
      var s = q.value.trim().toLowerCase(), shown = 0;
      for (var i = 0; i < cards.length; i++) {
        var hit = !s || cards[i].dataset.name.indexOf(s) > -1;
        cards[i].hidden = !hit;
        if (hit) shown++;
      }
      none.hidden = shown !== 0;
      x.hidden = !s;
      count.textContent = s ? (shown + ' of ' + total + ' brands') : '';
    }
    q.addEventListener('input', apply);
    q.addEventListener('keydown', function (e) { if (e.key === 'Escape') { q.value = ''; apply(); } });
    x.addEventListener('click', function () { q.value = ''; apply(); q.focus(); });
  })();
  </script>
<?php endif; ?>
