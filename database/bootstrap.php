<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/helpers.php';

function readSchemaSql(): string
{
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Unable to read database/schema.sql');
    }

    return preg_replace('/^\xEF\xBB\xBF/', '', $schema) ?? $schema;
}

function createRootDatabaseConnection(): PDO
{
    $host = env('MYSQLHOST', env('DB_HOST', '127.0.0.1'));
    $port = env('MYSQLPORT', env('DB_PORT', '3306'));
    $user = env('MYSQLUSER', env('DB_USER', 'root'));
    $pass = env('MYSQLPASSWORD', env('DB_PASS', ''));

    return new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function createConfiguredDatabaseConnection(): PDO
{
    $host = env('MYSQLHOST', env('DB_HOST', '127.0.0.1'));
    $port = env('MYSQLPORT', env('DB_PORT', '3306'));
    $dbName = env('MYSQLDATABASE', env('DB_NAME', 'inventra'));
    $user = env('MYSQLUSER', env('DB_USER', 'root'));
    $pass = env('MYSQLPASSWORD', env('DB_PASS', ''));

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function applySchema(PDO $pdo, string $schema): void
{
    $statements = array_filter(array_map('trim', explode(';', $schema)), static fn (string $sql): bool => $sql !== '');

    foreach ($statements as $statement) {
        $normalized = strtoupper(trim($statement));
        if (str_starts_with($normalized, 'CREATE DATABASE') || str_starts_with($normalized, 'USE ')) {
            continue;
        }

        $pdo->exec($statement);
    }
}

function initializeConfiguredDatabase(): void
{
    $dbName = env('MYSQLDATABASE', env('DB_NAME', 'inventra'));
    
    // Only attempt to create database if not using Railway's pre-provisioned DB
    if (!env('MYSQLDATABASE')) {
        try {
            $rootPdo = createRootDatabaseConnection();
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        } catch (PDOException $e) {
            // Log or ignore if we don't have permission to create DB
        }
    }

    $dbPdo = createConfiguredDatabaseConnection();
    applySchema($dbPdo, readSchemaSql());

    // Auto-seed demo data if the users table is empty (i.e. fresh DB)
    $userCount = (int) $dbPdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount === 0) {
        ob_start();
        require_once __DIR__ . '/seed_demo_users.php';
        require_once __DIR__ . '/seed_department_store.php';
        require_once __DIR__ . '/seed_analytics.php';
        ob_end_clean();
    }
}

function rebuildConfiguredDatabase(): void
{
    $dbName = env('MYSQLDATABASE', env('DB_NAME', 'inventra'));
    $lastException = null;

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            $rootPdo = createRootDatabaseConnection();
            $rootPdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

            $dbPdo = createConfiguredDatabaseConnection();
            applySchema($dbPdo, readSchemaSql());
            return;
        } catch (PDOException $exception) {
            $lastException = $exception;
            $mysqlCode = (string) ($exception->errorInfo[1] ?? '');
            if (!in_array($mysqlCode, ['1205', '1213'], true) || $attempt === 3) {
                throw $exception;
            }

            usleep(250000 * $attempt);
        }
    }

    if ($lastException instanceof Throwable) {
        throw $lastException;
    }
}
