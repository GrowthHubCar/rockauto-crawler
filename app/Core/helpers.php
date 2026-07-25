<?php
declare(strict_types=1);

// Global view helpers (loaded in bootstrap, available in all templates).

if (!function_exists('e')) {
    /** HTML-escape for safe output in templates. */
    function e(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('img_url')) {
    /** Serve a stored part photo from the CDN when SP_CDN_BASE is configured;
     *  otherwise return the path unchanged. Only rewrites paths under the local
     *  parts prefix — absolute URLs and other assets pass through untouched. */
    function img_url(?string $path): string
    {
        $path = $path ?? '';
        $base = defined('SP_CDN_BASE') ? SP_CDN_BASE : '';
        if ($base === '' || $path === '') return $path;
        $prefix = '/RockAuto/assets/parts/';
        $pos = strpos($path, $prefix);
        if ($pos === false) return $path;
        return rtrim($base, '/') . '/' . ltrim(substr($path, $pos + strlen($prefix)), '/');
    }
}

if (!function_exists('images_live')) {
    /** Parallel HEAD-probe of image URLs -> [url => bool].
     *  ~3% of catalog images 404 on the CDN (the source image never fetched), which
     *  renders as an empty product card. Callers use this to drop those parts from
     *  shop-window listings. Only ever call it behind a cache — never per page view.
     *  If curl is unavailable we report everything live: hiding real parts because we
     *  cannot verify is worse than the occasional blank card. */
    function images_live(array $urls, int $timeout = 6): array
    {
        $out = [];
        $urls = array_values(array_unique(array_filter($urls)));
        if (!$urls || !function_exists('curl_multi_init')) {
            foreach ($urls as $u) { $out[$u] = true; }
            return $out;
        }
        $mh = curl_multi_init();
        $handles = [];
        foreach ($urls as $u) {
            $ch = curl_init($u);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY         => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = [$ch, $u];
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) { curl_multi_select($mh, 0.5); }
        } while ($running && $status === CURLM_OK);
        foreach ($handles as [$ch, $u]) {
            $out[$u] = ((int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) === 200;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $out;
    }
}

if (!function_exists('with_live_images')) {
    /** Keep only the rows whose primary image actually resolves, capped at $n. */
    function with_live_images(array $rows, int $n): array
    {
        if (!$rows) { return []; }
        $live = images_live(array_map(fn($r) => img_url($r['primary_image_path'] ?? ''), $rows));
        $out = [];
        foreach ($rows as $r) {
            if (($live[img_url($r['primary_image_path'] ?? '')] ?? true) && count($out) < $n) {
                $out[] = $r;
            }
        }
        return $out;
    }
}

if (!function_exists('part_search_clause')) {
    /** Free-text search predicate over parts -> [sql, args]. Shared by the shop
     *  page and its JSON endpoint so both filter identically.
     *
     *  Prefers the FULLTEXT index on parts.name: an unanchored LIKE over ~1M rows
     *  costs ~4s whenever nothing matches, because it must scan the whole table
     *  before concluding "none". Short/punctuation terms (part numbers) fall back
     *  to LIKE, which is fine there — part_number and sku are indexed. */
    function part_search_clause(string $q): array
    {
        static $hasFt = null;
        if ($hasFt === null) {
            try {
                $hasFt = (bool) \App\Core\Database::connection()
                    ->query("SHOW INDEX FROM parts WHERE Key_name = 'ft_parts_name'")->fetch();
            } catch (\Throwable $e) { $hasFt = false; }
        }
        $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $long  = array_values(array_filter(
            $words,
            fn($w) => mb_strlen((string) preg_replace('/\W+/u', '', $w)) >= 3
        ));
        if ($long && $hasFt) {
            // every word required and prefix-matched, so "brake pad" behaves like a phrase
            $expr = implode(' ', array_map(
                fn($w) => '+' . trim((string) preg_replace('/[+\-><()~*"@]+/', ' ', $w)) . '*',
                $long
            ));
            return ['MATCH(p.name) AGAINST (:ftq IN BOOLEAN MODE)', [':ftq' => $expr]];
        }
        return ['(p.name LIKE :q1 OR p.part_number LIKE :q2 OR p.sku LIKE :q3)',
                [':q1' => '%' . $q . '%', ':q2' => '%' . $q . '%', ':q3' => '%' . $q . '%']];
    }
}

if (!function_exists('money')) {
    /** Format a decimal as USD. NULL means RockAuto lists no price for the part
     *  (out of stock) — render that, never a fake "$0.00". */
    function money(int|float|string|null $n): string
    {
        if ($n === null) return 'Out of Stock';
        return '$' . number_format((float) $n, 2);
    }
}

if (!function_exists('setting')) {
    /** Read a row from the `settings` key/value table (cached for the request).
     *  Returns $default when the key is absent or the DB is unreachable. */
    function setting(string $key, ?string $default = null): ?string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $rows = \App\Core\Database::connection()
                    ->query("SELECT `key`, `value` FROM settings")->fetchAll();
                foreach ($rows as $r) { $cache[$r['key']] = $r['value']; }
            } catch (\Throwable $e) { $cache = []; }   // table missing / DB down -> defaults
        }
        return $cache[$key] ?? $default;
    }
}

