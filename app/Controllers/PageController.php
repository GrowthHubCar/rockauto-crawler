<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/** Static-ish marketing pages. Numbers come from the live catalog rather than
 *  being written into the copy, so the pages cannot drift from reality. */
final class PageController extends Controller
{
    public function about(): void
    {
        $this->render('about', [
            'stats'  => $this->catalogFacts(),
            'showFaq' => true,
        ], 'About Us — Supreme Spare Parts');
    }

    /** Biggest brands by sellable part count, for the "brands we stock" strip.
     *  Reuses the brands page's daily cache — grouping ~1M parts is too slow live. */
    private function topBrands(int $n): array
    {
        try {
            $db  = $this->db();
            $raw = $db->query("SELECT `value` FROM settings WHERE `key`='brands_cache'")->fetchColumn();
            $c   = $raw ? json_decode((string) $raw, true) : null;
            $all = is_array($c) ? ($c['brands'] ?? []) : [];
            if (!$all) { return []; }
            usort($all, fn($a, $b) => (int) $b['n'] <=> (int) $a['n']);
            return array_slice($all, 0, $n);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function contact(): void
    {
        $sent = isset($_GET['sent']);
        $this->render('contact', [
            'sent' => $sent, 'errors' => [], 'old' => [],
            'showFaq' => true,
        ], 'Contact Us — Supreme Spare Parts');
    }

    /** Store a contact enquiry. Server-side validated; the honeypot catches the
     *  bulk of bot posts without putting a CAPTCHA in a real customer's way. */
    public function contactSubmit(): void
    {
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last  = trim((string) ($_POST['last_name'] ?? ''));
        $old = [
            'first_name' => $first,
            'last_name'  => $last,
            'name'    => trim($first . ' ' . $last),
            'email'   => trim((string) ($_POST['email'] ?? '')),
            'phone'   => trim((string) ($_POST['phone'] ?? '')),
            'vehicle' => trim((string) ($_POST['vehicle'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
        ];

        // hidden field a human never fills in
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $this->redirect('/contact?sent=1');
            return;
        }

        $errors = [];
        if ($first === '' || mb_strlen($first) > 60) { $errors['first_name'] = 'First name is required.'; }
        if ($last === '' || mb_strlen($last) > 60)   { $errors['last_name'] = 'Last name is required.'; }
        if ($old['email'] === '' || mb_strlen($old['email']) > 190
            || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address so we can reply.';
        }
        if (mb_strlen($old['phone']) > 60)    { $errors['phone'] = 'That phone number is too long.'; }
        if (mb_strlen($old['vehicle']) > 190) { $errors['vehicle'] = 'That vehicle or VIN is too long.'; }
        if (mb_strlen($old['message']) < 10)  { $errors['message'] = 'Give us a little more detail (10 characters or more).'; }
        if (mb_strlen($old['message']) > 5000) { $errors['message'] = 'That message is too long (5000 characters max).'; }

        if ($errors) {
            $this->render('contact', ['sent' => false, 'errors' => $errors, 'old' => $old,
                'showFaq' => true], 'Contact Us — Supreme Spare Parts');
            return;
        }

        try {
            $this->db()->prepare(
                "INSERT INTO contact_messages (name, email, phone, vehicle, message, ip)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([
                $old['name'], $old['email'],
                $old['phone'] !== '' ? $old['phone'] : null,
                $old['vehicle'] !== '' ? $old['vehicle'] : null,
                $old['message'],
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            ]);
        } catch (\Throwable $e) {
            $this->render('contact', [
                'sent'   => false,
                'errors' => ['message' => 'We could not send that just now. Please try again, or message us on WhatsApp.'],
                'old'    => $old,
                'showFaq' => true,
            ], 'Contact Us — Supreme Spare Parts');
            return;
        }

        // redirect after POST so a refresh cannot resubmit
        $this->redirect('/contact?sent=1');
    }

    /** Cached daily — these are COUNT(*) over millions of rows. */
    private function catalogFacts(): array
    {
        $zero = ['parts' => 0, 'fitments' => 0, 'vehicles' => 0, 'makes' => 0, 'brands' => 0];
        try {
            $db = $this->db();
            $raw = $db->query("SELECT `value` FROM settings WHERE `key`='about_cache'")->fetchColumn();
            $c = $raw ? json_decode((string) $raw, true) : null;
            if (is_array($c) && (int) ($c['t'] ?? 0) > time() - 86400) {
                return $c;
            }
            $out = [
                'parts'    => (int) $db->query("SELECT COUNT(*) FROM parts")->fetchColumn(),
                'fitments' => (int) $db->query("SELECT COUNT(*) FROM part_fitment")->fetchColumn(),
                'vehicles' => (int) $db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn(),
                'makes'    => (int) $db->query("SELECT COUNT(*) FROM makes")->fetchColumn(),
                'brands'   => (int) $db->query("SELECT COUNT(*) FROM brands")->fetchColumn(),
                't'        => time(),
            ];
            $db->prepare("INSERT INTO settings (`key`,`value`) VALUES ('about_cache', ?)
                          ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
               ->execute([json_encode($out)]);
            return $out;
        } catch (\Throwable $e) {
            return $zero;
        }
    }
}
