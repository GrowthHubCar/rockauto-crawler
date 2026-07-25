<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

/** Newsletter subscribers captured by the storefront signup form. */
class SubscriberController extends AdminController
{
    public function index(): void
    {
        $per  = 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * $per;

        $total = (int) $this->db()
            ->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();

        $stmt = $this->db()->prepare(
            "SELECT email, source, created_at
               FROM newsletter_subscribers
           ORDER BY created_at DESC, id DESC
              LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':lim', $per, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $off, \PDO::PARAM_INT);
        $stmt->execute();

        $this->adminRender('subscribers', [
            'subs'    => $stmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
            'per'     => $per,
            '_active' => 'subscribers',
        ], 'Subscribers');
    }
}
