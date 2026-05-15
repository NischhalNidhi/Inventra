<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/helpers.php';

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('MYSQLHOST', env('DB_HOST', '127.0.0.1'));
    $port = env('MYSQLPORT', env('DB_PORT', '3306'));
    $dbName = env('MYSQLDATABASE', env('DB_NAME', 'inventra'));
    $username = env('MYSQLUSER', env('DB_USER', 'root'));
    $password = env('MYSQLPASSWORD', env('DB_PASS', ''));
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);

    return $pdo;
}
