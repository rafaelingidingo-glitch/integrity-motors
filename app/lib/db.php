<?php
/**
 * PDO singleton — MySQL in production (cPanel), SQLite for local development.
 * All queries use prepared statements. Dates are stored as 'Y-m-d H:i:s'
 * strings in Africa/Dar_es_Salaam time (set in config.php).
 */

declare(strict_types=1);

final class IacDb
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if (DB_DRIVER === 'sqlite') {
                if (!is_dir(dirname(SQLITE_PATH))) {
                    @mkdir(dirname(SQLITE_PATH), 0775, true);
                }
                self::$pdo = new PDO('sqlite:' . SQLITE_PATH, null, null, $options);
                self::$pdo->exec('PRAGMA journal_mode = WAL');
                self::$pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$pdo->exec("SET time_zone = '+03:00'"); // Africa/Dar_es_Salaam (no DST)
            }
        } catch (PDOException $e) {
            error_log('[db] connection failed: ' . $e->getMessage());
            http_response_code(500);
            if (APP_DEBUG) {
                exit('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES));
            }
            exit('Database connection failed. Please check configuration.');
        }

        return self::$pdo;
    }

    /** Run a prepared statement and return it. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch all rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Fetch one row or null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch first column of first row (counts etc). */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** INSERT / UPDATE / DELETE — returns affected row count. */
    public static function exec(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    public static function lastId(): string
    {
        return (string) self::pdo()->lastInsertId();
    }

    /**
     * Portable UPSERT (MySQL: ON DUPLICATE KEY UPDATE / SQLite: ON CONFLICT DO UPDATE).
     * $unique: column(s) that carry a unique constraint; $updates: columns to
     * overwrite — either a plain list ['a','b'] or a key=>value map (keys used).
     */
    public static function upsert(string $table, array $data, array $unique, array $updates): void
    {
        $cols   = array_keys($data);
        $colSql = implode(', ', array_map(fn ($c) => "`$c`", $cols));
        $marks  = implode(', ', array_fill(0, count($cols), '?'));
        $values = array_values($data);

        // Accept both ['col1','col2'] and ['col1' => $val] for $updates
        $updateCols = [];
        foreach ($updates as $k => $v) {
            $updateCols[] = is_int($k) ? $v : $k;
        }

        if (DB_DRIVER === 'sqlite') {
            $conflict = implode(', ', array_map(fn ($c) => "`$c`", $unique));
            $set      = implode(', ', array_map(fn ($c) => "`$c` = excluded.`$c`", $updateCols));
            $sql      = "INSERT INTO `$table` ($colSql) VALUES ($marks) ON CONFLICT($conflict) DO UPDATE SET $set";
        } else {
            $set = implode(', ', array_map(fn ($c) => "`$c` = VALUES(`$c`)", $updateCols));
            $sql = "INSERT INTO `$table` ($colSql) VALUES ($marks) ON DUPLICATE KEY UPDATE $set";
        }

        self::exec($sql, $values);
    }

    /** Run a callback inside a transaction (nested calls reuse the outer tx). */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

/** Shorthand aliases */
function db(): PDO
{
    return IacDb::pdo();
}
function db_all(string $sql, array $params = []): array
{
    return IacDb::all($sql, $params);
}
function db_one(string $sql, array $params = []): ?array
{
    return IacDb::one($sql, $params);
}
function db_scalar(string $sql, array $params = []): mixed
{
    return IacDb::scalar($sql, $params);
}
function db_exec(string $sql, array $params = []): int
{
    return IacDb::exec($sql, $params);
}
