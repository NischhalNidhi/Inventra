<?php

declare(strict_types=1);

/**
 * Inventra - Unified Database Controller
 * This file combines bootstrap, schema, init, rebuild, and seeding logic.
 */

require_once __DIR__ . '/../core/helpers.php';

// --- CONFIGURATION & CONNECTION ---

const DATABASE_SCHEMA = <<<'SQL'
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS report_import_row_errors;
DROP TABLE IF EXISTS report_import_batches;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_tokens;
DROP TABLE IF EXISTS password_reset_requests;
DROP TABLE IF EXISTS sales_transactions;
DROP TABLE IF EXISTS access_requests;
DROP TABLE IF EXISTS delivery_logs;
DROP TABLE IF EXISTS po_line_items;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS stock_history;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Manager', 'Supervisor', 'Salesman', 'Logistic Handler') NOT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    purpose ENUM('account_setup', 'password_reset') NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_login_attempts_ip_attempted_at (ip, attempted_at)
);

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    contact_person VARCHAR(120) DEFAULT NULL,
    email VARCHAR(120) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    image_name VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(30) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    image_name VARCHAR(255) DEFAULT NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    min_threshold INT UNSIGNED NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    category_id INT UNSIGNED DEFAULT NULL,
    supplier_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    updated_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_archived_updated (is_archived, updated_at),
    INDEX idx_products_category_archived (category_id, is_archived),
    INDEX idx_products_supplier_archived (supplier_id, is_archived),
    INDEX idx_products_stock_threshold (is_archived, stock_quantity, min_threshold),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_products_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    movement_type ENUM('in', 'out', 'return', 'bulk_in') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    source_ref VARCHAR(120) DEFAULT NULL,
    unit_price DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_movements_product_created (product_id, created_at),
    INDEX idx_movements_created (created_at),
    CONSTRAINT fk_movements_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(40) NOT NULL UNIQUE,
    supplier_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'received') NOT NULL DEFAULT 'pending',
    expected_date DATE DEFAULT NULL,
    carrier_name VARCHAR(120) DEFAULT NULL,
    tracking_number VARCHAR(120) DEFAULT NULL,
    dispatch_date DATE DEFAULT NULL,
    expected_arrival DATE DEFAULT NULL,
    shipment_status ENUM('order_placed', 'dispatched', 'in_transit', 'delivered') NOT NULL DEFAULT 'order_placed',
    status_updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_purchase_orders_status (status, created_at),
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_po_created_by FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS po_line_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_ordered INT UNSIGNED NOT NULL,
    quantity_received INT UNSIGNED DEFAULT NULL,
    unit_price DECIMAL(10,2) DEFAULT NULL,
    CONSTRAINT fk_po_lines_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_po_lines_product FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS delivery_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_ordered INT UNSIGNED NOT NULL,
    quantity_received INT UNSIGNED NOT NULL,
    date_received TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_by INT UNSIGNED NOT NULL,
    CONSTRAINT fk_delivery_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    CONSTRAINT fk_delivery_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_delivery_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_delivery_user FOREIGN KEY (received_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS sales_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id VARCHAR(40) DEFAULT NULL,
    branch_code VARCHAR(10) DEFAULT NULL,
    city VARCHAR(120) DEFAULT NULL,
    customer_type VARCHAR(30) DEFAULT NULL,
    customer_gender VARCHAR(20) DEFAULT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    sale_date DATE NOT NULL,
    sale_time TIME DEFAULT NULL,
    sold_at DATETIME DEFAULT NULL,
    region VARCHAR(120) DEFAULT NULL,
    payment_method VARCHAR(40) DEFAULT NULL,
    tax_amount DECIMAL(10,4) DEFAULT NULL,
    gross_total DECIMAL(10,4) DEFAULT NULL,
    cogs DECIMAL(10,4) DEFAULT NULL,
    gross_margin_percentage DECIMAL(10,6) DEFAULT NULL,
    gross_income DECIMAL(10,4) DEFAULT NULL,
    rating DECIMAL(4,2) DEFAULT NULL,
    source ENUM('manual_entry', 'import') NOT NULL DEFAULT 'manual_entry',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sales_sale_date (sale_date),
    INDEX idx_sales_product_date (product_id, sale_date),
    INDEX idx_sales_invoice_id (invoice_id),
    INDEX idx_sales_branch_city (branch_code, city),
    CONSTRAINT fk_sales_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_sales_user FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS access_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL,
    desired_role ENUM('Supervisor', 'Salesman', 'Logistic Handler') NOT NULL,
    message VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    review_note VARCHAR(255) DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_access_review_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS report_import_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_type ENUM('csv', 'xlsx') NOT NULL,
    status ENUM('completed', 'failed') NOT NULL,
    imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_batch_user FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS report_import_row_errors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    row_index INT UNSIGNED NOT NULL,
    error_message VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_row_batch FOREIGN KEY (batch_id) REFERENCES report_import_batches(id) ON DELETE CASCADE
);

