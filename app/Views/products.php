<?php /** @var array $products @var int $page @var bool $more @var string $veh @var string $cat @var string $filter @var int $liveCount @var \App\Core\Controller $_controller */
$url = fn(string $p) => e($_controller->url($p));
/* keep any active drill filter across pagination */
$pageUrl = function (int $pg) use ($_controller, $veh, $cat, $brand) {
    $q = ['page' => $pg];
    if ($veh !== '')   { $q['vehicle'] = $veh; }
    if ($cat !== '')   { $q['category'] = $cat; }
    if ($brand !== '') { $q['brand'] = $brand; }
    return e($_controller->url('/products?' . http_build_query($q)));
};
$levels = [
    ['k' => 'make',   'label' => 'Make',             'ph' => 'All makes'],
    ['k' => 'model',  'label' => 'Model',            'ph' => 'Select make first'],
    ['k' => 'year',   'label' => 'Year',             'ph' => 'Select model first'],
    ['k' => 'engine', 'label' => 'Engine / Trim',    'ph' => 'Select year first'],
    ['k' => 'cat',    'label' => 'Part Category',    'ph' => 'Select engine first'],
    ['k' => 'sub',    'label' => 'Part Subcategory', 'ph' => 'Select category first'],
];
?>
<?php /* The hero is rendered full-bleed by layout.php (before <main>), not here. */ ?>

<!-- Drill-down: same Make > Model > Year > Engine > Category > Subcategory order as
     the homepage catalog. Searchable comboboxes; each change refetches over AJAX. -->
<div class="drill" id="drill">
  <div class="drill-row">
    <?php foreach ($levels as $i => $lv): ?>
      <div class="ss" id="ss-<?= e($lv['k']) ?>" data-k="<?= e($lv['k']) ?>">
        <span class="ss-lab" id="lab-<?= e($lv['k']) ?>"><?= e($lv['label']) ?></span>
        <button class="ss-btn" type="button" role="combobox" aria-haspopup="listbox"
                aria-expanded="false" aria-labelledby="lab-<?= e($lv['k']) ?>"
                <?= $i > 0 ? 'disabled' : '' ?>>
          <span class="ss-val"><?= e($lv['ph']) ?></span>
          <span class="ss-cv"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span>
        </button>
        <div class="ss-pop" hidden>
          <div class="ss-search"><input type="text" placeholder="Search&hellip;" aria-label="Search <?= e($lv['label']) ?>"></div>
          <ul class="ss-list" role="listbox" aria-label="<?= e($lv['label']) ?>"></ul>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="drill-search">
    <label class="ss-lab" for="qBox">Search Parts</label>
    <div class="qwrap">
      <svg class="qic" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
      <input type="search" id="qBox" value="<?= e($q ?? '') ?>" autocomplete="off"
             placeholder="Part name, brand or number&hellip;">
      <button type="button" class="qclear" id="qClear" aria-label="Clear search"<?= ($q ?? '') === '' ? ' hidden' : '' ?>>&times;</button>
    </div>
  </div>
  <div class="drill-foot">
    <span class="drill-state" id="dState">Showing every part we stock.</span>
    <button class="btn btn-sm" type="button" id="dClear" hidden>Clear Selection</button>
  </div>
</div>

<div id="pResults">
<?php if (!$products): ?>
  <p class="empty">No products match this selection.</p>
<?php else: ?>
  <div class="pgrid">
    <?php foreach ($products as $p): ?>
      <a class="pcard" href="<?= $url('/part/' . $p['slug']) ?>">
        <span class="pcard-im">
          <img src="<?= e(img_url($p['primary_image_path'])) ?>" alt="<?= e($p['name']) ?>"
               loading="lazy" onerror="this.style.opacity=0">
        </span>
        <span class="pcard-bd">
          <span class="pcard-title"><?= e($p['name']) ?></span>
          <span class="pcard-price"><?= price_tag($p['price'], (int) ($p['category_id'] ?? 0)) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="pager">
    <?php if ($page > 1): ?>
      <a class="btn btn-sm" href="<?= $pageUrl($page - 1) ?>">&larr; Previous</a>
    <?php endif; ?>
    <span class="pager-at">Page <?= (int) $page ?></span>
    <?php if ($more): ?>
      <a class="btn btn-sm" href="<?= $pageUrl($page + 1) ?>">Next &rarr;</a>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>

