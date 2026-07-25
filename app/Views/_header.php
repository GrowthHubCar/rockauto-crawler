<?php
/* Shared BMW-M masthead — included by layout.php AND home.php so every page
   carries the identical header. Self-contained: resolves a URL helper from
   either $_controller (layout) or $base (home), and the cart count on its own. */
$u = $u ?? (isset($_controller)
        ? (fn(string $p) => e($_controller->url($p)))
        : (fn(string $p) => e(rtrim($base ?? '', '/') . '/' . ltrim($p, '/'))));
$cartN = $cartN ?? (class_exists('\App\Core\Cart') ? \App\Core\Cart::headerCount() : 0);
$q = $q ?? '';
?>
<div class="util"><div class="wrap">
  <span class="hidem usp"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M4 13a8 8 0 0 1 16 0M8 21a4 4 0 0 1-4-4v-3M20 14v3a4 4 0 0 1-4 4"/></svg>Need help finding a part?</span>
  <?php
    $hWa    = trim((string) (setting('contact_whatsapp', '') ?? ''));
    $hEmail = trim((string) (setting('contact_email', '') ?? ''));
  ?>
  <?php if ($hWa !== ''): ?>
    <a class="hidem" href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg><span class="accent"><?= e($hWa) ?></span></a>
  <?php endif; ?>
  <?php if ($hEmail !== ''): ?>
    <a class="hidem" href="mailto:<?= e($hEmail) ?>"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg><?= e($hEmail) ?></a>
  <?php endif; ?>
</div></div>

<header class="site-header" id="hdr">
  <div class="wrap header-inner">
    <a class="brand" href="<?= $u('/') ?>" aria-label="Supreme Motors Equipments Limited home">
      <img class="brand-logo" src="<?= $u('assets/img/site-logo.png') ?>" alt="Supreme Motors Equipments Limited">
    </a>

    <?php $navWa = whatsapp_link('Hi, I have a question about a part.'); ?>
    <nav class="mainnav" aria-label="Primary">
      <a href="<?= $u('/') ?>">Home</a>
      <a href="<?= $u('/products') ?>">Shop</a>
      <a href="<?= $u('/brands') ?>">Brands</a>
      <a href="<?= $u('/about') ?>">About Us</a>
      <a href="<?= $u('/contact') ?>">Contact Us</a>
    </nav>

    <div class="header-right">
      <form class="search" action="<?= $u('/products') ?>" method="get" role="search">
        <input type="search" name="q" placeholder="Search part, brand, or number&hellip;"
               value="<?= e($q) ?>" aria-label="Search parts">
        <button type="submit" aria-label="Search">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg>
        </button>
      </form>
      <a class="iconbtn cart" href="<?= $u('/cart') ?>" aria-label="Cart">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M6 6h15l-1.6 9H7.5z"/><circle cx="9.5" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6 5 3H2"/></svg>
        <span class="badge"<?= $cartN > 0 ? '' : ' hidden' ?>><?= (int) $cartN ?></span>
      </a>
      <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
</header>

<div class="drawer-ov" id="drawerOv"></div>
<aside class="drawer" id="drawer" aria-hidden="true">
  <div class="mstripe" aria-hidden="true"></div>
  <div class="dh">
    <span class="brand-word">Menu</span>
    <button class="iconbtn" id="drawerClose" aria-label="Close menu"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
  </div>
  <nav aria-label="Mobile">
    <a href="<?= $u('/') ?>">Home</a>
    <a href="<?= $u('/products') ?>">Shop</a>
    <a href="<?= $u('/brands') ?>">Brands</a>
    <a href="<?= $u('/about') ?>">About Us</a>
    <a href="<?= $u('/contact') ?>">Contact Us</a>
    <a href="<?= $u('/cart') ?>">Cart</a>
  </nav>
</aside>

<!-- Slide-out cart drawer (opens when a part is added to cart) -->
<div class="cart-dr-ov" id="cartDrOv"></div>
<aside class="cart-dr" id="cartDr" aria-hidden="true" aria-label="Shopping cart">
  <div class="cart-dr-hd">
    <b>Your Cart <span id="cdN"></span></b>
    <button class="cart-dr-x" id="cartDrX" aria-label="Close cart"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
  </div>
  <div class="cart-dr-body" id="cdBody"></div>
  <div class="cart-dr-ft" id="cdFt">
    <div class="cart-dr-sub"><span>Subtotal</span><b id="cdSub">&mdash;</b></div>
    <div class="cart-dr-actions">
      <a class="btn" href="<?= $u('/cart') ?>">View Cart</a>
      <a class="btn btn-accent" href="<?= $u('/checkout') ?>">Checkout</a>
    </div>
  </div>
</aside>

