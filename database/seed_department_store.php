<?php

/**
 * Inventra - Comprehensive Departmental Store Demo Data Seeder
 * This script populates the database with realistic data for a departmental store.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

$pdo = getDatabaseConnection();

// 1. Categories
$categories = [
    ['Beverages', 'Soft drinks, milk, juices, and tea/coffee'],
    ['Grocery Staples', 'Rice, bread, flour, pulses, and oil'],
    ['Household', 'Cleaning supplies, laundry, and kitchenware'],
    ['Snacks', 'Chips, biscuits, and confectionery'],
    ['Personal Care', 'Shampoo, soap, and hygiene products'],
    ['Electronics', 'Gadgets, batteries, and small appliances'],
    ['Stationery', 'Pens, notebooks, and office supplies'],
    ['Clothing', 'Basic apparel and accessories']
];

foreach ($categories as $cat) {
    if ($pdo->query("SELECT COUNT(*) FROM categories WHERE name = '{$cat[0]}'")->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute($cat);
    }
}

$catMap = $pdo->query("SELECT name, id FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Suppliers
$suppliers = [
    ['Global Distribution Ltd', 'Robert Smith', 'robert@globaldist.local', '555-0101'],
    ['National Foods', 'John Doe', 'john@national.local', '555-0102'],
    ['CleanHome Co', 'Jane Doe', 'jane@clean.local', '555-0103'],
    ['ElectroHub Solutions', 'Mike Johnson', 'mike@electrohub.local', '555-0104'],
    ['Daily Essentials Inc', 'Sarah Wilson', 'sarah@dailyessentials.local', '555-0105']
];

foreach ($suppliers as $supp) {
    if ($pdo->query("SELECT COUNT(*) FROM suppliers WHERE name = '{$supp[0]}'")->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, email, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute($supp);
    }
}

$suppMap = $pdo->query("SELECT name, id FROM suppliers")->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Products
// [Name, SKU, Description, Image, Stock, Min, Price, Category, Supplier]
$products = [
    // Beverages
    ['Full Cream Milk 1L', 'BEV-MILK-001', 'Fresh full cream milk', 'milk.png', 50, 20, 120.00, 'Beverages', 'National Foods'],
    ['Cola Zero 500ml', 'BEV-COLA-002', 'Sugar-free carbonated drink', 'cola.png', 100, 30, 45.00, 'Beverages', 'Global Distribution Ltd'],
    ['Assam Tea Red Label 250g', 'BEV-TEA-003', 'Strong black tea', 'tea.png', 40, 15, 180.00, 'Beverages', 'National Foods'],
    
    // Grocery Staples
    ['Organic Whole Wheat Bread', 'GRO-BRD-001', 'Fresh baked daily', 'bread.png', 30, 15, 80.00, 'Grocery Staples', 'National Foods'],
    ['Basmati Rice 5kg', 'GRO-RICE-002', 'Premium long grain rice', 'rice.png', 20, 5, 850.00, 'Grocery Staples', 'National Foods'],
    ['Extra Virgin Olive Oil 500ml', 'GRO-OIL-003', 'Cold pressed oil', 'oil.png', 15, 5, 650.00, 'Grocery Staples', 'Global Distribution Ltd'],
    
    // Household
    ['Floor Cleaner Lemon 1L', 'HOU-CLN-001', 'Citrus fresh surface cleaner', 'cleaner.png', 40, 10, 250.00, 'Household', 'CleanHome Co'],
    ['Dishwashing Liquid 750ml', 'HOU-DISH-002', 'Tough on grease', 'dishwash.png', 35, 10, 120.00, 'Household', 'CleanHome Co'],
    ['Multipurpose Napkins 100s', 'HOU-NPK-003', 'Soft paper napkins', 'napkins.png', 60, 20, 95.00, 'Household', 'Daily Essentials Inc'],
    
    // Snacks
    ['Potato Chips Salted', 'SNK-CHIP-001', 'Classic salted potato chips', 'chips.png', 100, 30, 50.00, 'Snacks', 'National Foods'],
    ['Dark Chocolate Bar 80g', 'SNK-CHOC-002', '70% Cocoa dark chocolate', 'chocolate.png', 45, 15, 150.00, 'Snacks', 'Global Distribution Ltd'],
    ['Oat Biscuits 200g', 'SNK-BISC-003', 'Healthy fiber biscuits', 'biscuits.png', 55, 20, 75.00, 'Snacks', 'National Foods'],
    
    // Personal Care
    ['Shampoo Anti-Dandruff 200ml', 'PC-SHM-001', 'Daily use shampoo', 'shampoo.png', 25, 10, 350.00, 'Personal Care', 'CleanHome Co'],
    ['Antibacterial Soap Bar', 'PC-SOAP-002', 'Life protection soap', 'soap.png', 80, 25, 40.00, 'Personal Care', 'CleanHome Co'],
    ['Face Wash Charcoal 100ml', 'PC-FACE-003', 'Deep cleaning face wash', 'facewash.png', 30, 10, 220.00, 'Personal Care', 'Daily Essentials Inc'],

    // Electronics
    ['Alkaline AA Batteries 4pk', 'ELE-BATT-001', 'Long lasting power', 'batteries.png', 40, 15, 160.00, 'Electronics', 'ElectroHub Solutions'],
    ['LED Desk Lamp', 'ELE-LAMP-002', 'Touch control adjustable lamp', 'lamp.png', 12, 5, 1250.00, 'Electronics', 'ElectroHub Solutions'],
    ['Type-C Charging Cable', 'ELE-CABL-003', 'Fast sync and charge', 'cable.png', 25, 10, 450.00, 'Electronics', 'ElectroHub Solutions'],

    // Stationery
    ['Gel Pen Blue 10pk', 'STA-PEN-001', 'Smooth writing gel pens', 'pens.png', 50, 15, 100.00, 'Stationery', 'Daily Essentials Inc'],
    ['Spiral Notebook A5', 'STA-NOTE-002', '160 pages ruled', 'notebook.png', 35, 10, 120.00, 'Stationery', 'Daily Essentials Inc'],

    // Clothing
    ['Cotton T-Shirt Plain L', 'CLO-TSH-001', '100% Cotton breathable tee', 'tshirt.png', 20, 5, 550.00, 'Clothing', 'Global Distribution Ltd'],
    ['Woolen Socks 2pk', 'CLO-SOCK-002', 'Warm and cozy winter socks', 'socks.png', 15, 5, 250.00, 'Clothing', 'Global Distribution Ltd']
];

foreach ($products as $p) {
    if ($pdo->query("SELECT COUNT(*) FROM products WHERE sku = '{$p[1]}'")->fetchColumn() > 0) continue;
    
    $stmt = $pdo->prepare("INSERT INTO products (name, sku, description, image_name, stock_quantity, min_threshold, unit_price, category_id, supplier_id, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
    $stmt->execute([
        $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], 
        $catMap[$p[7]] ?? null, 
        $suppMap[$p[8]] ?? null
    ]);
    
    $productId = $pdo->lastInsertId();
    
    // Initial stock movement
    $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, movement_type, quantity, previous_quantity, new_quantity, reason, unit_price) VALUES (?, 1, 'bulk_in', ?, 0, ?, 'Initial stock seeding', ?)");
    $stmt->execute([$productId, $p[4], $p[4], $p[6]]);
}

// 4. Sample Purchase Orders
$po_numbers = ['PO-' . date('Y') . '-001', 'PO-' . date('Y') . '-002'];
foreach ($po_numbers as $idx => $po_num) {
    if ($pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE po_number = '{$po_num}'")->fetchColumn() > 0) continue;
    
    $suppId = ($idx == 0) ? $suppMap['National Foods'] : $suppMap['Global Distribution Ltd'];
    $status = ($idx == 0) ? 'received' : 'pending';
    
    $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, supplier_id, status, created_by) VALUES (?, ?, ?, 1)");
    $stmt->execute([$po_num, $suppId, $status]);
    $poId = $pdo->lastInsertId();
    
    // Add some line items
    $prodId = $pdo->query("SELECT id FROM products WHERE supplier_id = {$suppId} LIMIT 1")->fetchColumn();
    if ($prodId) {
        $stmt = $pdo->prepare("INSERT INTO po_line_items (po_id, product_id, quantity_ordered, unit_price) VALUES (?, ?, 50, 100.00)");
        $stmt->execute([$poId, $prodId]);
    }
}

// 5. Sample Sales Transactions for the last 30 days
$seedSkus = array_column($products, 1);
$skuPlaceholders = implode(',', array_fill(0, count($seedSkus), '?'));
$salesCountStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM sales_transactions st
     INNER JOIN products p ON p.id = st.product_id
     WHERE p.sku IN ($skuPlaceholders)"
);
$salesCountStmt->execute($seedSkus);

if ((int) $salesCountStmt->fetchColumn() === 0) {
    echo "Generating sales transactions...\n";
    $productStmt = $pdo->prepare(
        "SELECT id, unit_price
         FROM products
         WHERE sku IN ($skuPlaceholders)
         ORDER BY sku ASC"
    );
    $productStmt->execute($seedSkus);
    $allProductIds = $productStmt->fetchAll(PDO::FETCH_ASSOC);
    $regions = ['North', 'South', 'East', 'West', 'Central'];
    $stmt = $pdo->prepare("INSERT INTO sales_transactions (product_id, quantity, unit_price, sale_date, region, created_by) VALUES (?, ?, ?, ?, ?, 1)");

    for ($i = 0; $i < 40 && $allProductIds; $i++) {
        $prod = $allProductIds[$i % count($allProductIds)];
        $qty = ($i % 5) + 1;
        $date = date('Y-m-d', strtotime('-' . ($i % 30) . ' days'));
        $region = $regions[$i % count($regions)];

        $stmt->execute([$prod['id'], $qty, $prod['unit_price'], $date, $region]);
    }
} else {
    echo "Sales transactions already seeded; skipping.\n";
}

echo "Demo data for departmental store seeded successfully!\n";
