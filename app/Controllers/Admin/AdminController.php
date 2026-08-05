<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Auth;

/** Base for all authenticated admin pages: guards access + renders admin chrome. */
abstract class AdminController extends Controller
{
    public function __construct()
    {
        Auth::start();
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    /** Render a view inside the admin layout. */
    protected function adminRender(string $view, array $data = [], ?string $title = null): void
    {
        $data['title']   = ($title ? $title . ' — ' : '') . 'Supreme Spare Parts Admin';
        $data['_controller'] = $this;
        $data['_user']   = Auth::user();
        $data['_active'] = $data['_active'] ?? '';
        $data['_flash']  = $this->takeFlash();
        $content = $this->capture('admin/' . $view, $data);
        $data['content'] = $content;
        echo $this->capture('admin/layout', $data);
    }

    protected function adminUrl(string $path = ''): string
    {
        return $this->url('/admin/' . ltrim($path, '/'));
    }

    /** Reject non-matching CSRF tokens on state-changing requests. */
    protected function requireCsrf(): void
    {
        if (!Auth::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Invalid or expired form token. Go back and try again.');
        }
    }

    protected function flash(string $type, string $msg): void
    {
        Auth::start();
        $_SESSION['_flash'] = ['type' => $type, 'msg' => $msg];
    }

    protected function takeFlash(): ?array
    {
        Auth::start();
        $f = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $f;
    }

    /** Small pagination helper: returns [limit, offset, page]. */
    /** @return array{0:int,1:int,2:int} [limit, offset, page]. Honors ?per= (rows/page). */
    protected function pageWindow(int $perPage = 25): array
    {
        $allowed = [25, 50, 100];
        $per = (int) ($_GET['per'] ?? $perPage);
        if (!in_array($per, $allowed, true)) {
            $per = $perPage;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        return [$per, ($page - 1) * $per, $page];
    }
}
