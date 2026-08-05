<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class CatalogController extends Controller
{
    /** Browse a make: its vehicles grouped by model/year -> vehicle pages. */
    public function make(string $makeSlug): void
    {
        $db = $this->db();
        $stmt = $db->prepare("SELECT id, name, slug FROM makes WHERE slug = ?");
        $stmt->execute([$makeSlug]);
        $make = $stmt->fetch();
        if (!$make) { $this->notFoundResponse(); return; }

        $stmt = $db->prepare(
            "SELECT v.slug, v.`year`, v.trim, mo.name AS model,
                    COALESCE(e.name,'Standard') AS engine
               FROM vehicles v
               JOIN models mo ON mo.id = v.model_id
          LEFT JOIN engines e ON e.id = v.engine_id
              WHERE v.make_id = ?
              ORDER BY mo.name, v.`year` DESC"
        );
        $stmt->execute([$make['id']]);
        $vehicles = $stmt->fetchAll();

        $this->render('make', compact('make', 'vehicles'),
            $make['name'] . ' Parts — Supreme Spare Parts');
    }

    /** A selected vehicle: show the categories that have parts fitting it. */
    public function vehicle(string $slug): void
    {
        $db = $this->db();
        $vehicle = $this->loadVehicle($slug);
        if (!$vehicle) { $this->notFoundResponse(); return; }

        // Leaf part-types fitting this vehicle, with counts, tagged with their
        // RockAuto parent group (e.g. "Wiper & Washer") so the view can group them.
        $stmt = $db->prepare(
            "SELECT c.name, c.slug, COUNT(DISTINCT p.id) AS n,
                    COALESCE(par.name, c.name)               AS group_name,
                    COALESCE(par.position, c.position, 9999) AS group_pos
               FROM part_fitment pf
               JOIN parts p      ON p.id = pf.part_id
               JOIN categories c ON c.id = p.category_id
          LEFT JOIN categories par ON par.id = c.parent_id
              WHERE pf.vehicle_id = :vid
              GROUP BY c.id
              ORDER BY group_pos, group_name, COALESCE(c.position, 9999), c.name"
        );
        $stmt->execute([':vid' => $vehicle['id']]);
        $categories = $stmt->fetchAll();

        $this->render('vehicle', compact('vehicle', 'categories'),
            $this->vehicleLabel($vehicle) . ' Parts — Supreme Spare Parts');
    }

    /** Parts of one category fitting one vehicle. */
    public function vehicleCategory(string $slug, string $catSlug): void
    {
        $db = $this->db();
        $vehicle = $this->loadVehicle($slug);
        if (!$vehicle) { $this->notFoundResponse(); return; }

        $stmt = $db->prepare("SELECT id, name, slug FROM categories WHERE slug = ?");
        $stmt->execute([$catSlug]);
        $category = $stmt->fetch();
        if (!$category) { $this->notFoundResponse(); return; }

        // Each part carries its variant span (RockAuto's inventory-tier / "Choose
        // Type" dropdown) and its style sub-group ("Beam (Standard)", "Winter"...)
        // so the list can surface multiple prices and section like RockAuto.
        $stmt = $db->prepare(
            "SELECT p.sku, p.part_number, p.name, p.price, p.core_charge,
                    p.primary_image_path, b.name AS brand, pf.note,
                    (SELECT COUNT(*)   FROM part_variants v WHERE v.part_id = p.id) AS n_variants,
                    (SELECT MIN(v.price) FROM part_variants v
                        WHERE v.part_id = p.id AND v.price IS NOT NULL) AS vmin,
                    (SELECT MAX(v.price) FROM part_variants v
                        WHERE v.part_id = p.id AND v.price IS NOT NULL) AS vmax,
                    (SELECT a.`value` FROM part_attributes a
                        WHERE a.part_id = p.id AND a.name = 'Style' LIMIT 1) AS style
               FROM part_fitment pf
               JOIN parts p  ON p.id = pf.part_id
          LEFT JOIN brands b ON b.id = p.brand_id
              WHERE pf.vehicle_id = :vid AND p.category_id = :cid
              ORDER BY (style IS NULL), style, (p.price IS NULL), p.price, b.name"
        );
        $stmt->execute([':vid' => $vehicle['id'], ':cid' => $category['id']]);
        $parts = $stmt->fetchAll();

        $this->render('parts', compact('vehicle', 'category', 'parts'),
            $category['name'] . ' — ' . $this->vehicleLabel($vehicle) . ' — Supreme Spare Parts');
    }

    /** Legacy route: search is part of the shop page now. Redirect so old
     *  links, bookmarks and the previous header form keep working. */
    public function search(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $this->redirect('/products' . ($q !== '' ? '?q=' . urlencode($q) : ''));
    }

    /** Every brand we carry, with sellable-part counts. Cached daily — grouping
     *  ~1M parts by brand is too slow to run on every page view. */
    public function brands(): void
    {
        $brands = [];
        try {
            $db = $this->db();
            $raw = $db->query("SELECT `value` FROM settings WHERE `key`='brands_cache'")->fetchColumn();
            $c = $raw ? json_decode((string) $raw, true) : null;
            if (is_array($c) && (int) ($c['t'] ?? 0) > time() - 86400) {
                $brands = $c['brands'] ?? [];
            } else {
                $brands = $db->query(
                    "SELECT b.name, b.slug, COUNT(*) AS n
                       FROM parts p JOIN brands b ON b.id = p.brand_id
                      WHERE p.price > 0 AND p.primary_image_path IS NOT NULL
                      GROUP BY b.id ORDER BY b.name"
                )->fetchAll();
                $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('brands_cache', ?)
                              ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                   ->execute([json_encode(['brands' => $brands, 't' => time()])]);
            }
        } catch (\Throwable $e) {
            $brands = [];
        }
        $this->render('brands', compact('brands'), 'Brands — Supreme Spare Parts');
    }

    /** All Products — a paginated grid of everything sellable. */
    public function products(): void
    {
        $per  = 24;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * $per;
        $veh   = trim((string) ($_GET['vehicle'] ?? ''));   // set by the catalog drill
        $cat   = trim((string) ($_GET['category'] ?? ''));  // group OR leaf sub-category
        $brand = trim((string) ($_GET['brand'] ?? ''));     // set from the brands page
        $q     = trim((string) ($_GET['q'] ?? ''));         // free-text search

        $joins = '';
        $where = ['p.price > 0', 'p.primary_image_path IS NOT NULL'];
        $args  = [];
        if ($q !== '') {
            [$sql, $sargs] = part_search_clause($q);
            $where[] = $sql;
            $args += $sargs;
        }
        // EXISTS, not a join: part_fitment is 1:many, so joining it would multiply
        // rows and force a DISTINCT — and DISTINCT here costs a full temp table over
        // every matching part (~16s) before LIMIT can apply. EXISTS short-circuits.
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
            $args[':cat']  = $cat;
            $args[':cat2'] = $cat;
        }
        if ($brand !== '') {
            $where[] = 'b.slug = :brand';
            $args[':brand'] = $brand;
        }

        // Fetch one extra row: that tells us whether a "next" link is warranted
        // without a COUNT(*) over ~1M rows on every page view.
        $stmt = $this->db()->prepare(
            "SELECT p.slug, p.name, p.price, p.primary_image_path, p.category_id, b.name AS brand
               FROM parts p
          LEFT JOIN brands b ON b.id = p.brand_id
              {$joins}
              WHERE " . implode(' AND ', $where) . "
           ORDER BY p.id
              LIMIT :lim OFFSET :off"
        );
        foreach ($args as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':lim', $per + 1, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $off, \PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll();

        $more = count($products) > $per;
        if ($more) { array_pop($products); }

        // Human label for whatever the drill narrowed to, plus a $preset the
        // shop page uses to pre-select the drill comboboxes when arriving with a
        // filter in the URL (e.g. the "Shop parts" link from a part's fitment).
        $filter = '';
        $preset = ['make' => '', 'model' => '', 'year' => '', 'engine' => '',
                   'cat' => '', 'catL' => '', 'sub' => '', 'subL' => ''];
        if ($veh !== '' && ($v = $this->loadVehicle($veh))) {
            $filter = $this->vehicleLabel($v);
            $preset['make']    = $v['make_slug'];
            $preset['makeL']   = $v['make'];
            $preset['model']   = $v['model_slug'];
            $preset['modelL']  = $v['model'];
            $preset['year']    = (string) $v['year'];
            $preset['engine']  = $v['slug'];
            $preset['engineL'] = trim(($v['engine'] ?: 'Standard') . ($v['trim'] ? ' — ' . $v['trim'] : ''));
            // Remember the drilled car for 7 days: every drill (home selects, VIN,
            // chips, fitment links) lands here, so this is the one place that always
            // fires. The homepage "Resume" strip reads this cookie.
            setcookie('sp_last_vehicle',
                json_encode(['slug' => $v['slug'], 'label' => $filter]),
                ['expires' => time() + 7 * 86400, 'path' => '/', 'samesite' => 'Lax']);
        }
        if ($cat !== '') {
            $cn = $this->db()->prepare("SELECT name, parent_id FROM categories WHERE slug = ? LIMIT 1");
            $cn->execute([$cat]);
            if ($crow = $cn->fetch()) {
                $filter = trim(($filter !== '' ? $filter . ' — ' : '') . $crow['name']);
                // a sub-category has a parent group; a top group sits in 'cat'
                if ($crow['parent_id']) { $preset['sub'] = $cat; $preset['subL'] = $crow['name']; }
                else { $preset['cat'] = $cat; $preset['catL'] = $crow['name']; }
            }
        }
        if ($brand !== '') {
            $bn = $this->db()->prepare("SELECT name FROM brands WHERE slug = ? LIMIT 1");
            $bn->execute([$brand]);
            if ($n = $bn->fetchColumn()) {
                $filter = trim(($filter !== '' ? $filter . ' — ' : '') . $n);
            }
        }

        $stats = $this->heroStats();

        if ($q !== '') {
            $filter = trim(($filter !== '' ? $filter . ' — ' : '') . '"' . $q . '"');
        }

        // Full-bleed hero, rendered by the layout before <main> so it spans the
        // whole viewport instead of being capped inside the content column.
        $pageHero = [
            'eyebrow' => 'Inventory',
            'h1'      => $filter !== '' ? $filter : 'Browse Our Range of Parts',
            'lead'    => '',
            'img'     => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1600&auto=format&fit=crop',
            'rail'    => ['Parts live' => $stats['parts'] ?? 0, 'Fitments' => $stats['fitments'] ?? 0,
                          'Vehicles'   => $stats['vehicles'] ?? 0],
        ];

        $this->render('products', compact('products', 'page', 'more', 'veh', 'cat', 'brand', 'q', 'filter', 'stats', 'pageHero', 'preset'),
            ($filter !== '' ? $filter . ' Parts' : 'All Products') . ' — Supreme Spare Parts');
    }

    /** Catalog figures for the shop hero rail. Shares the homepage's daily
     *  stats_cache rather than running its own COUNT(*) over ~1M rows. */
    private function heroStats(): array
    {
        $out = ['parts' => 0, 'fitments' => 0, 'vehicles' => 0, 'brands' => 0];
        try {
            $db  = $this->db();
            $raw = $db->query("SELECT `value` FROM settings WHERE `key`='stats_cache'")->fetchColumn();
            $c   = $raw ? json_decode((string) $raw, true) : null;
            if (is_array($c) && (int) ($c['t'] ?? 0) > time() - 86400) {
                $out['parts']    = (int) ($c['parts'] ?? 0);
                $out['fitments'] = (int) ($c['fitments'] ?? 0);
                $out['vehicles'] = (int) ($c['vehicles'] ?? 0);
            }
            if (!$out['parts']) {
                $out['parts'] = (int) $db->query("SELECT COUNT(*) FROM parts")->fetchColumn();
            }
            $out['brands'] = (int) $db->query("SELECT COUNT(*) FROM brands")->fetchColumn();
        } catch (\Throwable $e) {
            // leave zeros — the rail hides any figure it doesn't have
        }
        return $out;
    }

    // ---- helpers ----

    private function loadVehicle(string $slug): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT v.id, v.slug, v.`year`, v.trim,
                    m.name AS make, m.slug AS make_slug,
                    mo.name AS model, mo.slug AS model_slug,
                    e.name AS engine
               FROM vehicles v
               JOIN makes m   ON m.id = v.make_id
               JOIN models mo ON mo.id = v.model_id
          LEFT JOIN engines e ON e.id = v.engine_id
              WHERE v.slug = ?"
        );
        $stmt->execute([$slug]);
        $v = $stmt->fetch();
        return $v ?: null;
    }

    private function vehicleLabel(array $v): string
    {
        $bits = [$v['year'], $v['make'], $v['model']];
        if (!empty($v['engine'])) $bits[] = $v['engine'];
        if (!empty($v['trim']))   $bits[] = $v['trim'];
        return implode(' ', $bits);
    }
}