if (!function_exists('social_networks')) {
    /** Networks offered in admin -> label. Order here is the order shown in the footer. */
    function social_networks(): array
    {
        return [
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'x'         => 'X (Twitter)',
            'tiktok'    => 'TikTok',
            'linkedin'  => 'LinkedIn',
        ];
    }
}

if (!function_exists('social_links')) {
    /** [network => url] for the ones an admin actually filled in. Empty = hidden. */
    function social_links(): array
    {
        $out = [];
        foreach (array_keys(social_networks()) as $k) {
            $u = trim((string) (setting('social_' . $k, '') ?? ''));
            if ($u !== '') { $out[$k] = $u; }
        }
        return $out;
    }
}

if (!function_exists('whatsapp_link')) {
    /** wa.me link built from the admin-set WhatsApp number. '' when unset, so
     *  callers can fall back rather than render a dead link. */
    function whatsapp_link(string $text = ''): string
    {
        $digits = preg_replace('/\D+/', '', (string) (setting('contact_whatsapp', '') ?? ''));
        if ($digits === '') { return ''; }
        return 'https://wa.me/' . $digits . ($text !== '' ? '?text=' . rawurlencode($text) : '');
    }
}

if (!function_exists('commission_pct_for')) {
    /** Effective commission % for a category id. Cascades leaf -> parent group
     *  -> the store default. NULL/unknown category falls back to the default.
     *  The category map is loaded once per request. */
    function commission_pct_for(?int $categoryId): float
    {
        static $cat = null, $parent = null, $default = null;
        if ($cat === null) {
            $cat = []; $parent = [];
            // Store-wide default = the Settings "Default commission rate"
            // (reseller_markup_pct). A category with its own rate overrides it.
            $default = (float) (setting('reseller_markup_pct', '0') ?? '0');
            try {
                foreach (\App\Core\Database::connection()
                    ->query("SELECT id, parent_id, commission_pct FROM categories")->fetchAll() as $r) {
                    $parent[(int) $r['id']] = $r['parent_id'] !== null ? (int) $r['parent_id'] : null;
                    if ($r['commission_pct'] !== null) { $cat[(int) $r['id']] = (float) $r['commission_pct']; }
                }
            } catch (\Throwable $e) { /* categories/columns missing -> default only */ }
        }
        // walk up to the parent group looking for the first set commission
        $id = $categoryId;
        $guard = 0;
        while ($id !== null && $guard++ < 6) {
            if (isset($cat[$id])) { return $cat[$id]; }
            $id = $parent[$id] ?? null;
        }
        return $default;
    }
}

if (!function_exists('markup_factor')) {
    /** Multiplier for a category: 1 + commission%/100. No category = default. */
    function markup_factor(?int $categoryId = null): float
    {
        return 1.0 + max(0.0, commission_pct_for($categoryId)) / 100.0;   // never below cost
    }
}

if (!function_exists('sell_price')) {
    /** Customer price = RockAuto cost * (1 + commission), rounded to the cent.
     *  Pass the part's category_id so its individual commission applies; without
     *  it the store default is used. NULL base (out of stock) passes through. */
    function sell_price(int|float|string|null $base, ?int $categoryId = null): ?float
    {
        if ($base === null) return null;
        return round((float) $base * markup_factor($categoryId), 2);
    }
}

if (!function_exists('price_tag')) {
    /** Formatted customer-facing price: money(sell_price(base, categoryId)). */
    function price_tag(int|float|string|null $base, ?int $categoryId = null): string
    {
        return money(sell_price($base, $categoryId));
    }
}
