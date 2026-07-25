<?php
declare(strict_types=1);
// Self-check for img_url() (bin/test_img_url.php). Run: php bin/test_img_url.php
define('SP_CDN_BASE', 'https://supremeautos-parts.b-cdn.net');
require __DIR__ . '/../app/Core/helpers.php';

$ok = true;
function chk(string $label, bool $cond): void {
    global $ok; $ok = $ok && $cond;
    echo ($cond ? '[PASS] ' : '[FAIL] ') . $label . "\n";
}

chk('rewrites local parts path -> CDN',
    img_url('/RockAuto/assets/parts/111/x__ra_m.jpg')
    === 'https://supremeautos-parts.b-cdn.net/111/x__ra_m.jpg');
chk('absolute URL passes through',
    img_url('https://example.com/y.jpg') === 'https://example.com/y.jpg');
chk('non-parts asset passes through',
    img_url('/RockAuto/assets/img/logo.svg') === '/RockAuto/assets/img/logo.svg');
chk('null -> empty string', img_url(null) === '');

echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
