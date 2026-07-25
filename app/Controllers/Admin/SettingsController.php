<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;

/** Storefront settings — reseller markup + the social links shown in the footer. */
class SettingsController extends AdminController
{
    /** Contact details surfaced on the storefront (footer + "Talk to a specialist"). */
    private const CONTACT = [
        'whatsapp' => ['label' => 'WhatsApp number', 'hint' => 'Include the country code, e.g. +1 800 555 0110. Powers the "Talk to a specialist" button.'],
        'email'    => ['label' => 'Contact email',   'hint' => 'Shown in the storefront footer.'],
        'address'  => ['label' => 'Address',         'hint' => 'Shown in the storefront footer.'],
    ];

    public function index(): void
    {
        $pct = $this->db()->query(
            "SELECT `value` FROM settings WHERE `key` = 'reseller_markup_pct'"
        )->fetchColumn();

        $socials = [];
        foreach (social_networks() as $key => $label) {
            $socials[$key] = [
                'label' => $label,
                'value' => (string) (setting('social_' . $key, '') ?? ''),
            ];
        }
        $contact = [];
        foreach (self::CONTACT as $key => $meta) {
            $contact[$key] = $meta + ['value' => (string) (setting('contact_' . $key, '') ?? '')];
        }

        // Stripe: the publishable key is public so we show it; the secret and
        // webhook secret are only ever reported as set/unset, never echoed back.
        $stripe = [
            'publishable'   => (string) (setting('stripe_publishable', '') ?? ''),
            'has_secret'    => trim((string) (setting('stripe_secret', '') ?? '')) !== '',
            'has_webhook'   => trim((string) (setting('stripe_webhook_secret', '') ?? '')) !== '',
            'enabled'       => (new \App\Core\Stripe())->enabled(),
        ];

        $shipCfg  = (require BASE_DIR . '/config/config.php')['shipping'];
        $delivery = [
            'flat'      => (string) (setting('delivery_flat', (string) $shipCfg['flat']) ?? ''),
            'free_over' => (string) (setting('delivery_free_over', (string) $shipCfg['free_over']) ?? ''),
        ];

        $this->adminRender('settings', [
            'markup'   => $pct === false ? '0' : (string) $pct,
            'socials'  => $socials,
            'contact'  => $contact,
            'stripe'   => $stripe,
            'delivery' => $delivery,
            'csrf'    => Auth::token(),
            '_active' => 'settings',
        ], 'Settings');
    }

    public function save(): void
    {
        $this->requireCsrf();
        // Accept 0–100000%. Clamp negatives to 0 so we never sell below cost.
        $raw = str_replace([',', '%', ' '], '', (string) ($_POST['markup_pct'] ?? '0'));
        $pct = is_numeric($raw) ? max(0.0, min(100000.0, (float) $raw)) : 0.0;
        $val = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.'); // tidy: 25.00 -> 25
        $this->db()->prepare(
            "INSERT INTO settings (`key`,`value`) VALUES ('reseller_markup_pct', ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        )->execute([$val === '' ? '0' : $val]);

        // Social links. Blank clears the row (network disappears from the footer).
        $stmt = $this->db()->prepare(
            "INSERT INTO settings (`key`,`value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $bad = [];
        foreach (social_networks() as $key => $label) {
            $url = trim((string) ($_POST['social_' . $key] ?? ''));
            if ($url !== '') {
                if (!preg_match('~^https?://~i', $url)) {
                    $url = 'https://' . ltrim($url, '/');   // tolerate "instagram.com/acme"
                }
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $bad[] = $label;
                    $url = '';
                }
            }
            $stmt->execute(['social_' . $key, $url]);
        }

        // Contact details (free text — stored as given, escaped on output).
        foreach (array_keys(self::CONTACT) as $key) {
            $stmt->execute(['contact_' . $key, trim((string) ($_POST['contact_' . $key] ?? ''))]);
        }

        // Delivery: flat charge + free-shipping threshold (both >= 0). A blank or
        // 0 threshold disables free shipping (flat charge always applies).
        $flat = str_replace([',', '$', ' '], '', (string) ($_POST['delivery_flat'] ?? '0'));
        $free = str_replace([',', '$', ' '], '', (string) ($_POST['delivery_free_over'] ?? '0'));
        $stmt->execute(['delivery_flat', is_numeric($flat) ? number_format(max(0.0, (float) $flat), 2, '.', '') : '0.00']);
        $stmt->execute(['delivery_free_over', is_numeric($free) ? number_format(max(0.0, (float) $free), 2, '.', '') : '0.00']);

        // Stripe keys. Publishable is public and saved as typed (blank clears it).
        // Secret + webhook are write-only: a blank field KEEPS the stored value so
        // the masked form never wipes a live key on save.
        $stmt->execute(['stripe_publishable', trim((string) ($_POST['stripe_publishable'] ?? ''))]);
        foreach (['stripe_secret', 'stripe_webhook_secret'] as $skey) {
            $new = trim((string) ($_POST[$skey] ?? ''));
            if ($new !== '') { $stmt->execute([$skey, $new]); }
        }

        $msg = "Default commission saved: +{$val}%. Categories without their own rate use it; new adds to cart apply immediately.";
        if ($bad) {
            $msg .= ' Ignored invalid URL for: ' . implode(', ', $bad) . '.';
        }
        $this->flash($bad ? 'err' : 'ok', $msg);
        $this->redirect('/admin/settings');
    }
}
