<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

$pdo = getDatabaseConnection();

echo "Cleaning existing data...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("TRUNCATE TABLE sales_transactions");
$pdo->exec("TRUNCATE TABLE stock_movements");
$pdo->exec("TRUNCATE TABLE products");
$pdo->exec("TRUNCATE TABLE categories");
$pdo->exec("TRUNCATE TABLE suppliers");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

$csvFile = __DIR__ . '/../supermarket_sales.csv';
if (!is_file($csvFile)) {
    die("Error: Dataset file not found at $csvFile\n");
}

$csv = array_map('str_getcsv', file($csvFile));
$header = array_shift($csv); // Remove header

echo "Extracting categories...\n";
$categories = [];
foreach ($csv as $row) {
    if (isset($row[5]) && trim($row[5]) !== '') {
        $categories[trim($row[5])] = true;
    }
}

$categoryMap = [];
$catStmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
foreach (array_keys($categories) as $catName) {
    $catStmt->execute([$catName, "Imported from supermarket sales dataset"]);
    $categoryMap[$catName] = (int) $pdo->lastInsertId();
}

echo "Extracting suppliers (from Branch/City)...\n";
$suppliers = [];
foreach ($csv as $row) {
    if (isset($row[1]) && isset($row[2])) {
        $branchCity = trim($row[2]) . ' (Branch ' . trim($row[1]) . ')';
        $suppliers[$branchCity] = true;
    }
}

$supplierMap = [];
$supStmt = $pdo->prepare("INSERT INTO suppliers (name, email, contact_person) VALUES (?, ?, ?)");
foreach (array_keys($suppliers) as $supName) {
    // Generate a dummy email
    $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $supName)) . '@supermarket.local';
    $supStmt->execute([$supName . ' Supplier', $email, 'Manager of ' . $supName]);
    $supplierMap[$supName] = (int) $pdo->lastInsertId();
}

// Get the admin user id
$userId = (int) $pdo->query("SELECT id FROM users WHERE role = 'Manager' LIMIT 1")->fetchColumn();
if (!$userId) {
    // Fallback if users table is empty
    $pdo->exec("INSERT INTO users (full_name, email, username, password_hash, role) VALUES ('System Manager', 'manager@inventra.local', 'manager', 'xxx', 'Manager')");
    $userId = (int) $pdo->lastInsertId();
}

echo "Importing products and sales...\n";
$productStmt = $pdo->prepare("
    INSERT INTO products (name, sku, category_id, supplier_id, unit_price, stock_quantity, min_threshold, created_by, updated_by) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$saleStmt = $pdo->prepare("
    INSERT INTO sales_transactions (product_id, quantity, unit_price, sale_date, region, created_by) 
    VALUES (?, ?, ?, ?, ?, ?)
");

$productsCreated = 0;
$salesCreated = 0;
$productCache = []; // Map "SKU" -> ID

foreach ($csv as $row) {
    if (count($row) < 16) continue;

    $invoiceId = trim($row[0]);
    $branch = trim($row[1]);
    $city = trim($row[2]);
    $productLine = trim($row[5]);
    $unitPrice = (float) $row[6];
    $quantity = (int) $row[7];
    $dateStr = trim($row[10]); // e.g. 1/5/2019
    
    // Parse Date m/d/Y
    $dateParts = explode('/', $dateStr);
    if (count($dateParts) === 3) {
        $date = sprintf('%04d-%02d-%02d', $dateParts[2], $dateParts[0], $dateParts[1]);
    } else {
        $date = '2019-01-01'; // Fallback
    }

    $branchCity = $city . ' (Branch ' . $branch . ')';
    $supplierId = $supplierMap[$branchCity] ?? null;
    $categoryId = $categoryMap[$productLine] ?? null;

    // Simulate unique products by Product Line and Unit Price
    $sku = "SKU-" . strtoupper(substr(md5($productLine . $unitPrice), 0, 8));

    if (!isset($productCache[$sku])) {
        // Generate a product name
        $prodName = $productLine . " Premium Item ($" . number_format($unitPrice, 2) . ")";
        
        // Randomize current stock so some are low-stock to trigger the UI alerts
        // The dataset doesn't have stock, so we simulate it.
        $minStock = rand(10, 30);
        $currentStock = rand(5, 100); // Some will be below min_threshold

        $productStmt->execute([
            $prodName,
            $sku,
            $categoryId,
            $supplierId,
            $unitPrice,
            $currentStock,
            $minStock,
            $userId,
            $userId
        ]);
        $productCache[$sku] = (int) $pdo->lastInsertId();
        $productsCreated++;
    }

    $prodId = $productCache[$sku];

    // Insert sale record
    $saleStmt->execute([
        $prodId,
        $quantity,
        $unitPrice,
        $date,
        $city,
        $userId
    ]);
    $salesCreated++;
}

echo "Success!\n";
echo "- $productsCreated unique products created.\n";
echo "- " . count($categories) . " categories created.\n";
echo "- " . count($suppliers) . " suppliers created.\n";
echo "- $salesCreated sales transactions imported.\n";