INSERT INTO categories (name, description)
SELECT * FROM (
    SELECT 'Grocery Staples' AS name, 'Daily pantry items and packaged essentials' AS description
    UNION ALL
    SELECT 'Beverages', 'Cold drinks, juices, water, and ready-to-serve beverages'
    UNION ALL
    SELECT 'Snacks', 'Biscuits, chips, confectionery, and quick-grab treats'
    UNION ALL
    SELECT 'Household', 'Cleaning supplies, paper goods, and home-care items'
    UNION ALL
    SELECT 'Personal Care', 'Toiletries, hygiene, and self-care products'
) AS seed_categories
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE categories.name = seed_categories.name);

INSERT INTO users (full_name, email, username, password_hash, role, is_active)
SELECT * FROM (
    SELECT
      'System Manager' AS full_name,
      'manager@inventra.local' AS email,
      'manager' AS username,
      '$2y$12$fuzGDrJ18sy15/BTjMLJyuvMAKUV1Tls9NQ7mzZU0SzKxZujsbdYe' AS password_hash,
      'Manager' AS role,
      1 AS is_active
) AS seed_users
WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.username = seed_users.username);
SQL;

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
        $pdo->exec($statement);
    }
}

function tableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
    $stmt->execute(['table_name' => $tableName]);
    return (bool) $stmt->fetchColumn();
}

function initializeConfiguredDatabase(): void
{
    $dbName = env('MYSQLDATABASE', env('DB_NAME', 'inventra'));
    if (!env('MYSQLDATABASE')) {
        try {
            $rootPdo = createRootDatabaseConnection();
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        } catch (PDOException $e) {}
    }

    $dbPdo = createConfiguredDatabaseConnection();
    if (!tableExists($dbPdo, 'users')) {
        applySchema($dbPdo, DATABASE_SCHEMA);
    }
}

function rebuildConfiguredDatabase(): void
{
    $dbName = env('MYSQLDATABASE', env('DB_NAME', 'inventra'));
    $rootPdo = createRootDatabaseConnection();
    $rootPdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $dbPdo = createConfiguredDatabaseConnection();
    applySchema($dbPdo, DATABASE_SCHEMA);
}

// --- SEEDING LOGIC ---

