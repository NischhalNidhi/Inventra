<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';
extract(buildAppDependencies(), EXTR_SKIP);
$pdo = $pdo ?? null;
if (!$pdo) {
    echo "Database connection not available." . PHP_EOL;
    exit(1);
}

$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS salesman_stock_allocations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salesman_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_allocated INT UNSIGNED NOT NULL,
    quantity_remaining INT UNSIGNED NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS salesman_stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    allocation_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    movement_type ENUM('allocate', 'sale', 'return', 'adjust') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    previous_allocation INT NOT NULL,
    new_allocation INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SQL;

try {
    $pdo->exec($sql);
    echo "Salesman stock tables created or already exist.\n";
} catch (Throwable $e) {
    echo "Failed to create tables: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "Done.\n";