<script>
(function(){
  var BASE=<?= json_encode(rtrim($_controller->url('/'), '/')) ?>;
  var results=document.getElementById('pResults'),
      state=document.getElementById('dState'),
      clear=document.getElementById('dClear'),
      sub=document.querySelector('.phero-t p:not(.eyebrow)'),
      heroH1=document.querySelector('.phero h1');
  var order=['make','model','year','engine','cat','sub'];
  var PH=['All makes','Select make first','Select model first','Select year first',
          'Select engine first','Select category first'];
  var S={},T={},page=1;
  var PRESET=<?= json_encode($preset ?? []) ?>;
  var qBox=document.getElementById('qBox'),qClear=document.getElementById('qClear');
  var Q=qBox?qBox.value.trim():'';
  order.forEach(function(k){S[k]='';T[k]=''});

  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function api(p){return fetch(BASE+'/api'+p,{headers:{'Accept':'application/json'}})
    .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})}

  /* Searchable dropdown. Native <select> can't filter, and Make alone is 300+
     options — typing to narrow is the whole point. */
  function SearchSelect(root,onPick){
    var btn=root.querySelector('.ss-btn'), pop=root.querySelector('.ss-pop'),
        val=root.querySelector('.ss-val'), q=pop.querySelector('input'),
        list=pop.querySelector('.ss-list');
    var items=[],ph='',cur='',mark=-1;

    function render(){
      var f=q.value.trim().toLowerCase();
      var vis=items.filter(function(it){return !f||it.t.toLowerCase().indexOf(f)>-1});
      if(!vis.length){list.innerHTML='<li class="ss-none">No matches</li>';mark=-1;return}
      list.innerHTML=vis.map(function(it){
        return '<li class="ss-opt'+(it.v===cur?' on':'')+'" role="option" data-v="'+esc(it.v)+'"'+
               ' aria-selected="'+(it.v===cur?'true':'false')+'">'+
               '<span>'+esc(it.t)+'</span>'+(it.n!=null?'<span class="n">'+esc(it.n)+'</span>':'')+'</li>';
      }).join('');
      mark=-1;
    }
    function open(){
      if(btn.disabled)return;
      closeAll(root);
      root.classList.add('open'); pop.hidden=false; btn.setAttribute('aria-expanded','true');
      q.value=''; render(); q.focus();
    }
    function close(){
      root.classList.remove('open'); pop.hidden=true; btn.setAttribute('aria-expanded','false');
    }
    function choose(v,t){
      cur=v; val.textContent=t||ph; close(); btn.focus(); onPick(v,t||'');
    }
    function moveMark(d){
      var opts=list.querySelectorAll('.ss-opt'); if(!opts.length)return;
      if(mark>-1)opts[mark].classList.remove('mark');
      mark=(mark+d+opts.length)%opts.length;
      opts[mark].classList.add('mark');
      opts[mark].scrollIntoView({block:'nearest'});
    }

    btn.addEventListener('click',function(){root.classList.contains('open')?close():open()});
    list.addEventListener('click',function(e){
      var li=e.target.closest('.ss-opt'); if(!li)return;
      choose(li.dataset.v,li.querySelector('span').textContent);
    });
    q.addEventListener('input',render);
    q.addEventListener('keydown',function(e){
      if(e.key==='ArrowDown'){e.preventDefault();moveMark(1)}
      else if(e.key==='ArrowUp'){e.preventDefault();moveMark(-1)}
      else if(e.key==='Enter'){
        e.preventDefault();
        var opts=list.querySelectorAll('.ss-opt'); if(!opts.length)return;
        var li=opts[mark>-1?mark:0];
        choose(li.dataset.v,li.querySelector('span').textContent);
      } else if(e.key==='Escape'){e.preventDefault();close();btn.focus()}
    });
    btn.addEventListener('keydown',function(e){
      if(e.key==='ArrowDown'||e.key==='Enter'||e.key===' '){e.preventDefault();open()}
    });

    return {
      root:root,
      set:function(list_,placeholder){
        items=list_||[]; ph=placeholder; cur='';
        val.textContent=items.length?placeholder:'None found';
        btn.disabled=!items.length; render();
      },
      reset:function(placeholder){
        items=[]; ph=placeholder; cur=''; val.textContent=placeholder;
        btn.disabled=true; list.innerHTML=''; close();
      },
      /* set the value + label without firing onPick — used to hydrate the drill
         from the URL, where the grid is already filtered server-side */
      select:function(v,t){ cur=v; val.textContent=t||ph; btn.disabled=false; },
      close:close
    };
  }
  var SS={};
  function closeAll(except){order.forEach(function(k){if(SS[k]&&SS[k].root!==except)SS[k].close()})}
  document.addEventListener('click',function(e){ if(!e.target.closest('.ss'))closeAll(null) });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape')closeAll(null) });

  function resetFrom(i){
    for(var j=i;j<order.length;j++){ var k=order[j]; S[k]='';T[k]=''; SS[k].reset(PH[j]) }
  }

  function card(p){
    return '<a class="pcard" href="'+BASE+'/part/'+encodeURIComponent(p.slug)+'">'+
      '<span class="pcard-im"><img src="'+esc(p.img)+'" alt="'+esc(p.name)+'" loading="lazy" onerror="this.style.opacity=0"></span>'+
      '<span class="pcard-bd"><span class="pcard-title">'+esc(p.name)+'</span>'+
      '<span class="pcard-price">'+esc(p.price)+'</span></span></a>';
  }
  function pager(more,pg){
    var h='<div class="pager">';
    if(pg>1)h+='<button class="btn btn-sm" type="button" data-pg="'+(pg-1)+'">&larr; Previous</button>';
    h+='<span class="pager-at">Page '+pg+'</span>';
    if(more)h+='<button class="btn btn-sm" type="button" data-pg="'+(pg+1)+'">Next &rarr;</button>';
    return h+'</div>';
  }
  function query(){
    var q=[];
    if(Q)q.push('q='+encodeURIComponent(Q));
    if(S.engine)q.push('vehicle='+encodeURIComponent(S.engine));
    var c=S.sub||S.cat; if(c)q.push('category='+encodeURIComponent(c));
    q.push('page='+page);
    return q.join('&');
  }
  function label(){
    var bits=[];
    if(Q)bits.push('“'+Q+'”');
    if(S.engine)bits.push([T.make,T.model,T.year].filter(Boolean).join(' ')+(T.engine?' '+T.engine:''));
    var c=T.sub||T.cat; if(c)bits.push(c);
    return bits.join(' — ');
  }
  function load(){
    var active=!!(S.engine||S.cat||S.sub||Q);
    clear.hidden=!active;
    results.setAttribute('aria-busy','true'); results.style.opacity=.35;
    state.textContent='Loading…';
    api('/products?'+query()).then(function(d){
      var rows=d.rows||[];
      results.innerHTML=rows.length
        ? '<div class="pgrid">'+rows.map(card).join('')+'</div>'+pager(d.more,d.page)
        : '<p class="empty">No products match this selection.</p>';
      results.style.opacity=1; results.removeAttribute('aria-busy');
      var l=label();
      heroH1.innerHTML=esc(l||'Browse Our Range of Parts')+'<span class="dot">.</span>';
      state.textContent=active
        ? (rows.length?'Filtered by '+l:'Nothing matches '+l)
        : 'Showing every part we stock.';
      results.querySelectorAll('[data-pg]').forEach(function(b){
        b.addEventListener('click',function(){page=+b.dataset.pg;load();
          document.getElementById('drill').scrollIntoView({behavior:'smooth',block:'start'})});
      });
    }).catch(function(){
      results.style.opacity=1; results.removeAttribute('aria-busy');
      state.textContent='Could not load parts. Try again.';
    });
  }

  // wire each level: pick -> load the next level's options -> refresh the grid
  var next={
    make:  function(){return api('/models?make='+encodeURIComponent(S.make))
             .then(function(d){SS.model.set(d.map(function(x){return {v:x.slug,t:x.name}}),'All models')})},
    model: function(){return api('/tree/years?make='+encodeURIComponent(S.make)+'&model='+encodeURIComponent(S.model))
             .then(function(d){SS.year.set(d.map(function(x){return {v:String(x),t:String(x)}}),'All years')})},
    year:  function(){return api('/vehicles?year='+encodeURIComponent(S.year)+'&make='+encodeURIComponent(S.make)+'&model='+encodeURIComponent(S.model))
             .then(function(d){SS.engine.set(d.map(function(x){return {v:x.slug,t:x.label}}),'All engines')})},
    engine:function(){return api('/tree/groups?vehicle='+encodeURIComponent(S.engine))
             .then(function(d){SS.cat.set(d.map(function(x){return {v:x.slug,t:x.name,n:x.n}}),'All categories')})},
    cat:   function(){return api('/tree/categories?vehicle='+encodeURIComponent(S.engine)+'&group='+encodeURIComponent(S.cat))
             .then(function(d){SS.sub.set(d.map(function(x){return {v:x.slug,t:x.name,n:x.n}}),'All subcategories')})},
    sub:   function(){return Promise.resolve()}
  };
  order.forEach(function(k,i){
    SS[k]=SearchSelect(document.getElementById('ss-'+k),function(v,t){
      S[k]=v; T[k]=t; resetFrom(i+1); page=1;
      if(v&&next[k])next[k]();
      load();
    });
  });

  clear.addEventListener('click',function(){
    resetFrom(0); page=1;
    Q=''; if(qBox)qBox.value=''; if(qClear)qClear.hidden=true;
    var u=new URL(location.href); u.searchParams.delete('q'); history.replaceState(null,'',u);
    api('/tree/makes').then(function(d){
      SS.make.set(d.map(function(x){return {v:x.slug,t:x.name,n:x.n}}),'All makes')});
    load();
  });

  if(qBox){
    var tmr=null;
    function runSearch(){
      Q=qBox.value.trim(); page=1;
      if(qClear)qClear.hidden=!Q;
      load();
      // keep the URL shareable/back-button friendly without reloading
      var u=new URL(location.href);
      Q?u.searchParams.set('q',Q):u.searchParams.delete('q');
      history.replaceState(null,'',u);
    }
    qBox.addEventListener('input',function(){clearTimeout(tmr);tmr=setTimeout(runSearch,320)});
    qBox.addEventListener('keydown',function(e){
      if(e.key==='Enter'){e.preventDefault();clearTimeout(tmr);runSearch()}
    });
    qClear&&qClear.addEventListener('click',function(){qBox.value='';runSearch();qBox.focus()});
  }

  /* Hydrate the drill from the URL filter so arriving with ?vehicle= / ?category=
     (e.g. the "Shop parts" link on a part page) shows the selection, not empty
     dropdowns. The grid itself is already filtered server-side; select() only
     mirrors the state into the comboboxes without re-fetching. */
  function enc(x){return encodeURIComponent(x)}
  api('/tree/makes').then(function(d){
    SS.make.set(d.map(function(x){return {v:x.slug,t:x.name,n:x.n}}),'All makes');
    if(!(PRESET && PRESET.make)) return;
    S.make=PRESET.make; T.make=PRESET.makeL; SS.make.select(PRESET.make, PRESET.makeL);
    return api('/models?make='+enc(PRESET.make)).then(function(d){
      SS.model.set(d.map(function(x){return {v:x.slug,t:x.name}}),'All models');
      S.model=PRESET.model; T.model=PRESET.modelL; SS.model.select(PRESET.model, PRESET.modelL);
      return api('/tree/years?make='+enc(PRESET.make)+'&model='+enc(PRESET.model));
    }).then(function(d){
      SS.year.set(d.map(function(x){return {v:String(x),t:String(x)}}),'All years');
      S.year=PRESET.year; T.year=PRESET.year; SS.year.select(PRESET.year, PRESET.year);
      return api('/vehicles?year='+enc(PRESET.year)+'&make='+enc(PRESET.make)+'&model='+enc(PRESET.model));
    }).then(function(d){
      SS.engine.set(d.map(function(x){return {v:x.slug,t:x.label}}),'All engines');
      S.engine=PRESET.engine; T.engine=PRESET.engineL; SS.engine.select(PRESET.engine, PRESET.engineL);
      return api('/tree/groups?vehicle='+enc(PRESET.engine));
    }).then(function(d){
      SS.cat.set(d.map(function(x){return {v:x.slug,t:x.name,n:x.n}}),'All categories');
      var group=PRESET.cat || '';
      if(!group && PRESET.sub){ group=''; }   // sub without its group -> just show grid
      if(PRESET.cat){ S.cat=PRESET.cat; T.cat=PRESET.catL; SS.cat.select(PRESET.cat, PRESET.catL);
        return api('/tree/categories?vehicle='+enc(PRESET.engine)+'&group='+enc(PRESET.cat)).then(function(d){
          SS.sub.set(d.map(function(x){return {v:x.slug,t:x.name,n:x.n}}),'All subcategories');
          if(PRESET.sub){ S.sub=PRESET.sub; T.sub=PRESET.subL; SS.sub.select(PRESET.sub, PRESET.subL); }
        });
      }
    }).then(function(){ clear.hidden=false; });
  });
})();
</script>
