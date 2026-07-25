<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

class DashboardController extends AdminController
{
    public function index(): void
    {
        $db = $this->db();

        // Catalog counts are cached (COUNT(*) over part_fitment alone is ~3s on
        // 12M rows). Orders is small and stays live so new sales show at once.
        $cat = $this->catalogStats();
        $orders = (int) $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $counts = $cat['counts'] + ['orders' => $orders];
        $priced = $cat['priced'];
        $topCats = $cat['topCats'];

        $recentParts = $db->query(
            "SELECT p.sku, p.name, p.price, b.name AS brand, p.updated_at
               FROM parts p LEFT JOIN brands b ON b.id = p.brand_id
              ORDER BY p.updated_at DESC LIMIT 8"
        )->fetchAll();

        $recentImports = $db->query(
            "SELECT `type`, filename, rows_ok, rows_failed, `status`, created_at
               FROM import_logs ORDER BY created_at DESC LIMIT 6"
        )->fetchAll();

        // --- Chart data ---------------------------------------------------
        // Revenue + orders over the last 30 days, zero-filled so the axis is
        // always continuous even with sparse (or zero) orders.
        $rows = $db->query(
            "SELECT DATE(placed_at) AS d, COALESCE(SUM(grand_total),0) AS rev, COUNT(*) AS n
               FROM orders
              WHERE placed_at >= (CURDATE() - INTERVAL 29 DAY)
                AND status NOT IN ('cancelled','refunded')
              GROUP BY DATE(placed_at)"
        )->fetchAll();
        $byDay = [];
        foreach ($rows as $r) { $byDay[$r['d']] = ['rev' => (float) $r['rev'], 'n' => (int) $r['n']]; }
        $revenueSeries = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $revenueSeries[] = ['date' => $d, 'rev' => $byDay[$d]['rev'] ?? 0.0, 'n' => $byDay[$d]['n'] ?? 0];
        }
        $revenue30 = array_sum(array_column($revenueSeries, 'rev'));
        $orders30  = array_sum(array_column($revenueSeries, 'n'));

        // Lifetime revenue (fulfilled statuses) + all-time order count.
        $lifetime = $db->query(
            "SELECT COALESCE(SUM(grand_total),0) AS total, COUNT(*) AS n
               FROM orders WHERE status IN ('paid','processing','shipped','completed')"
        )->fetch();

        $this->adminRender('dashboard',
            ['counts' => $counts, 'recentParts' => $recentParts, 'recentImports' => $recentImports,
             'revenueSeries' => $revenueSeries, 'revenue30' => $revenue30, 'orders30' => $orders30,
             'lifetime' => $lifetime, 'priced' => $priced, 'topCats' => $topCats, '_active' => 'dashboard'],
            'Dashboard');
    }

    /** Heavy catalog aggregates (counts + priced coverage + top categories),
     *  cached in settings for an hour. These barely move minute-to-minute, and
     *  computing them live costs ~4s (12M-row part_fitment count dominates). */
    private function catalogStats(): array
    {
        $db = $this->db();
        try {
            $raw = $db->query("SELECT `value` FROM settings WHERE `key`='admin_dash_cache'")->fetchColumn();
            $c = $raw ? json_decode((string) $raw, true) : null;
            if (is_array($c) && (int) ($c['t'] ?? 0) > time() - 3600) {
                return $c;
            }

            $counts = $db->query(
                "SELECT
                   (SELECT COUNT(*) FROM parts)        AS parts,
                   (SELECT COUNT(*) FROM part_fitment)  AS fitments,
                   (SELECT COUNT(*) FROM vehicles)      AS vehicles,
                   (SELECT COUNT(*) FROM makes)         AS makes,
                   (SELECT COUNT(*) FROM models)        AS models,
                   (SELECT COUNT(*) FROM brands)        AS brands,
                   (SELECT COUNT(*) FROM categories)    AS categories"
            )->fetch(\PDO::FETCH_ASSOC);
            $counts = array_map('intval', $counts);

            $priced = $db->query(
                "SELECT SUM(price IS NOT NULL) AS priced, SUM(price IS NULL) AS unpriced FROM parts"
            )->fetch(\PDO::FETCH_ASSOC);

            $topCats = $db->query(
                "SELECT c.name, COUNT(*) AS n
                   FROM parts p JOIN categories c ON c.id = p.category_id
                  GROUP BY c.id ORDER BY n DESC LIMIT 6"
            )->fetchAll();

            $out = ['counts' => $counts, 'priced' => $priced, 'topCats' => $topCats, 't' => time()];
            $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('admin_dash_cache', ?)
                          ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
               ->execute([json_encode($out)]);
            return $out;
        } catch (\Throwable $e) {
            return ['counts' => ['parts' => 0, 'fitments' => 0, 'vehicles' => 0, 'makes' => 0,
                                 'models' => 0, 'brands' => 0, 'categories' => 0],
                    'priced' => ['priced' => 0, 'unpriced' => 0], 'topCats' => []];
        }
    }
}
