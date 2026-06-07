<?php
// ============================================================
//  VAR ROOM — Database Configuration
//  config/db.php
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'var_room');
define('DB_USER',    'root');   // XAMPP default
define('DB_PASS',    '');       // XAMPP default (empty)
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO connection.
 * Usage anywhere: $pdo = db();
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn     = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[VAR ROOM DB] ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Database connection failed.']));
        }
    }
    return $pdo;
}

/* ── Shorthand helpers ───────────────────────────────────── */

function db_fetch_all(string $sql, array $p = []): array {
    $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll();
}

function db_fetch_one(string $sql, array $p = []): array|false {
    $s = db()->prepare($sql); $s->execute($p); return $s->fetch();
}

function db_execute(string $sql, array $p = []): int {
    $s = db()->prepare($sql); $s->execute($p); return $s->rowCount();
}

function db_insert(string $sql, array $p = []): string {
    $s = db()->prepare($sql); $s->execute($p); return db()->lastInsertId();
}
