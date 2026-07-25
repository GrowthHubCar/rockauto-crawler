<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;

class CategoryController extends AdminController
{
    public function index(): void
    {
        $db = $this->db();
        [$limit, $offset, $page] = $this->pageWindow(50);
        $q = trim((string) ($_GET['q'] ?? ''));
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = "WHERE c.name LIKE :q OR c.slug LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }

        $cnt = $db->prepare("SELECT COUNT(*) AS n FROM categories c $where");
        $cnt->execute($params);
        $total = (int) $cnt->fetch()['n'];

        $stmt = $db->prepare(
            "SELECT c.id, c.parent_id, c.name, c.slug, c.position, c.is_active, c.commission_pct,
                    pc.name AS parent_name,
                    (SELECT COUNT(*) FROM parts p WHERE p.category_id = c.id) AS parts
               FROM categories c
          LEFT JOIN categories pc ON pc.id = c.parent_id
               $where
              ORDER BY c.slug LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $categories = $stmt->fetchAll();

        // Full list (unpaginated) for the parent dropdowns.
        $parents = $db->query("SELECT id, name, slug FROM categories ORDER BY slug")->fetchAll();
        $this->adminRender('categories/index',
            ['categories' => $categories, 'parents' => $parents, 'q' => $q, 'page' => $page,
             'total' => $total, 'perPage' => $limit, 'csrf' => Auth::token(), '_active' => 'categories',
             'defaultPct' => (string) (setting('reseller_markup_pct', '0') ?? '0')],
            'Categories');
    }

    public function store(): void
    {
        $this->requireCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $parent = ($_POST['parent_id'] ?? '') !== '' ? (int) $_POST['parent_id'] : null;
        if ($name === '') { $this->flash('error', 'Category name required.'); $this->redirect('/admin/categories'); }
        try {
            $this->db()->prepare(
                "INSERT INTO categories (parent_id, name, slug, position) VALUES (?, ?, ?, ?)"
            )->execute([$parent, $name, $this->slugify($name, $parent), (int) ($_POST['position'] ?? 0)]);
            $this->flash('ok', 'Category added.');
        } catch (\PDOException $e) {
            $this->flash('error', 'Duplicate slug or invalid category.');
        }
        $this->redirect('/admin/categories');
    }

    public function update(string $id): void
    {
        $this->requireCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $parent = ($_POST['parent_id'] ?? '') !== '' ? (int) $_POST['parent_id'] : null;
        if ($parent === (int) $id) { $parent = null; } // can't be its own parent
        $active = isset($_POST['is_active']) ? 1 : 0;
        // Commission: blank = inherit the store default; a number (incl. 0) is this
        // category's own rate. Clamp negatives so we never sell below cost.
        $craw = str_replace([',', '%', ' '], '', (string) ($_POST['commission_pct'] ?? ''));
        $commission = ($craw === '' || !is_numeric($craw)) ? null : max(0.0, (float) $craw);
        if ($name === '') { $this->flash('error', 'Category name required.'); $this->redirect('/admin/categories'); }
        $this->db()->prepare(
            "UPDATE categories SET parent_id = ?, name = ?, position = ?, is_active = ?, commission_pct = ? WHERE id = ?"
        )->execute([$parent, $name, (int) ($_POST['position'] ?? 0), $active, $commission, (int) $id]);
        $this->flash('ok', 'Category updated.');
        $this->redirect('/admin/categories');
    }

    public function delete(string $id): void
    {
        $this->requireCsrf();
        $this->db()->prepare("DELETE FROM categories WHERE id = ?")->execute([(int) $id]);
        $this->flash('ok', 'Category deleted.');
        $this->redirect('/admin/categories');
    }

    private function slugify(string $name, ?int $parent): string
    {
        $base = strtolower(trim($name));
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', $base), '-') ?: 'category';
        // categories.slug is globally unique; suffix with parent id to reduce collisions.
        return $parent ? $base . '-' . $parent : $base;
    }
}
