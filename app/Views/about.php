<?php /** @var array $stats @var \App\Core\Controller $_controller */
$url = fn(string $p) => e($_controller->url($p));
$n   = fn($v) => number_format((int) $v);
?>
<!-- 1. Intro: heading + copy left, single plate + rotating seal right -->
<section class="ab-hero">
  <div class="ab-hero-t">
    <p class="ab-kicker">About us</p>
    <h1>Built on fitment,<br>not guesswork</h1>
    <p class="ab-lead">Buying the wrong part costs you the weekend, not the price of the part.
       Everything here exists to make that mistake hard to make &mdash; every listing tied to
       verified fitment for your exact year, make, model, engine and trim.</p>
    <a class="link-arrow" href="<?= $url('/products') ?>">Find your vehicle
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
  </div>
  <div class="ab-hero-im">
    <div class="ab-plate" role="presentation"
         style="background-image:url('https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?q=80&amp;w=1200&amp;auto=format&amp;fit=crop')"></div>
    <div class="ab-seal" aria-hidden="true">
      <svg viewBox="0 0 120 120">
        <defs><path id="abcirc" d="M60,60 m-43,0 a43,43 0 1,1 86,0 a43,43 0 1,1 -86,0"/></defs>
        <text><textPath href="#abcirc" startOffset="0">SUPREME SPARE PARTS &#183; VERIFIED FITMENT &#183; </textPath></text>
      </svg>
      <span class="ab-seal-c">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M7 17 17 7M9 7h8v8"/></svg>
      </span>
    </div>
  </div>
</section>

<!-- 2. Mission statement, tail receding -->
<section class="ab-say">
  <p>We are building the parts catalog we wanted as owners &mdash; one that answers
     &ldquo;does this fit my car?&rdquo; before you pay,
     <span>not with a disclaimer after you have. Real fitment data, honest availability,
     and a person at the end of it when the data is not enough.</span></p>
</section>

<!-- 3. Figures, centred -->
<?php $fig = array_filter([
        'Parts catalogued'  => (int) ($stats['parts'] ?? 0),
        'Verified fitments' => (int) ($stats['fitments'] ?? 0),
        'Vehicles covered'  => (int) ($stats['vehicles'] ?? 0),
        'Brands stocked'    => (int) ($stats['brands'] ?? 0),
      ]); ?>
<?php if ($fig): ?>
  <dl class="ab-figs">
    <?php foreach ($fig as $k => $v): ?>
      <div><dt><?= $n($v) ?></dt><dd><?= e($k) ?></dd></div>
    <?php endforeach; ?>
  </dl>
<?php endif; ?>

<!-- 4. Mosaic -->
<section class="ab-mosaic-sec">
  <div class="ab-head">
    <h2>Where data meets the garage</h2>
    <p>A catalog is only as good as the fitment behind it. These are the numbers we hold
       ourselves to.</p>
  </div>
  <div class="ab-mosaic">
    <div class="mz mz-ph a" role="presentation"
         style="background-image:url('https://images.unsplash.com/photo-1487754180451-c456f719a1fc?q=80&amp;w=900&amp;auto=format&amp;fit=crop')"></div>
    <div class="mz mz-stat is-red b">
      <b><?= $n($stats['fitments'] ?? 0) ?></b>
      <span>Verified fitment records behind every result</span>
    </div>
    <div class="mz mz-ph c" role="presentation"
         style="background-image:url('https://images.unsplash.com/photo-1530046339160-ce3e530c7d2f?q=80&amp;w=900&amp;auto=format&amp;fit=crop')"></div>
    <div class="mz mz-ph d" role="presentation"
         style="background-image:url('https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?q=80&amp;w=900&amp;auto=format&amp;fit=crop')"></div>
    <div class="mz mz-stat is-dark e">
      <b><?= $n($stats['vehicles'] ?? 0) ?></b>
      <span>Vehicles you can drill to, engine and trim included</span>
    </div>
  </div>
</section>

