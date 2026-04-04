<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$schema = file_get_contents(__DIR__ . '/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Unable to read schema.sql\n");
    exit(1);
}

$schema = preg_replace('/^\xEF\xBB\xBF/', '', $schema) ?? $schema;
$host = env('DB_HOST', '127.0.0.1');
$port = env('DB_PORT', '3306');
$dbName = env('DB_NAME', 'inventra');
$user = env('DB_USER', 'root');
$pass = env('DB_PASS', '');

$rootPdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

$dbPdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$statements = array_filter(array_map('trim', explode(';', $schema)), static fn (string $sql): bool => $sql !== '');
foreach ($statements as $statement) {
    $normalized = strtoupper(trim($statement));
    if (str_starts_with($normalized, 'CREATE DATABASE') || str_starts_with($normalized, 'USE ')) {
        continue;
    }
    $dbPdo->exec($statement);
}

echo "Database schema initialized.\n";
