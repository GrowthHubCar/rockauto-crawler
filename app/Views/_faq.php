<?php /* Shared FAQ — included by the homepage and the About page. */ ?>
<section class="faq sec" id="faq"><div class="wrap">
  <?php
    $sEmail = trim((string) (setting('contact_email', '') ?? ''));
    $sWaUrl = whatsapp_link('Hi, I need help finding a part.');
  ?>
  <div class="faq-head reveal">
    <div class="faq-head-t">
      <h2>Didn't find what you're looking for?</h2>
      <p>Send us your question and we'll respond shortly.</p>
    </div>
    <?php if ($sWaUrl !== ''): ?>
      <a class="btn btn-wa" href="<?= e($sWaUrl) ?>" target="_blank" rel="noopener">
        <svg width="17" height="17" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.22 8.22 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.25-8.23a8.2 8.2 0 0 1 8.24 8.24c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.19-.54.06-.25-.12-1.05-.39-2-1.23a7.5 7.5 0 0 1-1.38-1.72c-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.44.13-.15.17-.25.25-.41.09-.17.04-.31-.02-.44-.06-.12-.56-1.35-.77-1.85-.2-.48-.4-.42-.56-.43h-.47c-.16 0-.43.06-.65.31-.23.24-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.21 3.72.59.25 1.05.4 1.4.52.59.18 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.17-.47-.29Z"/></svg>
        Ask on WhatsApp
      </a>
    <?php elseif ($sEmail !== ''): ?>
      <a class="btn btn-red" href="mailto:<?= e($sEmail) ?>">Email Us</a>
    <?php endif; ?>
  </div>
  <div class="acc reveal" id="acc">
    <div class="acc-item"><button class="acc-q" aria-expanded="false">How do I know whether a part fits my vehicle?<span class="ico"><i class="h"></i><i class="v"></i></span></button><div class="acc-a"><div class="inner">Every part is matched to your exact year, make, model, and engine using verified fitment data. Select your vehicle or enter your VIN, and you will only see parts confirmed to fit — backed by our fitment guarantee.</div></div></div>
    <div class="acc-item"><button class="acc-q" aria-expanded="false">Can I search using my vehicle's VIN?<span class="ico"><i class="h"></i><i class="v"></i></span></button><div class="acc-a"><div class="inner">Yes. Enter your 17-character VIN in the VIN tab and we decode your exact vehicle automatically, then show the parts that fit it — no manual selection needed.</div></div></div>
    <div class="acc-item"><button class="acc-q" aria-expanded="false">Where can I find my VIN number?<span class="ico"><i class="h"></i><i class="v"></i></span></button><div class="acc-a"><div class="inner">Your VIN appears on the lower driver-side corner of the windshield, inside the driver door jamb, and on your vehicle registration and insurance documents.</div></div></div>
    <div class="acc-item"><button class="acc-q" aria-expanded="false">Do you sell genuine OEM and aftermarket parts?<span class="ico"><i class="h"></i><i class="v"></i></span></button><div class="acc-a"><div class="inner">Both. We stock genuine OEM components alongside premium aftermarket brands, so you can choose the exact balance of price and specification you need.</div></div></div>
    <div class="acc-item"><button class="acc-q" aria-expanded="false">How long does delivery take?<span class="ico"><i class="h"></i><i class="v"></i></span></button><div class="acc-a"><div class="inner">In-stock orders placed before 4pm dispatch the same day, with standard nationwide delivery in 2–4 business days. Expedited options are available at checkout.</div></div></div>
    <div class="acc-item"><button class="acc-q" aria-expanded="false">Can I return a part if it does not fit?<span class="ico"><i class="h"></i><i class="v"></i></span></button><div class="acc-a"><div class="inner">Yes. Unused parts in original packaging can be returned within 90 days. If a part does not fit despite matching your vehicle, we cover the return shipping.</div></div></div>
    <div class="acc-item"><button class="acc-q" aria-expanded="false">Can your team help me identify a part?<span class="ico"><i class="h"></i><i class="v"></i></span></button><div class="acc-a"><div class="inner">Absolutely. Our parts specialists can identify a component from a part number, a photo, or your vehicle details. Call or email us and we will confirm the correct part.</div></div></div>
    <div class="acc-item"><button class="acc-q" aria-expanded="false">How do I check on my order?<span class="ico"><i class="h"></i><i class="v"></i></span></button><div class="acc-a"><div class="inner">You will get an order confirmation by email as soon as your order is placed. For a status update, message our parts team and we will check it for you.</div></div></div>
  </div>
</div></section>

<script>
/* Accordion. Guarded so including this partial twice on one page is harmless. */
(function(){
  if(window.__faqInit)return; window.__faqInit=1;
  document.querySelectorAll('.acc-item').forEach(function(it){
    var q=it.querySelector('.acc-q'),a=it.querySelector('.acc-a');
    if(it.classList.contains('open'))a.style.maxHeight=a.scrollHeight+'px';
    q.addEventListener('click',function(){
      var open=it.classList.contains('open');
      document.querySelectorAll('.acc-item').forEach(function(x){
        x.classList.remove('open');
        x.querySelector('.acc-q').setAttribute('aria-expanded','false');
        x.querySelector('.acc-a').style.maxHeight='0px';
      });
      if(!open){it.classList.add('open');q.setAttribute('aria-expanded','true');a.style.maxHeight=a.scrollHeight+'px'}
    });
  });
})();
</script>
