<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/** Session-based admin authentication + CSRF for the admin panel. */
class Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Harden the session cookie: not JS-readable (blunts XSS session theft),
            // not sent cross-site (kills CSRF vectors), Secure once served on HTTPS.
            session_set_cookie_params([
                'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            ]);
            session_start();
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT * FROM admins WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        // Always run one bcrypt comparison so a missing email costs the same as a
        // wrong password — no timing oracle that reveals which admin emails exist.
        $hash = $admin['password_hash'] ?? '$2y$10$fFwqY9gQAmC5VVHjJVNh1e6c6JuVsrKdW2O2z0g1tgCv3w/c4TIuu';
        if (!password_verify($password, $hash) || !$admin) {
            return false;
        }
        self::start();
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'id'    => (int) $admin['id'],
            'name'  => $admin['name'],
            'email' => $admin['email'],
        ];
        $db->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = ?")->execute([$admin['id']]);
        return true;
    }

    // ---- Brute-force throttle (per client IP) ----
    // All three fail OPEN on a storage error: a broken throttle table must never
    // lock a legitimate admin out — the password check is still the real gate.

    /** Seconds remaining on an active lockout for this IP, else 0. */
    public static function loginLockRemaining(string $ip): int
    {
        try {
            // Do the arithmetic in SQL so MySQL's clock is used on both sides — mixing
            // MySQL NOW() with PHP time() misreports the lock by the timezone offset.
            $st = Database::connection()->prepare(
                "SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS rem
                   FROM admin_login_attempts
                  WHERE ip = ? AND locked_until IS NOT NULL AND locked_until > NOW()");
            $st->execute([$ip]);
            $row = $st->fetch();
            return $row ? (int) $row['rem'] : 0;
        } catch (\Throwable $e) { return 0; }
    }

    /** Record a failed attempt; lock the IP for 15 minutes once it hits 5 fails. */
    public static function loginFailed(string $ip): void
    {
        try {
            Database::connection()->prepare(
                // NOTE: MySQL evaluates SET left-to-right, so locked_until MUST come
                // before `fails = fails + 1` to read the pre-increment count (5th fail locks).
                "INSERT INTO admin_login_attempts (ip, fails, locked_until) VALUES (?, 1, NULL)
                 ON DUPLICATE KEY UPDATE
                   locked_until = IF(fails + 1 >= 5, NOW() + INTERVAL 15 MINUTE, locked_until),
                   fails = fails + 1"
            )->execute([$ip]);
        } catch (\Throwable $e) { /* throttle store down — never block auth on it */ }
    }

    /** Clear the throttle for this IP after a successful login. */
    public static function loginCleared(string $ip): void
    {
        try {
            Database::connection()->prepare("DELETE FROM admin_login_attempts WHERE ip = ?")->execute([$ip]);
        } catch (\Throwable $e) { }
    }

    public static function check(): bool
    {
        self::start();
        return isset($_SESSION['admin']);
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION['admin'] ?? null;
    }

    public static function logout(): void
    {
        self::start();
        unset($_SESSION['admin']);
        session_regenerate_id(true);
    }

    // ---- CSRF ----
    public static function token(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verify(?string $token): bool
    {
        self::start();
        return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
    }
}
