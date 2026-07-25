<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
{
    public function index(): void
    {
        // The homepage is the full "Supreme Autos" design — a standalone page
        // with its own head/header/footer, so render it directly (no layout).
        echo $this->capture('home', [
            'base'     => rtrim($this->url(''), '/'),
            'cartN'    => \App\Core\Cart::headerCount(),
            'featured' => $this->featuredParts(8),
            'stats'    => $this->catalogStats(),
            'extras'   => $this->homeExtras(),
            'recent'   => $this->recentVehicle(),
        ]);
    }

    /** Last drilled vehicle from its 7-day cookie (set in CatalogController::products).
     *  Drives the "Resume" strip so it reflects the real last car, not stale storage. */
    private function recentVehicle(): ?array
    {
        $raw = $_COOKIE['sp_last_vehicle'] ?? '';
        $v = $raw !== '' ? json_decode((string) $raw, true) : null;
        return (is_array($v) && !empty($v['slug'])) ? $v : null;
    }

    /** Category pills + popular-vehicle chips. Both come from aggregate queries
     *  (the vehicle one groups 12M fitment rows), so the result is cached daily. */
    private function homeExtras(): array
    {
        try {
            $db = $this->db();
            $raw = $db->query("SELECT `value` FROM settings WHERE `key`='home_cache'")->fetchColumn();
            $c = $raw ? json_decode((string) $raw, true) : null;
            if (is_array($c) && (int) ($c['t'] ?? 0) > time() - 86400) {
                return $c;
            }

            $categories = $db->query(
                "SELECT g.slug, g.name
                   FROM parts p
                   JOIN categories c ON c.id = p.category_id
                   JOIN categories g ON g.id = COALESCE(c.parent_id, c.id)
                  WHERE p.price > 0 AND p.primary_image_path IS NOT NULL
                  GROUP BY g.id ORDER BY COUNT(*) DESC LIMIT 24"
            )->fetchAll();

            // Most-fitted vehicles, then deduped to one entry per model so the
            // row isn't three flavours of the same truck.
            $rows = $db->query(
                "SELECT m.slug AS make_slug, m.name AS make,
                        mo.slug AS model_slug, mo.name AS model, v.`year`, COUNT(*) AS n
                   FROM part_fitment pf
                   JOIN vehicles v ON v.id = pf.vehicle_id
                   JOIN makes m    ON m.id = v.make_id
                   JOIN models mo  ON mo.id = v.model_id
                  GROUP BY m.id, mo.id, v.`year`
                  ORDER BY n DESC LIMIT 400"
            )->fetchAll();

            $popular = [];
            $seen = [];
            foreach ($rows as $r) {
                $key = $r['make_slug'] . '/' . $r['model_slug'];
                if (isset($seen[$key])) { continue; }
                $seen[$key] = true;
                $popular[] = [
                    'make'  => $r['make_slug'],
                    'model' => $r['model_slug'],
                    'year'  => (int) $r['year'],
                    'label' => ucwords(strtolower($r['make'] . ' ' . $r['model'])) . ' ' . (int) $r['year'],
                ];
                if (count($popular) >= 24) { break; }
            }

            $out = ['categories' => $categories, 'popular' => $popular, 't' => time()];
            $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('home_cache', ?)
                          ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
               ->execute([json_encode($out)]);
            return $out;
        } catch (\Throwable $e) {
            return ['categories' => [], 'popular' => []];
        }
    }

    /** Headline counts for the hero. Uses InnoDB's row estimates rather than
     *  COUNT(*) — an exact count over 11M fitment rows would stall every page
     *  load, and these are only ever shown rounded (1M+ / 12M+ / 25K+). */
    private function catalogStats(): array
    {
        try {
            $db = $this->db();
            // Cached for a day: the exact counts below are too slow to run per request.
            $raw = $db->query("SELECT `value` FROM settings WHERE `key`='stats_cache'")->fetchColumn();
            $c = $raw ? json_decode((string) $raw, true) : null;
            if (is_array($c) && (int) ($c['t'] ?? 0) > time() - 86400) {
                return $c;
            }
            // Exact where it's affordable. InnoDB's row estimate undercounts badly
            // (it reported 919,806 parts against an actual 1,041,615), which would
            // render "920K+" instead of "1M+". The 11M-row fitment table is the one
            // place an exact COUNT(*) isn't worth it.
            $parts    = (int) $db->query("SELECT COUNT(*) FROM parts")->fetchColumn();
            $vehicles = (int) $db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
            $fitments = (int) $db->query("SELECT COUNT(*) FROM part_fitment")->fetchColumn();

            $out = ['parts' => $parts, 'fitments' => $fitments, 'vehicles' => $vehicles, 't' => time()];
            $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('stats_cache', ?)
                          ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
               ->execute([json_encode($out)]);
            return $out;
        } catch (\Throwable $e) {
            return ['parts' => 0, 'fitments' => 0, 'vehicles' => 0];
        }
    }

    /** A daily-rotating pick of sellable parts for the homepage grid.
     *  Seeded by the date, so the same set shows all day and changes at midnight.
     *  Sampling by random id keeps it on the primary key — ORDER BY RAND() over
     *  ~1M rows would table-scan on every page load. */
    private function featuredParts(int $n = 8): array
    {
        try {
            $db = $this->db();
            // Verified picks are cached for the day: the image probe below costs real
            // HTTP round-trips and must never run per page view.
            $raw = $db->query("SELECT `value` FROM settings WHERE `key`='featured_cache'")->fetchColumn();
            $c = $raw ? json_decode((string) $raw, true) : null;
            if (is_array($c) && (int) ($c['t'] ?? 0) > time() - 86400 && count($c['rows'] ?? []) >= $n) {
                return array_slice($c['rows'], 0, $n);
            }

            [$lo, $hi] = $db->query("SELECT MIN(id), MAX(id) FROM parts")->fetch(\PDO::FETCH_NUM);
            if (!$hi) { return []; }
            $stmt = $db->prepare(
                "SELECT p.slug, p.name, p.price, p.primary_image_path, p.category_id, b.name AS brand
                   FROM parts p
              LEFT JOIN brands b ON b.id = p.brand_id
                  WHERE p.primary_image_path IS NOT NULL AND p.price > 0 AND p.id >= :r
                  ORDER BY p.id LIMIT 1"
            );
            // Over-sample: ~3% of primary images 404 on the CDN, and those parts get
            // dropped below, so drawing exactly $n would leave short rows.
            mt_srand((int) date('Ymd'));
            $cand = [];
            $seen = [];
            for ($i = 0; $i < $n * 8 && count($cand) < $n * 3; $i++) {
                $stmt->execute([':r' => mt_rand((int) $lo, (int) $hi)]);
                $row = $stmt->fetch();
                if ($row && empty($seen[$row['slug']])) {
                    $seen[$row['slug']] = true;
                    $cand[] = $row;
                }
            }
            $out = with_live_images($cand, $n);

            $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('featured_cache', ?)
                          ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
               ->execute([json_encode(['rows' => $out, 't' => time()])]);
            return $out;
        } catch (\Throwable $e) {
            return [];   // never let the shop-window break the homepage
        }
    }

    public function notFound(): void
    {
        $this->notFoundResponse();
    }
}
