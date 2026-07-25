<?php /** @var array $part @var array $images @var array $attrs @var int $fitCount @var int $stock @var array $variants @var \App\Core\Controller $_controller */
$url = fn(string $p) => e($_controller->url($p));

// pick the default variant (its price wins over the headline once chosen)
$default = null;
foreach ($variants as $v) { if ($v['is_default'] && !$v['oos']) { $default = $v; break; } }
if (!$default) { foreach ($variants as $v) { if (!$v['oos'] && $v['price'] !== null) { $default = $v; break; } } }
$shownPrice = $default ? $default['price'] : $part['price'];
$shownCore  = $default ? $default['core_charge'] : $part['core_charge'];
$buyable    = $shownPrice !== null;

$gallery = $images ?: ($part['primary_image_path'] ? [['path' => $part['primary_image_path'], 'alt' => $part['name']]] : []);
$mainImg = $gallery[0]['path'] ?? '';
?>
<div class="pd2">
  <!-- Gallery: draggable image slider with arrows + thumbnail sync -->
  <div class="pd2-gallery">
    <div class="pg-main" id="pgMain">
      <div class="pg-track" id="pgTrack">
        <?php foreach ($gallery as $img): ?>
          <div class="pg-slide"><img src="<?= e(img_url($img['path'])) ?>" alt="<?= e($part['name']) ?>" loading="lazy" onerror="this.style.opacity=0"></div>
        <?php endforeach; ?>
      </div>
      <?php if (count($gallery) > 1): ?>
        <button class="pg-arrow prev" id="pgPrev" type="button" aria-label="Previous image"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></button>
        <button class="pg-arrow next" id="pgNext" type="button" aria-label="Next image"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button>
      <?php endif; ?>
    </div>
    <?php if (count($gallery) > 1): ?>
      <div class="pg-thumbs" id="pgThumbs">
        <?php foreach ($gallery as $i => $img): ?>
          <button type="button" class="pg-thumb<?= $i === 0 ? ' on' : '' ?>" data-i="<?= $i ?>" aria-label="Image <?= $i + 1 ?>">
            <img src="<?= e(img_url($img['path'])) ?>" alt="<?= e($img['alt'] ?? '') ?>" loading="lazy" onerror="this.style.opacity=0">
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Info -->
  <div class="pd2-info">
    <?php if (!empty($part['brand'])): ?>
      <a class="pd2-brand" href="<?= $url('/products?brand=' . urlencode($part['brand_slug'] ?? '')) ?>"><?= e($part['brand']) ?></a>
    <?php endif; ?>
    <h1 class="pd2-name"><?= e($part['name']) ?></h1>

    <div class="pd2-price" id="pdPrice" data-core="<?= e((string) $shownCore) ?>">
      <?= price_tag($shownPrice, (int) $part['category_id']) ?>
      <?php if ((float) $shownCore > 0): ?><span class="core">+<?= money($shownCore) ?> core</span><?php endif; ?>
    </div>
    <div class="pd2-pn">Part #<b><?= e($part['part_number']) ?></b> &middot; SKU <?= e($part['sku']) ?>
      &middot; <span class="pd2-stock <?= $stock > 0 ? 'in' : 'out' ?>"><?= $stock > 0 ? 'In stock' : 'Backordered' ?></span></div>

    <?php if (!empty($part['description'])): ?>
      <div class="pd2-acc" id="pdAcc">
        <button class="pd2-acc-h" type="button" aria-expanded="false">Description <span class="chev"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span></button>
        <div class="pd2-acc-c"><div class="pd2-acc-inner"><?= nl2br(e($part['description'])) ?></div></div>
      </div>
    <?php endif; ?>

    <form class="pd2-buy" method="post" action="<?= $url('/cart/add') ?>">
      <input type="hidden" name="sku" value="<?= e($part['sku']) ?>">
      <?php if (count($variants) > 1): ?>
        <label class="pd2-choose">Choose type
          <select name="variant_id" id="pdVariant">
            <?php foreach ($variants as $v): ?>
              <option value="<?= (int) $v['id'] ?>"
                      data-price="<?= $v['price'] === null ? '' : e((string) sell_price($v['price'], (int) $part['category_id'])) ?>"
                      data-core="<?= e((string) $v['core_charge']) ?>"
                      <?= $v['oos'] ? 'disabled' : '' ?>
                      <?= ($default && $v['id'] === $default['id']) ? 'selected' : '' ?>>
                <?= e($v['type']) ?> <?= $v['price'] === null ? '(Out of Stock)' : '(' . price_tag($v['price'], (int) $part['category_id']) . ')' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>

      <div class="pd2-actions">
        <div class="pd2-qty" role="group" aria-label="Quantity">
          <button type="button" class="qbtn" data-step="-1" aria-label="Decrease">&minus;</button>
          <input type="text" name="qty" id="pdQty" value="1" inputmode="numeric" aria-label="Quantity">
          <button type="button" class="qbtn" data-step="1" aria-label="Increase">+</button>
        </div>
        <?php if ($buyable): ?>
          <button class="btn" type="submit">Add to Cart</button>
          <button class="btn btn-accent" type="button" id="pdBuyNow">Buy Now</button>
        <?php else: ?>
          <button class="btn" type="button" disabled>Out of Stock</button>
        <?php endif; ?>
      </div>
    </form>

    <div class="pd2-delivery">
      <div class="pd2-do">
        <span class="pd2-do-ic"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="1.6"/><circle cx="17.5" cy="17" r="1.6"/></svg></span>
        <span><b>Delivery</b>2&ndash;4 business days</span>
      </div>
      <div class="pd2-do">
        <span class="pd2-do-ic"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
        <span><b>Payment</b>Secure card checkout</span>
      </div>
      <div class="pd2-do">
        <span class="pd2-do-ic"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M3 7v6a9 9 0 0 0 9 8 9 9 0 0 0 9-8V7l-9-4z"/><path d="M9 12l2 2 4-4"/></svg></span>
        <span><b>Fitment</b>Guaranteed to fit</span>
      </div>
      <div class="pd2-do">
        <span class="pd2-do-ic"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg></span>
        <span><b>Returns</b>90-day easy returns</span>
      </div>
    </div>
  </div>
