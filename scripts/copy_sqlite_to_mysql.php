<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$dotenv = Dotenv::createImmutable($root);
$dotenv->safeLoad();

$sqlitePath = $root . '/' . ($_ENV['SQLITE_DB_PATH'] ?? 'database/database.sqlite');
if (! file_exists($sqlitePath)) {
    fwrite(STDERR, "ERROR: SQLite file not found: $sqlitePath\n");
    exit(1);
}

$mysqlHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
$mysqlPort = $_ENV['DB_PORT'] ?? '3306';
$mysqlDatabase = $_ENV['DB_DATABASE'] ?? 'laravel';
$mysqlUsername = $_ENV['DB_USERNAME'] ?? 'root';
$mysqlPassword = $_ENV['DB_PASSWORD'] ?? '';

$sqliteDsn = 'sqlite:' . $sqlitePath;
$mysqlDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $mysqlHost, $mysqlPort, $mysqlDatabase);

try {
    $sqlite = new PDO($sqliteDsn);
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: Cannot connect to SQLite: " . $e->getMessage() . "\n");
    exit(1);
}

try {
    $mysql = new PDO($mysqlDsn, $mysqlUsername, $mysqlPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: Cannot connect to MySQL: " . $e->getMessage() . "\n");
    exit(1);
}

$tables = [
    'users',
    'courses',
    'course_materials',
    'course_enrollments',
];

fwrite(STDOUT, "Starting migration from SQLite to MySQL...\n");
$mysql->exec('SET FOREIGN_KEY_CHECKS = 0');
$mysql->beginTransaction();

try {
    foreach ($tables as $table) {
        fwrite(STDOUT, "Migrating table: $table...\n");

        $mysql->exec("DELETE FROM `$table`");

        $rows = $sqlite->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (! $rows) {
            fwrite(STDOUT, "  No rows to copy.\n");
            continue;
        }

        $columns = array_keys($rows[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insertSql = "INSERT INTO `$table` ($columnList) VALUES ($placeholders)";
        $stmt = $mysql->prepare($insertSql);

        foreach ($rows as $row) {
            $stmt->execute(array_values($row));
        }

        fwrite(STDOUT, "  Copied " . count($rows) . " rows.\n");
    }

    $mysql->commit();
    $mysql->exec('SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDOUT, "Migration selesai. Pastikan sudah menjalankan php artisan migrate di MySQL sebelum skrip ini.\n");
} catch (Exception $e) {
    if ($mysql->inTransaction()) {
        $mysql->rollBack();
    }
    $mysql->exec('SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDERR, "ERROR: Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
