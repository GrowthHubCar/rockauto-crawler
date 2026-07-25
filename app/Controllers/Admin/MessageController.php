<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

/** Contact-form enquiries captured by the storefront. */
class MessageController extends AdminController
{
    public function index(): void
    {
        $per  = 25;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * $per;
        $status = (string) ($_GET['status'] ?? '');
        $valid  = ['new', 'read', 'archived'];
        $where  = in_array($status, $valid, true) ? 'WHERE status = :st' : '';

        $cnt = $this->db()->prepare("SELECT COUNT(*) FROM contact_messages {$where}");
        if ($where) { $cnt->bindValue(':st', $status); }
        $cnt->execute();
        $total = (int) $cnt->fetchColumn();

        $stmt = $this->db()->prepare(
            "SELECT id, name, email, phone, vehicle, message, status, created_at
               FROM contact_messages {$where}
           ORDER BY created_at DESC, id DESC
              LIMIT :lim OFFSET :off"
        );
        if ($where) { $stmt->bindValue(':st', $status); }
        $stmt->bindValue(':lim', $per, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $off, \PDO::PARAM_INT);
        $stmt->execute();

        $counts = ['new' => 0, 'read' => 0, 'archived' => 0];
        foreach ($this->db()->query(
            "SELECT status, COUNT(*) n FROM contact_messages GROUP BY status"
        )->fetchAll() as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }

        $this->adminRender('messages', [
            'msgs'    => $stmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
            'per'     => $per,
            'status'  => $status,
            'counts'  => $counts,
            '_active' => 'messages',
        ], 'Messages');
    }

    /** Mark read / archived / back to new. */
    public function status(string $id): void
    {
        $to = (string) ($_POST['status'] ?? '');
        if (in_array($to, ['new', 'read', 'archived'], true)) {
            $this->db()->prepare("UPDATE contact_messages SET status = ? WHERE id = ?")
                 ->execute([$to, (int) $id]);
        }
        $this->redirect('/admin/messages' . (($s = $_POST['back'] ?? '') ? '?status=' . urlencode((string) $s) : ''));
    }

    public function delete(string $id): void
    {
        $this->db()->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([(int) $id]);
        $this->redirect('/admin/messages');
    }
}
