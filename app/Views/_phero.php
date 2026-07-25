<?php
/** Shared page hero. Set before including:
 *    $ph_eyebrow  short uppercase kicker
 *    $ph_h1       heading (may contain <br>, already escaped by the caller)
 *    $ph_lead     lead paragraph
 *    $ph_img      background image URL
 *    $ph_rail     optional [label => number] figures rail along the base */
$ph_rail = array_filter($ph_rail ?? [], fn($v) => (int) $v > 0);
?>
<section class="phero">
  <div class="phero-im" role="presentation"
       style="background-image:url('<?= e($ph_img) ?>')"></div>
  <div class="phero-body">
    <div class="phero-t">
      <p class="eyebrow"><?= e($ph_eyebrow) ?></p>
      <h1><?= $ph_h1 ?><span class="dot">.</span></h1>
      <?php if (!empty($ph_lead)): ?>
        <p class="phero-lead"><?= e($ph_lead) ?></p>
      <?php endif; ?>
    </div>
    <?php if ($ph_rail): ?>
      <dl class="phero-rail">
        <?php $first = true; foreach ($ph_rail as $k => $v): ?>
          <div>
            <dt><?= number_format((int) $v) ?></dt>
            <dd><?php if ($first): ?><span class="pip" aria-hidden="true"></span><?php endif; ?><?= e($k) ?></dd>
          </div>
        <?php $first = false; endforeach; ?>
      </dl>
    <?php endif; ?>
  </div>
</section>