<!-- 5. Heading left, narrative right -->
<section class="ab-two">
  <h2>Making the right part<br>easy to find</h2>
  <div>
    <p>Most catalogs show you everything and let you guess. Pick your vehicle here, or paste your
       VIN, and the catalog stops showing you everything and starts showing only what is
       confirmed to fit it.</p>
    <p>When a part has no live price we print <em>Out of Stock</em> rather than a number that
       fails at checkout. Genuine OEM sits beside premium aftermarket, so the trade between
       specification and price stays your call &mdash; not ours.</p>
  </div>
</section>

<!-- 6. Principles — asymmetric bento (red block / tall dark / photo / light),
     a deliberately different composition from the mosaic above -->
<section class="ab-rules">
  <div class="ab-head">
    <h2>What we stand behind</h2>
    <p>Four commitments the whole catalog is built to keep. Not slogans, but rules the data
       actually follows.</p>
  </div>
  <div class="ab-bento2">
    <article class="p2 p2-red x-tl">
      <span class="p2-n">01</span>
      <div class="p2-b">
        <h3>Fitment first</h3>
        <p>A part is listed against a vehicle only when the data says it fits that exact engine
           and trim, not the model line in general. That distinction is the whole product.</p>
      </div>
    </article>
    <article class="p2 p2-light x-tr">
      <span class="p2-n">02</span>
      <div class="p2-b">
        <h3>Honest availability</h3>
        <p>No live price means <em>Out of Stock</em>, printed plainly. Better a real gap than a
           number that fails at the till.</p>
      </div>
    </article>
    <article class="p2 p2-photo x-bl"
             style="background-image:url('https://images.unsplash.com/photo-1530046339160-ce3e530c7d2f?q=80&amp;w=900&amp;auto=format&amp;fit=crop')">
      <span class="p2-n">03</span>
      <div class="p2-b">
        <h3>OEM and aftermarket, together</h3>
        <p>Both on the same page, with the specs to compare them. You decide where the money goes.</p>
      </div>
    </article>
    <article class="p2 p2-dark x-br">
      <span class="p2-n">04</span>
      <div class="p2-b">
        <h3>A person at the end of it</h3>
        <p>Send a VIN or a photo of the old part and someone who knows the catalog confirms the
           right one before you spend anything.</p>
      </div>
    </article>
  </div>
</section>

<!-- 7. Word from the team. Unattributed on purpose: inventing a named founder and
     pairing them with a stock portrait would be fabricating a person. -->
<section class="ab-word">
  <div class="ab-word-t">
    <p class="ab-kicker">Our story</p>
    <h2>Why we built this</h2>
    <p>It started with a simple frustration: ordering a part listed for your car, waiting for it,
       and finding it did not fit. The listing was not wrong on purpose &mdash; it was matched to
       a model line instead of a vehicle.</p>
    <p>So we built the catalog the other way round. Fitment comes first, and a part only appears
       against your car when the data says it belongs there. The search, the drill-down, the VIN
       decode &mdash; all of it serves that one promise.</p>
    <p class="ab-word-by"><b>The Supreme Spare Parts team</b><span>Catalog &amp; fitment</span></p>
  </div>
  <figure class="ab-word-im">
    <div class="ab-word-photo" role="presentation"
         style="background-image:url('https://images.unsplash.com/photo-1552519507-da3b142c6e3d?q=80&amp;w=1000&amp;auto=format&amp;fit=crop')"></div>
    <figcaption class="ab-word-badge">
      <b><?= $n($stats['vehicles'] ?? 0) ?></b>
      <span>vehicles<br>covered</span>
    </figcaption>
  </figure>
</section>

<section class="ab-cta">
  <div>
    <h2>Ready to find the right part?</h2>
    <p>Start from your vehicle, or browse everything we stock.</p>
  </div>
  <div class="ab-cta-b">
    <a class="btn btn-accent" href="<?= $url('/products') ?>">Find my vehicle</a>
    <a class="btn" href="<?= $url('/products') ?>">Browse all parts</a>
  </div>
</section>
