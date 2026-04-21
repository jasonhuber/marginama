<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'marginama');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '') ?? '';
    $dsn  = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/**
 * Generate a 26-char ULID (Crockford base32, lexicographically sortable).
 * Used for all primary keys so row creation time is embedded in the id.
 */
function ulid(): string {
    static $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $timeMs = (int) (microtime(true) * 1000);
    $timeChars = '';
    for ($i = 9; $i >= 0; $i--) {
        $timeChars = $alphabet[$timeMs & 31] . $timeChars;
        $timeMs >>= 5;
    }
    $rand = random_bytes(10);
    $randChars = '';
    $bits = 0;
    $buf = 0;
    foreach (str_split($rand) as $b) {
        $buf = ($buf << 8) | ord($b);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $randChars .= $alphabet[($buf >> $bits) & 31];
        }
    }
    return $timeChars . $randChars;
}
