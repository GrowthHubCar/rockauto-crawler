<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;

class PartController extends AdminController
{
    public function index(): void
    {
        $db = $this->db();
        [$limit, $offset, $page] = $this->pageWindow(25);
        $q = trim((string) ($_GET['q'] ?? ''));

        $where = '';
        $params = [];
        if ($q !== '') {
            $where = "WHERE p.name LIKE :q OR p.part_number LIKE :q OR p.sku LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }

        // Exact COUNT(*) over ~1M parts costs ~4.7s. Only pay it when a search
        // actually narrows the set; unfiltered, use InnoDB's instant row estimate
        // (the pager never needs an exact last-page boundary on the full catalog).
        if ($q !== '') {
            $cntStmt = $db->prepare("SELECT COUNT(*) AS n FROM parts p $where");
            $cntStmt->execute($params);
            $total = (int) $cntStmt->fetch()['n'];
        } else {
            $total = (int) $db->query(
                "SELECT TABLE_ROWS FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'"
            )->fetchColumn();
        }

        // Whitelisted sort — column key never reaches SQL directly.
        $sortCols = ['name' => 'p.name', 'price' => 'p.price', 'status' => 'p.status', 'updated' => 'p.updated_at'];
        $sort = array_key_exists((string) ($_GET['sort'] ?? ''), $sortCols) ? (string) $_GET['sort'] : 'updated';
        $dir  = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $orderBy = $sortCols[$sort] . ' ' . $dir;

        $sql = "SELECT p.id, p.sku, p.part_number, p.name, p.price, p.status,
                       p.primary_image_path,
                       b.name AS brand, c.name AS category,
                       (SELECT COUNT(*) FROM part_fitment pf WHERE pf.part_id = p.id) AS fits
                  FROM parts p
             LEFT JOIN brands b     ON b.id = p.brand_id
             LEFT JOIN categories c ON c.id = p.category_id
                  $where
              ORDER BY $orderBy
                 LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $parts = $stmt->fetchAll();

        $this->adminRender('parts/index',
            ['parts' => $parts, 'q' => $q, 'page' => $page, 'total' => $total, 'perPage' => $limit,
             'sort' => $sort, 'dir' => strtolower($dir), '_active' => 'parts'],
            'Parts');
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $stmt = $this->db()->prepare("SELECT * FROM parts WHERE id = ?");
        $stmt->execute([(int) $id]);
        $part = $stmt->fetch();
        if (!$part) { $this->flash('error', 'Part not found.'); $this->redirect('/admin/parts'); }
        $this->form($part);
    }

    public function store(): void
    {
        $this->requireCsrf();
        $data = $this->collect();
        if ($err = $this->validate($data)) {
            $this->flash('error', $err);
            $this->redirect('/admin/parts/create');
        }
        $db = $this->db();
        try {
            $stmt = $db->prepare(
                "INSERT INTO parts (brand_id, category_id, part_number, sku, name, slug,
                                    description, price, core_charge, weight, status, primary_image_path)
                 VALUES (:brand,:cat,:pn,:sku,:name,:slug,:desc,:price,:core,:weight,:status,:img)"
            );
            $stmt->execute($this->bind($data));
            $this->flash('ok', 'Part created.');
        } catch (\PDOException $e) {
            error_log('[SupremeParts] part save failed: ' . $e->getMessage());
            $this->flash('error', 'Could not save — check for a duplicate SKU/slug or an invalid value.');
            $this->redirect('/admin/parts/create');
        }
        $this->redirect('/admin/parts');
    }

    public function update(string $id): void
    {
        $this->requireCsrf();
        $data = $this->collect();
        if ($err = $this->validate($data)) {
            $this->flash('error', $err);
            $this->redirect('/admin/parts/' . (int) $id . '/edit');
        }
        $db = $this->db();
        $bind = $this->bind($data);
        $bind[':id'] = (int) $id;
        try {
            $db->prepare(
                "UPDATE parts SET brand_id=:brand, category_id=:cat, part_number=:pn, sku=:sku,
                        name=:name, slug=:slug, description=:desc, price=:price, core_charge=:core,
                        weight=:weight, status=:status, primary_image_path=:img
                 WHERE id=:id"
            )->execute($bind);
            $this->flash('ok', 'Part updated.');
        } catch (\PDOException $e) {
            error_log('[SupremeParts] part save failed: ' . $e->getMessage());
            $this->flash('error', 'Could not save — check for a duplicate SKU/slug or an invalid value.');
            $this->redirect('/admin/parts/' . (int) $id . '/edit');
        }
        $this->redirect('/admin/parts');
    }

    public function delete(string $id): void
    {
        $this->requireCsrf();
        $this->db()->prepare("DELETE FROM parts WHERE id = ?")->execute([(int) $id]);
        $this->flash('ok', 'Part deleted.');
        $this->redirect('/admin/parts');
    }

    // ---- helpers ----

    private function form(?array $part): void
    {
        $db = $this->db();
        $brands = $db->query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
        $categories = $db->query("SELECT id, name, slug FROM categories ORDER BY slug")->fetchAll();
        $this->adminRender('parts/form',
            ['part' => $part, 'brands' => $brands, 'categories' => $categories,
             'csrf' => Auth::token(), '_active' => 'parts'],
            $part ? 'Edit Part' : 'New Part');
    }

    private function collect(): array
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $pn   = trim((string) ($_POST['part_number'] ?? ''));
        $sku  = trim((string) ($_POST['sku'] ?? ''));
        if ($sku === '' && $pn !== '') { $sku = $pn; }
        return [
            'brand_id'    => ($_POST['brand_id'] ?? '') !== '' ? (int) $_POST['brand_id'] : null,
            'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null,
            'part_number' => $pn,
            'sku'         => $sku,
            'name'        => $name,
            'slug'        => $this->slugify($name !== '' ? $name : $sku),
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'price'       => (float) ($_POST['price'] ?? 0),
            'core_charge' => (float) ($_POST['core_charge'] ?? 0),
            'weight'      => ($_POST['weight'] ?? '') !== '' ? (float) $_POST['weight'] : null,
            'status'      => in_array($_POST['status'] ?? '', ['active','inactive','discontinued'], true) ? $_POST['status'] : 'active',
            'primary_image_path' => trim((string) ($_POST['primary_image_path'] ?? '')) ?: null,
        ];
    }

    private function validate(array $d): ?string
    {
        if ($d['name'] === '')        return 'Name is required.';
        if ($d['part_number'] === '') return 'Part number is required.';
        if ($d['sku'] === '')         return 'SKU is required.';
        return null;
    }

    private function bind(array $d): array
    {
        return [
            ':brand'  => $d['brand_id'], ':cat' => $d['category_id'],
            ':pn'     => $d['part_number'], ':sku' => $d['sku'], ':name' => $d['name'],
            ':slug'   => $d['slug'], ':desc' => $d['description'], ':price' => $d['price'],
            ':core'   => $d['core_charge'], ':weight' => $d['weight'],
            ':status' => $d['status'], ':img' => $d['primary_image_path'],
        ];
    }

    private function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-') ?: 'part';
    }
}