</div>

<!-- Tabbed: Specifications | Fits These Vehicles -->
<section class="pd2-tabs" data-sku="<?= e($part['sku']) ?>">
  <div class="pd2-tabbar" role="tablist">
    <button class="pd2-tab on" role="tab" aria-selected="true" data-tab="specs">Specifications</button>
    <button class="pd2-tab" role="tab" aria-selected="false" data-tab="fits">Fits These Vehicles <span class="pd2-tab-n"><?= number_format($fitCount) ?></span></button>
  </div>

  <div class="pd2-panel on" data-panel="specs">
    <?php if ($attrs): ?>
      <table class="pd2-spec">
        <?php foreach ($attrs as $a): ?>
          <tr><th><?= e($a['name']) ?></th><td><?= e($a['value']) ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p class="empty">No specifications listed for this part.</p>
    <?php endif; ?>
  </div>

  <div class="pd2-panel" data-panel="fits" hidden>
    <?php if ($fitCount === 0): ?>
      <p class="empty">No fitment data for this part yet.</p>
    <?php else: ?>
      <div class="pd2-fits" id="pdFits" aria-live="polite"><p class="pd2-loading">Loading vehicles&hellip;</p></div>
      <div class="pd2-fits-pager" id="pdFitsPager" hidden>
        <button class="btn btn-sm" type="button" id="fitPrev">&larr; Previous</button>
        <span class="pager-at" id="fitAt">Page 1</span>
        <button class="btn btn-sm" type="button" id="fitNext">Next &rarr;</button>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