function seedDatabase(PDO $pdo): void
{
    echo "Seeding Categories...\n";
    $categories = [
        ['Beverages', 'Soft drinks, milk, juices'],
        ['Grocery Staples', 'Rice, bread, flour'],
        ['Household', 'Cleaning supplies'],
        ['Snacks', 'Chips, biscuits'],
        ['Personal Care', 'Shampoo, soap'],
        ['Electronics', 'Gadgets and accessories'],
        ['Apparel', 'Clothing and footwear'],
        ['Home & Kitchen', 'Appliances and decor'],
        ['Stationery', 'Office and school supplies']
    ];
    foreach ($categories as $cat) {
        $pdo->prepare("INSERT IGNORE INTO categories (name, description) VALUES (?, ?)")->execute($cat);
    }
    $catMap = array_flip($pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR));

    echo "Seeding Suppliers...\n";
    $suppliers = [
        ['National Foods', 'John Doe', 'john@national.local', '123456789'],
        ['CleanHome Co', 'Jane Doe', 'jane@clean.local', '987654321'],
        ['Pantry Express', 'Mike Ross', 'mike@pantry.local', '555-0199'],
        ['TechNova Solutions', 'Sarah Chen', 'sarah@technova.local', '555-0200'],
        ['Global Trends Inc', 'David Miller', 'david@globaltrends.local', '555-0201'],
        ['KitchenPro', 'Elena Rodriguez', 'elena@kitchenpro.local', '555-0202'],
        ['Office Supply Co', 'Pam Beesly', 'pam@officesupply.local', '555-0300']
    ];
    foreach ($suppliers as $supp) {
        $pdo->prepare("INSERT IGNORE INTO suppliers (name, contact_person, email, phone) VALUES (?, ?, ?, ?)")->execute($supp);
    }
    $suppMap = array_flip($pdo->query("SELECT id, name FROM suppliers")->fetchAll(PDO::FETCH_KEY_PAIR));

    echo "Seeding Products...\n";
    $products = [
        ['Full Cream Milk 1L', 'BVG-MILK-1', 'Fresh full cream milk from local farms', 'milk.png', 50, 20, 120.00, $catMap['Beverages'], $suppMap['National Foods']],
        ['Classic Cola 500ml', 'BVG-COLA-1', 'Refreshing carbonated soft drink', 'cola.png', 120, 30, 65.00, $catMap['Beverages'], $suppMap['National Foods']],
        ['Green Tea Bags (25 Pack)', 'BVG-TEA-G', 'Pure organic green tea for daily wellness', 'tea.png', 80, 20, 180.00, $catMap['Beverages'], $suppMap['National Foods']],
        ['Organic Whole Wheat Bread', 'GRC-BREAD-1', 'Freshly baked whole wheat bread daily', 'bread.png', 30, 15, 80.00, $catMap['Grocery Staples'], $suppMap['National Foods']],
        ['Basmati Rice 5kg', 'GRC-RICE-5', 'Premium long grain basmati rice for fine dining', 'rice.png', 60, 15, 850.00, $catMap['Grocery Staples'], $suppMap['Pantry Express']],
        ['Sunflower Cooking Oil 1L', 'GRC-OIL-1', 'Refined sunflower oil for healthy cooking', 'oil.png', 45, 12, 280.00, $catMap['Grocery Staples'], $suppMap['Pantry Express']],
        ['All-Purpose Cleaner', 'HHD-CLN-1', 'Tough on stains, gentle on surfaces', 'cleaner.png', 40, 10, 250.00, $catMap['Household'], $suppMap['CleanHome Co']],
        ['Dishwashing Liquid 500ml', 'HHD-DISH-1', 'Cuts through grease effectively', 'dishwash.png', 45, 12, 120.00, $catMap['Household'], $suppMap['CleanHome Co']],
        ['Paper Napkins (50 Pack)', 'HHD-NAP-1', 'Soft and absorbent 2-ply napkins', 'napkins.png', 200, 50, 45.00, $catMap['Household'], $suppMap['Office Supply Co']],
        ['Potato Chips (Classic)', 'SNK-CHIPS-1', 'Crunchy salted classic potato chips', 'chips.png', 100, 30, 50.00, $catMap['Snacks'], $suppMap['National Foods']],
        ['Digestive Biscuits', 'SNK-BIS-1', 'High-fiber digestive biscuits for tea time', 'biscuits.png', 60, 20, 95.00, $catMap['Snacks'], $suppMap['National Foods']],
        ['Dark Chocolate Bar', 'SNK-CHOC-1', '70% cocoa rich dark chocolate', 'chocolate.png', 50, 15, 150.00, $catMap['Snacks'], $suppMap['Global Trends Inc']],
        ['Anti-Dandruff Shampoo', 'PC-SHMP-1', 'Scalp care shampoo for healthy hair', 'shampoo.png', 25, 10, 350.00, $catMap['Personal Care'], $suppMap['CleanHome Co']],
        ['Moisturizing Soap Bar', 'PC-SOAP-1', 'Enriched with vitamin E for soft skin', 'soap.png', 150, 40, 45.00, $catMap['Personal Care'], $suppMap['CleanHome Co']],
        ['Gentle Face Wash', 'PC-FWASH-1', 'Daily face wash for all skin types', 'facewash.png', 40, 15, 195.00, $catMap['Personal Care'], $suppMap['CleanHome Co']],
        ['AA Alkaline Batteries', 'ELC-BATT-AA', 'Long-lasting power for everyday devices', 'batteries.png', 300, 100, 250.00, $catMap['Electronics'], $suppMap['TechNova Solutions']],
        ['USB-C Charging Cable', 'ELC-CABLE-C', 'Fast charging and data sync cable 1m', 'cable.png', 80, 20, 450.00, $catMap['Electronics'], $suppMap['TechNova Solutions']],
        ['LED Desk Lamp', 'ELC-LAMP-1', 'Eye-friendly adjustable LED lamp', 'lamp.png', 15, 5, 1200.00, $catMap['Electronics'], $suppMap['TechNova Solutions']],
        ['A5 Spiral Notebook', 'STN-NB-A5', 'High-quality ruled paper notebook', 'notebook.png', 120, 30, 85.00, $catMap['Stationery'], $suppMap['Office Supply Co']],
        ['Ballpoint Pens (Blue)', 'STN-PEN-B', 'Smooth writing blue ink pens (Pack of 10)', 'pens.png', 100, 25, 120.00, $catMap['Stationery'], $suppMap['Office Supply Co']],
        ['Cotton Crew Neck T-Shirt', 'APR-TSH-1', '100% breathable cotton t-shirt', 'tshirt.png', 50, 15, 550.00, $catMap['Apparel'], $suppMap['Global Trends Inc']],
    ];
    foreach ($products as $p) {
        if ($pdo->query("SELECT COUNT(*) FROM products WHERE sku = '{$p[1]}'")->fetchColumn() > 0) continue;
        $pdo->prepare("INSERT INTO products (name, sku, description, image_name, stock_quantity, min_threshold, unit_price, category_id, supplier_id, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)")->execute($p);
    }

    echo "Generating historical sales (last 12 months)...\n";
    $products = $pdo->query("SELECT id, unit_price FROM products")->fetchAll(PDO::FETCH_ASSOC);
    $regions = ['North Store', 'South Store', 'East Mall', 'West Plaza', 'Central Hub'];
    $paymentMethods = ['Cash', 'Credit Card', 'Mobile Pay', 'Debit Card'];
    $pdo->exec("DELETE FROM sales_transactions");
    $stmt = $pdo->prepare("INSERT INTO sales_transactions (invoice_id, product_id, quantity, unit_price, sale_date, sale_time, region, payment_method, customer_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

    for ($m = 11; $m >= 0; $m--) {
        $monthDate = date('Y-m-01', strtotime("-$m months"));
        $daysInMonth = (int)date('t', strtotime($monthDate));
        if ($m === 0) $daysInMonth = (int)date('d');
        $seasonalFactor = 1.0;
        $monthNum = (int)date('n', strtotime($monthDate));
        if (in_array($monthNum, [11, 12, 1])) $seasonalFactor = 1.6;
        if (in_array($monthNum, [6, 7])) $seasonalFactor = 1.3;
        
        $count = (int)(rand(150, 250) * $seasonalFactor);
        for ($i = 0; $i < $count; $i++) {
            $prod = $products[array_rand($products)];
            $qty = rand(1, 5);
            if ($prod['unit_price'] < 100) $qty = rand(2, 10);
            $day = rand(1, $daysInMonth);
            $date = date('Y-m-d', strtotime(date('Y-m', strtotime($monthDate)) . "-$day"));
            $time = sprintf('%02d:%02d:%02d', rand(9, 21), rand(0, 59), rand(0, 59));
            $stmt->execute(['INV-'.strtoupper(substr(md5((string)rand()), 0, 8)), $prod['id'], $qty, $prod['unit_price'], $date, $time, $regions[array_rand($regions)], $paymentMethods[array_rand($paymentMethods)], rand(0, 10) > 7 ? 'Member' : 'Normal']);
        }
    }
    echo "Database successfully seeded!\n";
}

// --- CLI INTERFACE ---

if (php_sapi_name() === 'cli' && isset($argv[1])) {
    try {
        if ($argv[1] === '--init') {
            initializeConfiguredDatabase();
            echo "Database initialized.\n";
        } elseif ($argv[1] === '--rebuild') {
            rebuildConfiguredDatabase();
            echo "Database rebuilt.\n";
        } elseif ($argv[1] === '--seed') {
            seedDatabase(createConfiguredDatabaseConnection());
        } else {
            echo "Usage: php database.php [--init | --rebuild | --seed]\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
