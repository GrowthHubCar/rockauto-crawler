<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * JSON endpoints powering the RockAuto-style cascading vehicle picker.
 * Year -> Make -> Model -> Engine(vehicle). Each step filters vehicles.
 */
class ApiController extends Controller
{
    public function years(): void
    {
        $rows = $this->db()->query(
            "SELECT DISTINCT `year` FROM vehicles ORDER BY `year` DESC"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $this->json(array_map('intval', $rows));
    }

    public function makes(): void
    {
        $year = (int) ($_GET['year'] ?? 0);
        $stmt = $this->db()->prepare(
            "SELECT DISTINCT m.name, m.slug
               FROM makes m JOIN vehicles v ON v.make_id = m.id
              WHERE (:y0 = 0 OR v.`year` = :y1)
              ORDER BY m.name"
        );
        $stmt->execute([':y0' => $year, ':y1' => $year]);
        $this->json($stmt->fetchAll());
    }

    public function models(): void
    {
        $year = (int) ($_GET['year'] ?? 0);
        $make = (string) ($_GET['make'] ?? '');
        $stmt = $this->db()->prepare(
            "SELECT DISTINCT mo.name, mo.slug
               FROM models mo
               JOIN vehicles v ON v.model_id = mo.id
               JOIN makes m    ON m.id = v.make_id
              WHERE m.slug = :make AND (:y0 = 0 OR v.`year` = :y1)
              ORDER BY mo.name"
        );
        $stmt->execute([':make' => $make, ':y0' => $year, ':y1' => $year]);
        $this->json($stmt->fetchAll());
    }

    /** Returns selectable vehicles (engine variants) for make+model+year. */
    public function vehicles(): void
    {
        $year  = (int) ($_GET['year'] ?? 0);
        $make  = (string) ($_GET['make'] ?? '');
        $model = (string) ($_GET['model'] ?? '');
        $stmt = $this->db()->prepare(
            "SELECT v.slug,
                    COALESCE(e.name, 'Standard') AS label,
                    v.trim
               FROM vehicles v
               JOIN makes m  ON m.id = v.make_id
               JOIN models mo ON mo.id = v.model_id
          LEFT JOIN engines e ON e.id = v.engine_id
              WHERE m.slug = :make AND mo.slug = :model
                AND (:y0 = 0 OR v.`year` = :y1)
              ORDER BY label, v.trim"
        );
        $stmt->execute([':make' => $make, ':model' => $model, ':y0' => $year, ':y1' => $year]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            if (!empty($r['trim'])) {
                $r['label'] .= ' — ' . $r['trim'];
            }
            unset($r['trim']);
        }
        $this->json($rows);
    }

    /** 8 sellable parts for the homepage grid, optionally inside one category group.
     *  Starts at a date-seeded random id so the set rotates daily but the query
     *  still rides the primary key (ORDER BY RAND() would scan ~1M rows). */
    public function featured(): void
    {
        $cat = trim((string) ($_GET['category'] ?? ''));
        $join = '';
        $where = ['p.price > 0', 'p.primary_image_path IS NOT NULL'];
        $args = [];
        if ($cat !== '') {
            $join = " JOIN categories c ON c.id = p.category_id
                      JOIN categories g ON g.id = COALESCE(c.parent_id, c.id) ";
            $where[] = '(c.slug = :cat OR g.slug = :cat2)';
            $args[':cat'] = $cat;
            $args[':cat2'] = $cat;
        }
        $base = "SELECT p.slug, p.name, p.price, p.primary_image_path, p.category_id
                   FROM parts p {$join} WHERE " . implode(' AND ', $where);

        // Cached for the day, per category: the image probe below makes real HTTP
        // round-trips, so it must not run on every pill click.
        $ckey = 'featured_cache_' . ($cat !== '' ? substr(md5($cat), 0, 16) : 'all');
        $cst = $this->db()->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $cst->execute([$ckey]);
        $c = ($raw = $cst->fetchColumn()) ? json_decode((string) $raw, true) : null;
        if (is_array($c) && (int) ($c['t'] ?? 0) > time() - 86400) {
            $this->json($c['rows'] ?? []);
            return;
        }

        // Over-fetch 32: ~3% of primary images 404 on the CDN and get dropped below.
        mt_srand((int) date('Ymd') + crc32($cat));
        [$lo, $hi] = $this->db()->query("SELECT MIN(id), MAX(id) FROM parts")->fetch(\PDO::FETCH_NUM);
        $rows = [];
        if ($hi) {
            $stmt = $this->db()->prepare($base . " AND p.id >= :r ORDER BY p.id LIMIT 32");
            foreach ($args as $k => $v) { $stmt->bindValue($k, $v); }
            $stmt->bindValue(':r', mt_rand((int) $lo, (int) $hi), \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }
        if (count($rows) < 32) {          // ran off the end of the table — take from the start
            $stmt = $this->db()->prepare($base . " ORDER BY p.id LIMIT 32");
            foreach ($args as $k => $v) { $stmt->bindValue($k, $v); }
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }
        $rows = with_live_images($rows, 8);
        foreach ($rows as &$r) {
            $r['img']   = img_url($r['primary_image_path']);
            $r['price'] = price_tag($r['price'], isset($r['category_id']) ? (int) $r['category_id'] : null); unset($r['category_id']);
            unset($r['primary_image_path']);
        }
        unset($r);
        $this->db()->prepare("INSERT INTO settings (`key`,`value`) VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
             ->execute([$ckey, json_encode(['rows' => $rows, 't' => time()])]);
        $this->json($rows);
    }

    /** Product grid as JSON, for the shop-page drill. Mirrors
     *  CatalogController::products() filtering exactly — EXISTS rather than a
     *  fitment join, so no DISTINCT and no temp table over the whole catalog. */
    public function products(): void
    {
        $per   = 24;
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $veh   = trim((string) ($_GET['vehicle'] ?? ''));
        $cat   = trim((string) ($_GET['category'] ?? ''));
        $brand = trim((string) ($_GET['brand'] ?? ''));
        $q     = trim((string) ($_GET['q'] ?? ''));

        $joins = '';
        $where = ['p.price > 0', 'p.primary_image_path IS NOT NULL'];
        $args  = [];
        if ($q !== '') {
            [$sql, $sargs] = part_search_clause($q);
            $where[] = $sql;
            $args += $sargs;
        }
        if ($veh !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM part_fitment pf
                                  JOIN vehicles v ON v.id = pf.vehicle_id
                                 WHERE pf.part_id = p.id AND v.slug = :veh)';
            $args[':veh'] = $veh;
        }
        if ($cat !== '') {
            $joins .= " JOIN categories c ON c.id = p.category_id
                        JOIN categories g ON g.id = COALESCE(c.parent_id, c.id) ";
            $where[] = '(c.slug = :cat OR g.slug = :cat2)';
            $args[':cat'] = $cat;
            $args[':cat2'] = $cat;
        }
        if ($brand !== '') {
            $where[] = 'b.slug = :brand';
            $args[':brand'] = $brand;
        }

        $stmt = $this->db()->prepare(
            "SELECT p.slug, p.name, p.price, p.primary_image_path, p.category_id
               FROM parts p
          LEFT JOIN brands b ON b.id = p.brand_id
              {$joins}
              WHERE " . implode(' AND ', $where) . "
           ORDER BY p.id
              LIMIT :lim OFFSET :off"
        );
        foreach ($args as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':lim', $per + 1, \PDO::PARAM_INT);
        $stmt->bindValue(':off', ($page - 1) * $per, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $more = count($rows) > $per;
        if ($more) { array_pop($rows); }
        foreach ($rows as &$r) {
            $r['img']   = img_url($r['primary_image_path']);
            $r['price'] = price_tag($r['price'], isset($r['category_id']) ? (int) $r['category_id'] : null); unset($r['category_id']);
            unset($r['primary_image_path']);
        }
        unset($r);
        $this->json(['rows' => $rows, 'more' => $more, 'page' => $page]);
    }

    // ---- Catalog tree (RockAuto-style expandable navigation) ----

    /** Level 1: every make that has vehicles, alphabetical, with vehicle counts. */
    public function treeMakes(): void
    {
        $rows = $this->db()->query(
            "SELECT m.name, m.slug, COUNT(DISTINCT v.id) AS n,
                    GROUP_CONCAT(DISTINCT v.market) AS mk
               FROM makes m JOIN vehicles v ON v.make_id = m.id
              GROUP BY m.id ORDER BY m.name"
        )->fetchAll();
        $this->json(array_map([self::class, 'withMarkets'], $rows));
    }

    /** Split a make's GROUP_CONCAT of per-vehicle market CSVs into a deduped,
     *  ordered list of market codes (the flags RockAuto shows: US, CA, MX …). */
    private static function withMarkets(array $r): array
    {
        $order = ['US' => 0, 'CA' => 1, 'MX' => 2];
        $codes = array_values(array_unique(array_filter(array_map(
            'trim', explode(',', (string) ($r['mk'] ?? ''))))));
        usort($codes, fn($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b));
        unset($r['mk']);
        $r['markets'] = $codes;
        return $r;
    }

    /** Level 2: years for a make, optionally narrowed to one model (newest first).
     *  The catalog drills Make -> Model -> Year, so `model` filters this list. */
    public function treeYears(): void
    {
        $make  = (string) ($_GET['make'] ?? '');
        $model = (string) ($_GET['model'] ?? '');
        $stmt = $this->db()->prepare(
            "SELECT DISTINCT v.`year`
               FROM vehicles v
               JOIN makes m   ON m.id = v.make_id
          LEFT JOIN models mo ON mo.id = v.model_id
              WHERE m.slug = :make AND (:m0 = '' OR mo.slug = :m1)
              ORDER BY v.`year` DESC"
        );
        $stmt->execute([':make' => $make, ':m0' => $model, ':m1' => $model]);
        $this->json(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
    }

    /** Level 5: part GROUPS fitting a vehicle (RockAuto's "Brake & Wheel Hub" tier).
     *  Parts hang off a leaf part-type whose parent is the group; a category with no
     *  parent is its own group. */
    public function treeGroups(): void
    {
        $stmt = $this->db()->prepare(
            "SELECT g.name, g.slug, COUNT(DISTINCT p.id) AS n
               FROM vehicles v
               JOIN part_fitment pf ON pf.vehicle_id = v.id
               JOIN parts p         ON p.id = pf.part_id
               JOIN categories c    ON c.id = p.category_id
               JOIN categories g    ON g.id = COALESCE(c.parent_id, c.id)
              WHERE v.slug = ?
                AND NOT EXISTS (SELECT 1 FROM vehicle_group_suppressed s
                                WHERE s.vehicle_id = v.id AND s.group_id = g.id)
              GROUP BY g.id ORDER BY g.name"
        );
        $stmt->execute([(string) ($_GET['vehicle'] ?? '')]);
        $this->json($stmt->fetchAll());
    }

    /** Level 6: part-types inside one group, for a vehicle ("Brake Fluid"). */
    public function treeCategories(): void
    {
        $stmt = $this->db()->prepare(
            "SELECT c.name, c.slug, COUNT(DISTINCT p.id) AS n
               FROM vehicles v
               JOIN part_fitment pf ON pf.vehicle_id = v.id
               JOIN parts p         ON p.id = pf.part_id
               JOIN categories c    ON c.id = p.category_id
               JOIN categories g    ON g.id = COALESCE(c.parent_id, c.id)
              WHERE v.slug = :veh AND g.slug = :grp
                AND NOT EXISTS (SELECT 1 FROM vehicle_group_suppressed s
                                WHERE s.vehicle_id = v.id AND s.group_id = g.id)
              GROUP BY c.id ORDER BY c.name"
        );
        $stmt->execute([':veh' => (string) ($_GET['vehicle'] ?? ''),
                        ':grp' => (string) ($_GET['group'] ?? '')]);
        $this->json($stmt->fetchAll());
    }

    /** Level 7 (leaf): parts of a part-type fitting a vehicle.
     *  Out-of-stock (price IS NULL) sorts last, as RockAuto does. */
    public function treeParts(): void
    {
        $stmt = $this->db()->prepare(
            "SELECT p.sku, p.part_number, p.name, p.price, p.core_charge,
                    p.primary_image_path AS img, b.name AS brand, pf.note
               FROM vehicles v
               JOIN part_fitment pf ON pf.vehicle_id = v.id
               JOIN parts p         ON p.id = pf.part_id
          LEFT JOIN brands b        ON b.id = p.brand_id
              WHERE v.slug = :veh AND p.category_id =
                    (SELECT id FROM categories WHERE slug = :cat)
                AND NOT EXISTS (SELECT 1 FROM vehicle_group_suppressed s
                                JOIN categories cc ON cc.slug = :cat
                                WHERE s.vehicle_id = v.id
                                  AND s.group_id = COALESCE(cc.parent_id, cc.id))
              ORDER BY (p.price IS NULL), b.name, p.price"
        );
        $stmt->execute([':veh' => (string) ($_GET['vehicle'] ?? ''),
                        ':cat' => (string) ($_GET['category'] ?? '')]);
        $rows = $stmt->fetchAll();
        // Customer-facing prices carry the reseller markup; core deposit does not.
        foreach ($rows as &$r) {
            $r['img']   = img_url($r['img'] ?? '');
            $r['price'] = sell_price($r['price']);   // NULL (OOS) passes through
        }
        unset($r);
        $this->json($rows);
    }
}