<script>
(function(){
  var h=document.getElementById('hdr');
  if(h){var f=function(){h.classList.toggle('stuck',window.scrollY>8)};f();window.addEventListener('scroll',f,{passive:true});}
  var b=document.getElementById('burger'),d=document.getElementById('drawer'),ov=document.getElementById('drawerOv'),c=document.getElementById('drawerClose');
  function open(){d.classList.add('show');ov.classList.add('show');b&&b.setAttribute('aria-expanded','true');d.setAttribute('aria-hidden','false');}
  function close(){d.classList.remove('show');ov.classList.remove('show');b&&b.setAttribute('aria-expanded','false');d.setAttribute('aria-hidden','true');}
  b&&b.addEventListener('click',open);c&&c.addEventListener('click',close);ov&&ov.addEventListener('click',close);

  /* ===== cart drawer ===== */
  var CD_DATA='<?= $u('/cart/data') ?>';
  var CD_UP=CD_DATA.replace('/cart/data','/cart/update'), CD_RM=CD_DATA.replace('/cart/data','/cart/remove');
  var CD_PART=CD_DATA.replace('/cart/data','/part/');
  var cd=document.getElementById('cartDr'), cdOv=document.getElementById('cartDrOv'),
      cdX=document.getElementById('cartDrX'), cdBody=document.getElementById('cdBody'),
      cdN=document.getElementById('cdN'), cdSub=document.getElementById('cdSub'),
      cdFt=document.getElementById('cdFt');
  var badge=document.querySelector('.iconbtn.cart .badge');
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(x){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]})}
  function cdOpen(){cd.classList.add('show');cdOv.classList.add('show');cd.setAttribute('aria-hidden','false')}
  function cdClose(){cd.classList.remove('show');cdOv.classList.remove('show');cd.setAttribute('aria-hidden','true')}
  cdX&&cdX.addEventListener('click',cdClose); cdOv&&cdOv.addEventListener('click',cdClose);
  document.addEventListener('keydown',function(e){if(e.key==='Escape')cdClose()});

  function render(data){
    if(!data||!data.ok)return;
    cdN.textContent=data.count?'('+data.count+')':'';
    if(badge){ badge.textContent=data.count; badge.hidden=!data.count; }
    if(data.empty){
      cdBody.innerHTML='<p class="cart-dr-empty">Your cart is empty.</p>';
      cdFt.hidden=true; return;
    }
    cdFt.hidden=false; cdSub.textContent=data.subtotal;
    cdBody.innerHTML=data.items.map(function(it){
      return '<div class="cd-line" data-pid="'+it.part_id+'" data-vid="'+it.variant_id+'" data-qty="'+it.qty+'">'+
        '<a class="cd-im" href="'+CD_PART+encodeURIComponent(it.slug)+'">'+
          (it.img?'<img src="'+esc(it.img)+'" alt="" loading="lazy" onerror="this.style.opacity=0">':'')+'</a>'+
        '<div class="cd-info"><a class="cd-nm" href="'+CD_PART+encodeURIComponent(it.slug)+'">'+esc(it.name)+'</a>'+
          '<span class="cd-meta">'+esc(it.unit_price)+' each</span>'+
          '<span class="cd-qty"><button type="button" class="cd-q" data-d="-1" aria-label="Decrease">&minus;</button>'+
            '<b>'+it.qty+'</b>'+
            '<button type="button" class="cd-q" data-d="1" aria-label="Increase">+</button></span>'+
        '</div>'+
        '<div class="cd-right"><span class="cd-pr">'+esc(it.line_total)+'</span>'+
          '<button type="button" class="cd-rm" aria-label="Remove">'+
            '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg></button>'+
        '</div></div>';
    }).join('');
  }
  function cdFetch(){return fetch(CD_DATA,{headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(render)}
  /* update/remove return a mutation summary (no items list), so re-fetch the full
     cart afterwards to re-render the drawer lines correctly */
  function cdPost(url,body){
    return fetch(url,{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams(body)})
      .then(function(){ return cdFetch(); });
  }
  // qty +/- and remove, delegated on the drawer body
  cdBody.addEventListener('click',function(e){
    var line=e.target.closest('.cd-line'); if(!line)return;
    var pid=line.dataset.pid, vid=line.dataset.vid, qty=parseInt(line.dataset.qty,10)||1;
    if(e.target.closest('.cd-rm')){
      line.style.opacity=.5;
      cdPost(CD_RM,{part_id:pid,variant_id:vid});
    } else {
      var q=e.target.closest('.cd-q'); if(!q)return;
      var nq=Math.max(1, qty+parseInt(q.dataset.d,10));   // min 1; use the trash to remove
      line.style.opacity=.5;
      cdPost(CD_UP,{part_id:pid,variant_id:vid,qty:nq});
    }
  });

  // add-to-cart forms anywhere -> AJAX add, then open the drawer
  document.addEventListener('submit',function(e){
    var f=e.target;
    if(!f.matches||!f.matches('form[action$="/cart/add"]'))return;
    e.preventDefault();
    fetch(f.action,{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(f)})
      .then(function(r){return r.json()}).then(function(d){render(d);cdOpen()})
      .catch(function(){f.submit()});
  });
  // header cart icon -> open the drawer (fallback link stays /cart)
  var cartLink=document.querySelector('.iconbtn.cart');
  cartLink&&cartLink.addEventListener('click',function(e){e.preventDefault();cdFetch().then(cdOpen)});
})();
</script>
