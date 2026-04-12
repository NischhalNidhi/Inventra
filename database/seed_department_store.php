<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

$pdo = getDatabaseConnection();

$pdo->exec("INSERT IGNORE INTO categories (name, description) VALUES 
    ('Beverages', 'Soft drinks, milk, juices'),
    ('Grocery Staples', 'Rice, bread, flour'),
    ('Household', 'Cleaning supplies'),
    ('Snacks', 'Chips, biscuits'),
    ('Personal Care', 'Shampoo, soap')
");

$cats = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
$catMap = array_flip($cats);

$pdo->exec("INSERT IGNORE INTO suppliers (name, contact_person, email, phone) VALUES 
    ('National Foods', 'John Doe', 'john@national.local', '123456789'),
    ('CleanHome Co', 'Jane Doe', 'jane@clean.local', '987654321')
");

$supps = $pdo->query("SELECT id, name FROM suppliers")->fetchAll(PDO::FETCH_KEY_PAIR);
$suppMap = array_flip($supps);

$products = [
    ['Full Cream Milk 1L', 'DAIRY-MILK-1', 'Fresh full cream milk', 'milk.png', 50, 20, 120.00, $catMap['Beverages'] ?? 1, $suppMap['National Foods'] ?? 1],
    ['Organic Whole Wheat Bread', 'BAKERY-BRD-1', 'Fresh baked daily', 'bread.png', 30, 15, 80.00, $catMap['Grocery Staples'] ?? 1, $suppMap['National Foods'] ?? 1],
    ['Floor Cleaner Lemon 1L', 'CLEAN-FLR-1', 'Citrus fresh surface cleaner', 'cleaner.png', 40, 10, 250.00, $catMap['Household'] ?? 1, $suppMap['CleanHome Co'] ?? 1],
    ['Potato Chips Salted', 'SNK-CHIPS-1', 'Classic salted potato chips', 'chips.png', 100, 30, 50.00, $catMap['Snacks'] ?? 1, $suppMap['National Foods'] ?? 1],
    ['Shampoo Anti-Dandruff 200ml', 'PC-SHMP-1', 'Daily use shampoo', 'shampoo.png', 25, 10, 350.00, $catMap['Personal Care'] ?? 1, $suppMap['CleanHome Co'] ?? 1],
];

foreach ($products as $p) {
    if ($pdo->query("SELECT COUNT(*) FROM products WHERE sku = '{$p[1]}'")->fetchColumn() > 0) continue;
    $stmt = $pdo->prepare("INSERT INTO products (name, sku, description, image_name, stock_quantity, min_threshold, unit_price, category_id, supplier_id, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
    $stmt->execute($p);
}

echo "Department store products seeded!\n";
