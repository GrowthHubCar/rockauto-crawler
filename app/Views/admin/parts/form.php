<?php
/** @var ?array $part @var array $brands @var array $categories @var string $csrf @var \App\Core\Controller $_controller */
$isEdit = $part !== null;
$action = $isEdit ? $_controller->url('/admin/parts/' . $part['id']) : $_controller->url('/admin/parts');
$val = fn(string $k, $d = '') => e((string) ($part[$k] ?? $d));
?>
<nav class="adm-crumbs" aria-label="Breadcrumb">
  <a href="<?= e($_controller->url('/admin/parts')) ?>">Parts</a>
  <span class="sep" aria-hidden="true">/</span>
  <span><?= $isEdit ? 'Edit' : 'New part' ?></span>
</nav>

<div class="adm-head-row">
  <h1 class="adm-h1"><?= $isEdit ? e($part['name']) : 'New part' ?></h1>
  <a class="btn btn-sm btn-ghost" href="<?= e($_controller->url('/admin/parts')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
    Back to parts
  </a>
</div>

<form class="adm-form" method="post" action="<?= e($action) ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <div class="adm-form__section">
    <p class="adm-form__section-title">Identity</p>
    <div class="adm-form-grid">
      <label class="wide">Name<input type="text" name="name" value="<?= $val('name') ?>" required autocomplete="off"></label>
      <label>Part number<input type="text" name="part_number" value="<?= $val('part_number') ?>" required autocomplete="off"></label>
      <label>SKU <span class="hint">Blank uses the part number</span><input type="text" name="sku" value="<?= $val('sku') ?>" autocomplete="off"></label>
      <label>Brand
        <select name="brand_id">
          <option value="">No brand</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= ($part['brand_id'] ?? null) == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Category
        <select name="category_id">
          <option value="">Uncategorized</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($part['category_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= e($c['slug']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </div>

  <div class="adm-form__section" style="margin-top:24px">
    <p class="adm-form__section-title">Pricing &amp; status</p>
    <div class="adm-form-grid">
      <label>Price <span class="hint">RockAuto cost, pre-markup</span><input type="number" step="0.01" min="0" name="price" value="<?= $val('price', '0') ?>"></label>
      <label>Core charge<input type="number" step="0.01" min="0" name="core_charge" value="<?= $val('core_charge', '0') ?>"></label>
      <label>Weight <span class="hint">lb, optional</span><input type="number" step="0.01" min="0" name="weight" value="<?= $val('weight') ?>"></label>
      <label>Status
        <select name="status">
          <?php foreach (['active','inactive','discontinued'] as $s): ?>
            <option value="<?= $s ?>" <?= ($part['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </div>

  <div class="adm-form__section" style="margin-top:24px">
    <p class="adm-form__section-title">Media &amp; description</p>
    <div class="adm-form-grid">
      <label class="wide">Primary image URL<input type="text" id="primary_image_path" name="primary_image_path" value="<?= $val('primary_image_path') ?>" autocomplete="off"></label>
      <?php $imgval = $val('primary_image_path'); ?>
      <div class="adm-img-preview wide" style="margin:-6px 0 2px">
        <img id="primary_image_preview" src="<?= $imgval ?>" alt="Part image preview"
             style="max-width:160px;max-height:160px;object-fit:contain;background:#fff;padding:6px;<?= $imgval ? '' : 'display:none;' ?>"
             onerror="this.style.display='none';">
      </div>
      <label class="wide">Description<textarea name="description" rows="4"><?= $val('description') ?></textarea></label>
    </div>
  </div>

  <div class="adm-form-actions">
    <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Create part' ?></button>
    <a class="btn btn-ghost" href="<?= e($_controller->url('/admin/parts')) ?>">Cancel</a>
  </div>
</form>

<script>
  (function () {
    var inp = document.getElementById('primary_image_path');
    var img = document.getElementById('primary_image_preview');
    if (inp && img) inp.addEventListener('input', function () {
      if (this.value) { img.src = this.value; img.style.display = ''; } else { img.style.display = 'none'; }
    });
  })();
</script>
