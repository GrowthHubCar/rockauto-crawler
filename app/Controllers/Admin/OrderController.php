<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

/** Storefront orders: list, detail, and status transitions. */
class OrderController extends AdminController
{
    private const STATUSES = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];

    public function index(): void
    {
        $per  = 25;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * $per;
        $status = (string) ($_GET['status'] ?? '');
        $where  = in_array($status, self::STATUSES, true) ? 'WHERE o.status = :st' : '';

        $cnt = $this->db()->prepare("SELECT COUNT(*) FROM orders o {$where}");
        if ($where) { $cnt->bindValue(':st', $status); }
        $cnt->execute();
        $total = (int) $cnt->fetchColumn();

        $stmt = $this->db()->prepare(
            "SELECT o.id, o.order_number, o.email, o.status, o.grand_total, o.currency, o.placed_at,
                    (SELECT COALESCE(SUM(quantity),0) FROM order_items oi WHERE oi.order_id = o.id) AS n_items
               FROM orders o {$where}
           ORDER BY o.placed_at DESC, o.id DESC
              LIMIT :lim OFFSET :off"
        );
        if ($where) { $stmt->bindValue(':st', $status); }
        $stmt->bindValue(':lim', $per, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $off, \PDO::PARAM_INT);
        $stmt->execute();

        // status counts for the tabs, plus revenue on fulfilled orders
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db()->query("SELECT status, COUNT(*) n FROM orders GROUP BY status")->fetchAll() as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        $revenue = (float) $this->db()->query(
            "SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE status IN ('paid','processing','shipped','completed')"
        )->fetchColumn();

        $this->adminRender('orders/index', [
            'orders'  => $stmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
            'per'     => $per,
            'status'  => $status,
            'counts'  => $counts,
            'revenue' => $revenue,
            'all'     => array_sum($counts),
            '_active' => 'orders',
        ], 'Orders');
    }

    public function show(string $id): void
    {
        $db = $this->db();
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([(int) $id]);
        $order = $stmt->fetch();
        if (!$order) { $this->notFoundResponse(); return; }

        $items = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
        $items->execute([$order['id']]);

        $addrs = $db->prepare("SELECT * FROM order_addresses WHERE order_id = ?");
        $addrs->execute([$order['id']]);
        $addresses = [];
        foreach ($addrs->fetchAll() as $a) { $addresses[$a['type']] = $a; }

        $pay = null;
        try {
            $p = $db->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
            $p->execute([$order['id']]);
            $pay = $p->fetch() ?: null;
        } catch (\Throwable $e) { /* payments table optional */ }

        $this->adminRender('orders/show', [
            'order'     => $order,
            'items'     => $items->fetchAll(),
            'addresses' => $addresses,
            'payment'   => $pay,
            'statuses'  => self::STATUSES,
            '_active'   => 'orders',
        ], 'Order ' . $order['order_number']);
    }

    public function status(string $id): void
    {
        $this->requireCsrf();
        $to = (string) ($_POST['status'] ?? '');
        if (in_array($to, self::STATUSES, true)) {
            $this->db()->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$to, (int) $id]);
            $this->flash('ok', 'Order status updated to ' . $to . '.');
        }
        $this->redirect('/admin/orders/' . (int) $id);
    }
}
