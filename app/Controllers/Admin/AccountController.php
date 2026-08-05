<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;

/** The signed-in admin edits their own name, email and password. */
class AccountController extends AdminController
{
    public function index(): void
    {
        $this->render_form(Auth::user() ?? [], []);
    }

    public function update(): void
    {
        $this->requireCsrf();
        $db   = $this->db();
        $me   = Auth::user() ?? [];
        $id   = (int) ($me['id'] ?? 0);

        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $cur   = (string) ($_POST['current_password'] ?? '');
        $new   = (string) ($_POST['new_password'] ?? '');
        $conf  = (string) ($_POST['confirm_password'] ?? '');

        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Enter your name (120 characters max).';
        }
        if ($email === '' || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        // Current admin's stored hash (source of truth, not the session).
        $stmt = $db->prepare("SELECT password_hash, email FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { $this->flash('error', 'Account not found.'); $this->redirect('/admin/account'); return; }

        $wantsPw      = $new !== '' || $conf !== '';
        $emailChanged = mb_strtolower($email) !== mb_strtolower((string) $row['email']);

        // Changing email or password requires re-entering the current password.
        if (!$errors && ($wantsPw || $emailChanged)) {
            if ($cur === '' || !password_verify($cur, (string) $row['password_hash'])) {
                $errors['current_password'] = 'Your current password is incorrect.';
            }
        }
        if (!$errors && $wantsPw) {
            if (mb_strlen($new) < 8)      { $errors['new_password'] = 'Use at least 8 characters.'; }
            elseif ($new !== $conf)       { $errors['confirm_password'] = 'The new passwords do not match.'; }
        }
        // Email must stay unique across admins.
        if (!isset($errors['email']) && $emailChanged) {
            $ex = $db->prepare("SELECT id FROM admins WHERE email = ? AND id <> ?");
            $ex->execute([$email, $id]);
            if ($ex->fetch()) { $errors['email'] = 'That email is already in use.'; }
        }

        if ($errors) {
            $this->render_form(['id' => $id, 'name' => $name, 'email' => $email], $errors);
            return;
        }

        if ($wantsPw) {
            $db->prepare("UPDATE admins SET name = ?, email = ?, password_hash = ? WHERE id = ?")
               ->execute([$name, $email, password_hash($new, PASSWORD_DEFAULT), $id]);
        } else {
            $db->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?")
               ->execute([$name, $email, $id]);
        }

        // Keep the session identity (header name/email) in step with the change.
        Auth::start();
        $_SESSION['admin']['name']  = $name;
        $_SESSION['admin']['email'] = $email;

        $this->flash('ok', $wantsPw ? 'Account and password updated.' : 'Account updated.');
        $this->redirect('/admin/account');
    }

    /** @param array<string,mixed> $admin @param array<string,string> $errors */
    private function render_form(array $admin, array $errors): void
    {
        $this->adminRender('account', [
            'admin'   => $admin,
            'errors'  => $errors,
            'csrf'    => Auth::token(),
            '_active' => 'account',
        ], 'My account');
    }
}
