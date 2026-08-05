<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class PartController extends Controller
{
    public function show(string $sku): void
    {
        $db = $this->db();

        $stmt = $db->prepare(
            "SELECT p.*, b.name AS brand, b.slug AS brand_slug, c.name AS category, c.slug AS category_slug
               FROM parts p
          LEFT JOIN brands b     ON b.id = p.brand_id
          LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.sku = ?"
        );
        $stmt->execute([$sku]);
        $part = $stmt->fetch();
        if (!$part) { $this->notFoundResponse(); return; }

        // GROUP BY path: RockAuto serves the same photo under carousel-variant URLs
        // that normalize to one local file, so dedupe by path or it renders twice.
        // Exclude raw rockauto.com hotlinks: our IP is blocked so they never load
        // (they'd flash then blank via onerror), and re-ingestion keeps re-adding
        // them alongside the self-hosted copy — only ever show the local/CDN image.
        $images = $db->prepare("SELECT path, MIN(alt) AS alt FROM part_images WHERE part_id = ? AND path NOT LIKE 'http%rockauto%' GROUP BY path ORDER BY MIN(position)");
        $images->execute([$part['id']]);
        $images = $images->fetchAll();

        $attrs = $db->prepare("SELECT name, `value` FROM part_attributes WHERE part_id = ? ORDER BY id");
        $attrs->execute([$part['id']]);
        $attrs = $attrs->fetchAll();

        // RockAuto's option dropdown (inventory tiers / "Choose Type" packs).
        // Default option first, then cheapest; out-of-stock options last.
        $variants = $db->prepare(
            "SELECT id, code, `type`, price, core_charge, oos, is_default, pack_size, pack_total
               FROM part_variants WHERE part_id = ?
              ORDER BY oos ASC, is_default DESC, (price IS NULL), price ASC, position ASC"
        );
        $variants->execute([$part['id']]);
        $variants = $variants->fetchAll();

        // Fitment can be huge (one coolant fits 13k+ vehicles), so the initial page
        // only carries the COUNT for the tab label — the rows load on demand,
        // paginated, via fitment() below. Keeps the part page fast for every part.
        $fc = $db->prepare("SELECT COUNT(*) FROM part_fitment WHERE part_id = ?");
        $fc->execute([$part['id']]);
        $fitCount = (int) $fc->fetchColumn();

        $stock = $db->prepare("SELECT SUM(quantity) AS qty FROM inventory WHERE part_id = ?");
        $stock->execute([$part['id']]);
        $stock = (int) ($stock->fetch()['qty'] ?? 0);

        $this->render('part', compact('part', 'images', 'attrs', 'fitCount', 'stock', 'variants'),
            $part['name'] . ' — Supreme Spare Parts');
    }

    /** GET /part/{sku}/fitment?page=N — paginated "fits these vehicles" list (JSON). */
    public function fitment(string $sku): void
    {
        $db = $this->db();
        $pid = $db->prepare("SELECT id FROM parts WHERE sku = ?");
        $pid->execute([$sku]);
        $partId = (int) ($pid->fetchColumn() ?: 0);
        if (!$partId) { $this->json(['ok' => false, 'rows' => [], 'more' => false]); return; }

        $per  = 25;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * $per;

        // STRAIGHT_JOIN forces the plan to start from part_fitment (fast via the
        // part_id index); without it MySQL scans all ~4,600 models first + filesort.
        $stmt = $db->prepare(
            "SELECT v.slug, v.`year`, v.trim, m.name AS make, mo.name AS model,
                    COALESCE(e.name,'') AS engine, pf.note
               FROM part_fitment pf
      STRAIGHT_JOIN vehicles v ON v.id = pf.vehicle_id
      STRAIGHT_JOIN makes m    ON m.id = v.make_id
      STRAIGHT_JOIN models mo  ON mo.id = v.model_id
          LEFT JOIN engines e  ON e.id = v.engine_id
              WHERE pf.part_id = :pid
              ORDER BY m.name, mo.name, v.`year` DESC
              LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':pid', $partId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $per + 1, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $off, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $more = count($rows) > $per;
        if ($more) { array_pop($rows); }
        $base = rtrim($this->url('/'), '/');
        foreach ($rows as &$r) {
            // shop page with this exact vehicle selected (filters the grid to its parts)
            $r['url']   = $base . '/products?vehicle=' . rawurlencode($r['slug']);
            $r['year']  = (int) $r['year'];
            $r['model'] = $r['model'] . ($r['trim'] ? ' ' . $r['trim'] : '');
            unset($r['slug'], $r['trim']);
        }
        unset($r);
        $this->json(['ok' => true, 'rows' => $rows, 'page' => $page, 'more' => $more]);
    }
}
