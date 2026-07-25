<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/** Storefront newsletter signup (homepage form). */
class NewsletterController extends Controller
{
    public function subscribe(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['ok' => false, 'error' => 'Enter a valid email address.']);
            return;
        }
        try {
            // Re-subscribing is a no-op rather than an error the visitor has to see.
            $this->db()->prepare(
                "INSERT INTO newsletter_subscribers (email, source) VALUES (?, 'homepage')
                 ON DUPLICATE KEY UPDATE email = email"
            )->execute([$email]);
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Could not subscribe right now.']);
        }
    }
}
