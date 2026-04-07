<?php

declare(strict_types=1);

<<<<<<< HEAD
require_once __DIR__ . '/../includes/helpers.php';
=======
require_once __DIR__ . '/../core/helpers.php';
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $dbName = env('DB_NAME', 'inventra');
    $username = env('DB_USER', 'root');
    $password = env('DB_PASS', '');
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
