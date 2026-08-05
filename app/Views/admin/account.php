<?php /** @var array $admin @var array $errors @var string $csrf @var \App\Core\Controller $_controller */
$err = fn(string $k) => isset($errors[$k]) ? '<small class="err">' . e($errors[$k]) . '</small>' : '';
?>
<h1 class="adm-h1">My account</h1>
<p class="adm-sub">Update your name, sign-in email, and password.</p>

<form method="post" action="<?= e($_controller->url('/admin/account')) ?>" class="adm-form" style="max-width:560px">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <div class="adm-form__section">
    <p class="adm-form__section-title">Profile</p>
    <label class="adm-field">
      <span>Name</span>
      <input type="text" name="name" value="<?= e((string) ($admin['name'] ?? '')) ?>" maxlength="120" autocomplete="name" required>
      <?= $err('name') ?>
    </label>
    <label class="adm-field">
      <span>Email</span>
      <input type="email" name="email" value="<?= e((string) ($admin['email'] ?? '')) ?>" maxlength="190" autocomplete="username" required>
      <?= $err('email') ?>
    </label>
  </div>

  <div class="adm-form__section">
    <p class="adm-form__section-title">Change password</p>
    <label class="adm-field">
      <span>New password</span>
      <input type="password" name="new_password" autocomplete="new-password" placeholder="Leave blank to keep your current password">
      <?= $err('new_password') ?>
    </label>
    <label class="adm-field">
      <span>Confirm new password</span>
      <input type="password" name="confirm_password" autocomplete="new-password">
      <?= $err('confirm_password') ?>
    </label>
  </div>

  <div class="adm-form__section">
    <p class="adm-form__section-title">Confirm it's you</p>
    <label class="adm-field">
      <span>Current password</span>
      <input type="password" name="current_password" autocomplete="current-password">
      <small class="hint">Required only when changing your email or password.</small>
      <?= $err('current_password') ?>
    </label>
  </div>

  <div style="margin-top:8px">
    <button class="btn btn-primary" type="submit">Save changes</button>
  </div>
</form>