(function(){
  var BASE = <?= json_encode(rtrim($_controller->url('/'), '/')) ?>;
  var SKU  = <?= json_encode($part['sku']) ?>;
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}

  /* gallery slider: prev/next arrows + drag on the main image, thumbnails jump/sync */
  var track = document.getElementById('pgTrack');
  if (track && track.children.length) {
    var main = document.getElementById('pgMain'), thumbs = document.getElementById('pgThumbs');
    var prev = document.getElementById('pgPrev'), next = document.getElementById('pgNext');
    var n = track.children.length, idx = 0, moved = false;
    function go(i){
      idx = Math.max(0, Math.min(n - 1, i));
      track.style.transition = 'transform .35s cubic-bezier(.22,1,.36,1)';
      track.style.transform = 'translateX(' + (-idx * 100) + '%)';
      if (thumbs) {
        [].forEach.call(thumbs.children, function(t, k){ t.classList.toggle('on', k === idx); });
        // keep the active thumb visible by scrolling the STRIP only (scrollIntoView
        // would scroll the whole page down to the thumbnails)
        var t = thumbs.children[idx];
        if (t) {
          var tl = t.offsetLeft, tr = tl + t.offsetWidth;
          if (tl < thumbs.scrollLeft) thumbs.scrollLeft = tl - 8;
          else if (tr > thumbs.scrollLeft + thumbs.clientWidth) thumbs.scrollLeft = tr - thumbs.clientWidth + 8;
        }
      }
      // wrap-around at the ends so the arrows are never disabled/stuck
    }
    prev && prev.addEventListener('click', function(){ go(idx <= 0 ? n - 1 : idx - 1); });
    next && next.addEventListener('click', function(){ go(idx >= n - 1 ? 0 : idx + 1); });

    // drag the main image between slides. Skip when the pointer starts on an arrow
    // so arrow clicks aren't swallowed by the drag handler.
    var down = false, sx = 0, dx = 0, cap = false;
    main.addEventListener('pointerdown', function(e){
      if (e.target.closest('.pg-arrow')) return;
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      down = true; moved = false; cap = false; sx = e.clientX; dx = 0; track.style.transition = 'none';
    });
    main.addEventListener('pointermove', function(e){
      if (!down) return; dx = e.clientX - sx;
      if (Math.abs(dx) > 4) { moved = true; if (!cap) { cap = true; try { main.setPointerCapture(e.pointerId); } catch(_){} } }
      if (moved) track.style.transform = 'translateX(calc(' + (-idx * 100) + '% + ' + dx + 'px))';
    });
    function end(){ if (!down) return; down = false; var w = main.clientWidth;
      // low threshold (8% of width, min 40px) so a small flick changes the slide
      if (moved) { var th = Math.min(w * 0.08, 40);
        if (dx < -th) go(idx >= n - 1 ? 0 : idx + 1);
        else if (dx > th) go(idx <= 0 ? n - 1 : idx - 1);
        else go(idx); }
      dx = 0; }
    main.addEventListener('pointerup', end); main.addEventListener('pointercancel', end); main.addEventListener('pointerleave', end);

    // thumbnails: click to jump, drag the strip to scroll
    if (thumbs) {
      var tdown = false, tsx = 0, tsl = 0, tmoved = 0;
      thumbs.addEventListener('pointerdown', function(e){ if (e.pointerType==='mouse' && e.button!==0) return; tdown=true; tmoved=0; tsx=e.clientX; tsl=thumbs.scrollLeft; });
      thumbs.addEventListener('pointermove', function(e){ if(!tdown)return; var d=e.clientX-tsx; if(Math.abs(d)>3){tmoved=Math.abs(d); thumbs.classList.add('drag'); thumbs.scrollLeft=tsl-d; e.preventDefault();} });
      function tup(){ tdown=false; thumbs.classList.remove('drag'); }
      thumbs.addEventListener('pointerup', tup); thumbs.addEventListener('pointercancel', tup); thumbs.addEventListener('pointerleave', tup);
      thumbs.addEventListener('click', function(e){ if (tmoved>5){ e.preventDefault(); e.stopPropagation(); tmoved=0; return; } var b=e.target.closest('.pg-thumb'); if(b) go(+b.dataset.i); });
    }
    go(0);
  }

  /* description accordion: closed by default, slides open/closed */
  var acc = document.getElementById('pdAcc');
  if (acc) {
    var ah = acc.querySelector('.pd2-acc-h'), ac = acc.querySelector('.pd2-acc-c');
    ah.addEventListener('click', function(){
      var open = !acc.classList.contains('open');
      ah.setAttribute('aria-expanded', String(open));
      if (open) {
        acc.classList.add('open');
        ac.style.maxHeight = 'none';        // measure full height...
        var h = ac.offsetHeight;
        ac.style.maxHeight = '0px'; ac.offsetHeight;   // ...reset + force reflow
        ac.style.maxHeight = h + 'px';                 // ...then animate to it
      } else {
        ac.style.maxHeight = ac.offsetHeight + 'px'; ac.offsetHeight;
        acc.classList.remove('open');
        ac.style.maxHeight = '0px';
      }
    });
    // once open, drop the fixed height so the panel can reflow freely
    ac.addEventListener('transitionend', function(){ if (acc.classList.contains('open')) ac.style.maxHeight = 'none'; });
  }

  /* qty stepper */
  var qty = document.getElementById('pdQty');
  document.querySelectorAll('.pd2-qty .qbtn').forEach(function(b){
    b.addEventListener('click', function(){
      var n = Math.max(1, (parseInt(qty.value,10)||1) + parseInt(b.dataset.step,10));
      qty.value = n;
    });
  });
  qty && qty.addEventListener('input', function(){ this.value = this.value.replace(/[^0-9]/g,''); });

  /* variant -> price */
  var sel = document.getElementById('pdVariant'), box = document.getElementById('pdPrice');
  if (sel && box) sel.addEventListener('change', function(){
    var o = sel.options[sel.selectedIndex], p = o.getAttribute('data-price'), c = parseFloat(o.getAttribute('data-core')||'0');
    var html = (p===''||p===null) ? 'Out of Stock' : '$' + Number(p).toFixed(2);
    if (p!=='' && c>0) html += ' <span class="core">+$' + c.toFixed(2) + ' core</span>';
    box.innerHTML = html;
  });

  /* Buy Now: add to cart then go straight to checkout */
  var buy = document.getElementById('pdBuyNow'), form = document.querySelector('.pd2-buy');
  buy && buy.addEventListener('click', function(){
    fetch(form.action, {method:'POST', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, body:new FormData(form)})
      .then(function(){ location.href = BASE + '/checkout'; })
      .catch(function(){ form.submit(); });
  });

  /* tabs + lazy paginated fitment */
  var tabs = document.querySelector('.pd2-tabs');
  var loaded = false, page = 1;
  tabs.querySelectorAll('.pd2-tab').forEach(function(t){
    t.addEventListener('click', function(){
      tabs.querySelectorAll('.pd2-tab').forEach(function(x){ x.classList.remove('on'); x.setAttribute('aria-selected','false'); });
      t.classList.add('on'); t.setAttribute('aria-selected','true');
      tabs.querySelectorAll('.pd2-panel').forEach(function(p){ p.classList.remove('on'); p.hidden = true; });
      var panel = tabs.querySelector('[data-panel="'+t.dataset.tab+'"]');
      panel.classList.add('on'); panel.hidden = false;
      if (t.dataset.tab === 'fits' && !loaded) { loaded = true; loadFits(1); }
    });
  });
  function loadFits(p){
    var box = document.getElementById('pdFits'); if(!box) return;
    box.innerHTML = '<p class="pd2-loading">Loading vehicles…</p>';
    fetch(BASE + '/part/' + encodeURIComponent(SKU) + '/fitment?page=' + p, {headers:{'Accept':'application/json'}})
      .then(function(r){ return r.json(); }).then(function(d){
        if (!d.ok || !d.rows.length) { box.innerHTML = '<p class="empty">No vehicles on this page.</p>'; return; }
        box.innerHTML = '<div class="fit-wrap"><table class="fit-table"><thead><tr>' +
          '<th>Year</th><th>Make</th><th>Model</th><th>Engine</th><th></th></tr></thead><tbody>' +
          d.rows.map(function(f){
            return '<tr><td class="fit-yr">'+f.year+'</td><td class="fit-mk">'+esc(f.make)+'</td>'+
                   '<td>'+esc(f.model)+'</td><td class="fit-en">'+esc(f.engine || '&mdash;')+'</td>'+
                   '<td class="fit-go"><a href="'+esc(f.url)+'">Shop parts<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></td></tr>';
          }).join('') + '</tbody></table></div>';
        page = d.page;
        var pager = document.getElementById('pdFitsPager');
        pager.hidden = false;
        document.getElementById('fitAt').textContent = 'Page ' + d.page;
        document.getElementById('fitPrev').disabled = d.page <= 1;
        document.getElementById('fitNext').disabled = !d.more;
        window.scrollTo({top: tabs.offsetTop - 20, behavior:'smooth'});
      });
  }
  var prev = document.getElementById('fitPrev'), next = document.getElementById('fitNext');
  prev && prev.addEventListener('click', function(){ if(page>1) loadFits(page-1); });
  next && next.addEventListener('click', function(){ loadFits(page+1); });
})();
</script>
