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
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

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
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $dbName = env('DB_NAME', 'inventra');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

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

function tableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
    $stmt->execute(['table_name' => $tableName]);

    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $tableName, string $indexName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name'
    );
    $stmt->execute([
        'table_name' => $tableName,
        'index_name' => $indexName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function ensureSchemaMigrations(PDO $pdo): void
{
    if (!tableExists($pdo, 'sales_transactions')) {
        return;
    }

    $salesTransactionColumns = [
        'invoice_id' => 'ALTER TABLE sales_transactions ADD COLUMN invoice_id VARCHAR(40) DEFAULT NULL AFTER id',
        'branch_code' => 'ALTER TABLE sales_transactions ADD COLUMN branch_code VARCHAR(10) DEFAULT NULL AFTER invoice_id',
        'city' => 'ALTER TABLE sales_transactions ADD COLUMN city VARCHAR(120) DEFAULT NULL AFTER branch_code',
        'customer_type' => 'ALTER TABLE sales_transactions ADD COLUMN customer_type VARCHAR(30) DEFAULT NULL AFTER city',
        'customer_gender' => 'ALTER TABLE sales_transactions ADD COLUMN customer_gender VARCHAR(20) DEFAULT NULL AFTER customer_type',
        'payment_method' => 'ALTER TABLE sales_transactions ADD COLUMN payment_method VARCHAR(40) DEFAULT NULL AFTER region',
        'tax_amount' => 'ALTER TABLE sales_transactions ADD COLUMN tax_amount DECIMAL(10,4) DEFAULT NULL AFTER payment_method',
        'gross_total' => 'ALTER TABLE sales_transactions ADD COLUMN gross_total DECIMAL(10,4) DEFAULT NULL AFTER tax_amount',
        'cogs' => 'ALTER TABLE sales_transactions ADD COLUMN cogs DECIMAL(10,4) DEFAULT NULL AFTER gross_total',
        'gross_margin_percentage' => 'ALTER TABLE sales_transactions ADD COLUMN gross_margin_percentage DECIMAL(10,6) DEFAULT NULL AFTER cogs',
        'gross_income' => 'ALTER TABLE sales_transactions ADD COLUMN gross_income DECIMAL(10,4) DEFAULT NULL AFTER gross_margin_percentage',
        'rating' => 'ALTER TABLE sales_transactions ADD COLUMN rating DECIMAL(4,2) DEFAULT NULL AFTER gross_income',
        'sale_time' => 'ALTER TABLE sales_transactions ADD COLUMN sale_time TIME DEFAULT NULL AFTER sale_date',
        'sold_at' => 'ALTER TABLE sales_transactions ADD COLUMN sold_at DATETIME DEFAULT NULL AFTER sale_time',
    ];

    foreach ($salesTransactionColumns as $columnName => $sql) {
        if (!columnExists($pdo, 'sales_transactions', $columnName)) {
            $pdo->exec($sql);
        }
    }

    $indexes = [
        'products' => [
            'idx_products_archived_updated' => 'CREATE INDEX idx_products_archived_updated ON products (is_archived, updated_at)',
            'idx_products_category_archived' => 'CREATE INDEX idx_products_category_archived ON products (category_id, is_archived)',
            'idx_products_supplier_archived' => 'CREATE INDEX idx_products_supplier_archived ON products (supplier_id, is_archived)',
            'idx_products_stock_threshold' => 'CREATE INDEX idx_products_stock_threshold ON products (is_archived, stock_quantity, min_threshold)',
        ],
        'sales_transactions' => [
            'idx_sales_sale_date' => 'CREATE INDEX idx_sales_sale_date ON sales_transactions (sale_date)',
            'idx_sales_product_date' => 'CREATE INDEX idx_sales_product_date ON sales_transactions (product_id, sale_date)',
            'idx_sales_invoice_id' => 'CREATE INDEX idx_sales_invoice_id ON sales_transactions (invoice_id)',
            'idx_sales_branch_city' => 'CREATE INDEX idx_sales_branch_city ON sales_transactions (branch_code, city)',
        ],
        'stock_movements' => [
            'idx_movements_product_created' => 'CREATE INDEX idx_movements_product_created ON stock_movements (product_id, created_at)',
            'idx_movements_created' => 'CREATE INDEX idx_movements_created ON stock_movements (created_at)',
        ],
        'purchase_orders' => [
            'idx_purchase_orders_status' => 'CREATE INDEX idx_purchase_orders_status ON purchase_orders (status, created_at)',
        ],
    ];

    foreach ($indexes as $tableName => $tableIndexes) {
        if (!tableExists($pdo, $tableName)) {
            continue;
        }

        foreach ($tableIndexes as $indexName => $sql) {
            if (!indexExists($pdo, $tableName, $indexName)) {
                $pdo->exec($sql);
            }
        }
    }
}

function initializeConfiguredDatabase(): void
{
    $dbName = env('DB_NAME', 'inventra');
    
    try {
        $rootPdo = createRootDatabaseConnection();
        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    } catch (PDOException $e) {
        // Log or ignore if we don't have permission to create DB
    }

    $dbPdo = createConfiguredDatabaseConnection();
    if (!tableExists($dbPdo, 'users') || !tableExists($dbPdo, 'products') || !tableExists($dbPdo, 'sales_transactions')) {
        applySchema($dbPdo, readSchemaSql());
    }

    ensureSchemaMigrations($dbPdo);

    // The initial manager account is created by schema.sql.
    // No demo data is seeded automatically anymore.










}

function rebuildConfiguredDatabase(): void
{
    $dbName = env('DB_NAME', 'inventra');
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
