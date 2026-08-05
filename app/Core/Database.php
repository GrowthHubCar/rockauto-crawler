<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $cfg = (require BASE_DIR . '/config/config.php')['db'];
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}";

            try {
                self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    // Pin the CONNECTION collation to the one the columns actually use.
                    // `charset=utf8mb4` in the DSN only issues `SET NAMES utf8mb4`, which
                    // adopts the server's DEFAULT collation for that charset — general_ci
                    // on MariaDB 11.x. With EMULATE_PREPARES off, a bound string is sent
                    // natively and carries that connection collation, so comparing it to a
                    // utf8mb4_unicode_ci column raises:
                    //   1267 Illegal mix of collations (utf8mb4_general_ci,COERCIBLE)
                    //        and (utf8mb4_unicode_ci,COERCIBLE) for operation '='
                    // MariaDB 10.4 (local XAMPP) tolerated the mix; 11.8 (Hostinger) does
                    // not, so every prepared query comparing a param to a slug/name column
                    // 500'd in production while working fine in dev.
                    PDO::MYSQL_ATTR_INIT_COMMAND =>
                        'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
                ]);
            } catch (PDOException $e) {
                // Never leak host/port/dbname/driver to the client; log the detail.
                error_log('[SupremeParts] DB connection failed: ' . $e->getMessage());
                http_response_code(500);
                exit('Service temporarily unavailable. Please try again shortly.');
            }
        }

        return self::$instance;
    }
}
