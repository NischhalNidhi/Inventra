<?php

/**
 * Inventra - Unified Database Seeder
 * Seeds categories, suppliers, products, and historical sales data.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

$pdo = getDatabaseConnection();

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
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name, description) VALUES (?, ?)");
    $stmt->execute($cat);
}

$cats = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
$catMap = array_flip($cats);

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
    $stmt = $pdo->prepare("INSERT IGNORE INTO suppliers (name, contact_person, email, phone) VALUES (?, ?, ?, ?)");
    $stmt->execute($supp);
}

$supps = $pdo->query("SELECT id, name FROM suppliers")->fetchAll(PDO::FETCH_KEY_PAIR);
$suppMap = array_flip($supps);

echo "Seeding Products...\n";
$products = [
    // Beverages
    ['Full Cream Milk 1L', 'BVG-MILK-1', 'Fresh full cream milk from local farms', 'milk.png', 50, 20, 120.00, $catMap['Beverages'], $suppMap['National Foods']],
    ['Classic Cola 500ml', 'BVG-COLA-1', 'Refreshing carbonated soft drink', 'cola.png', 120, 30, 65.00, $catMap['Beverages'], $suppMap['National Foods']],
    ['Green Tea Bags (25 Pack)', 'BVG-TEA-G', 'Pure organic green tea for daily wellness', 'tea.png', 80, 20, 180.00, $catMap['Beverages'], $suppMap['National Foods']],
    
    // Grocery Staples
    ['Organic Whole Wheat Bread', 'GRC-BREAD-1', 'Freshly baked whole wheat bread daily', 'bread.png', 30, 15, 80.00, $catMap['Grocery Staples'], $suppMap['National Foods']],
    ['Basmati Rice 5kg', 'GRC-RICE-5', 'Premium long grain basmati rice for fine dining', 'rice.png', 60, 15, 850.00, $catMap['Grocery Staples'], $suppMap['Pantry Express']],
    ['Sunflower Cooking Oil 1L', 'GRC-OIL-1', 'Refined sunflower oil for healthy cooking', 'oil.png', 45, 12, 280.00, $catMap['Grocery Staples'], $suppMap['Pantry Express']],
    
    // Household
    ['All-Purpose Cleaner', 'HHD-CLN-1', 'Tough on stains, gentle on surfaces', 'cleaner.png', 40, 10, 250.00, $catMap['Household'], $suppMap['CleanHome Co']],
    ['Dishwashing Liquid 500ml', 'HHD-DISH-1', 'Cuts through grease effectively', 'dishwash.png', 45, 12, 120.00, $catMap['Household'], $suppMap['CleanHome Co']],
    ['Paper Napkins (50 Pack)', 'HHD-NAP-1', 'Soft and absorbent 2-ply napkins', 'napkins.png', 200, 50, 45.00, $catMap['Household'], $suppMap['Office Supply Co']],
    
    // Snacks
    ['Potato Chips (Classic)', 'SNK-CHIPS-1', 'Crunchy salted classic potato chips', 'chips.png', 100, 30, 50.00, $catMap['Snacks'], $suppMap['National Foods']],
    ['Digestive Biscuits', 'SNK-BIS-1', 'High-fiber digestive biscuits for tea time', 'biscuits.png', 60, 20, 95.00, $catMap['Snacks'], $suppMap['National Foods']],
    ['Dark Chocolate Bar', 'SNK-CHOC-1', '70% cocoa rich dark chocolate', 'chocolate.png', 50, 15, 150.00, $catMap['Snacks'], $suppMap['Global Trends Inc']],
    
    // Personal Care
    ['Anti-Dandruff Shampoo', 'PC-SHMP-1', 'Scalp care shampoo for healthy hair', 'shampoo.png', 25, 10, 350.00, $catMap['Personal Care'], $suppMap['CleanHome Co']],
    ['Moisturizing Soap Bar', 'PC-SOAP-1', 'Enriched with vitamin E for soft skin', 'soap.png', 150, 40, 45.00, $catMap['Personal Care'], $suppMap['CleanHome Co']],
    ['Gentle Face Wash', 'PC-FWASH-1', 'Daily face wash for all skin types', 'facewash.png', 40, 15, 195.00, $catMap['Personal Care'], $suppMap['CleanHome Co']],
    
    // Electronics
    ['AA Alkaline Batteries', 'ELC-BATT-AA', 'Long-lasting power for everyday devices', 'batteries.png', 300, 100, 250.00, $catMap['Electronics'], $suppMap['TechNova Solutions']],
    ['USB-C Charging Cable', 'ELC-CABLE-C', 'Fast charging and data sync cable 1m', 'cable.png', 80, 20, 450.00, $catMap['Electronics'], $suppMap['TechNova Solutions']],
    ['LED Desk Lamp', 'ELC-LAMP-1', 'Eye-friendly adjustable LED lamp', 'lamp.png', 15, 5, 1200.00, $catMap['Electronics'], $suppMap['TechNova Solutions']],
    
    // Stationery
    ['A5 Spiral Notebook', 'STN-NB-A5', 'High-quality ruled paper notebook', 'notebook.png', 120, 30, 85.00, $catMap['Stationery'], $suppMap['Office Supply Co']],
    ['Ballpoint Pens (Blue)', 'STN-PEN-B', 'Smooth writing blue ink pens (Pack of 10)', 'pens.png', 100, 25, 120.00, $catMap['Stationery'], $suppMap['Office Supply Co']],
    
    // Apparel
    ['Cotton Crew Neck T-Shirt', 'APR-TSH-1', '100% breathable cotton t-shirt', 'tshirt.png', 50, 15, 550.00, $catMap['Apparel'], $suppMap['Global Trends Inc']],
];

foreach ($products as $p) {
    if ($pdo->query("SELECT COUNT(*) FROM products WHERE sku = '{$p[1]}'")->fetchColumn() > 0) continue;
    $stmt = $pdo->prepare("INSERT INTO products (name, sku, description, image_name, stock_quantity, min_threshold, unit_price, category_id, supplier_id, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
    $stmt->execute($p);
}

echo "Generating historical sales for analytics...\n";
$products = $pdo->query("SELECT id, unit_price FROM products")->fetchAll(PDO::FETCH_ASSOC);
$regions = ['North', 'South', 'East', 'West', 'Central'];
$stmt = $pdo->prepare("INSERT INTO sales_transactions (product_id, quantity, unit_price, sale_date, region, created_by) VALUES (?, ?, ?, ?, ?, 1)");

// Clear old transactions if any
$pdo->exec("DELETE FROM sales_transactions");

// Seed Current Month
$currentMonthDays = (int)date('d');
for ($i = 0; $i < 200; $i++) {
    $prod = $products[array_rand($products)];
    $qty = rand(1, 10);
    $daysAgo = rand(0, $currentMonthDays - 1);
    $date = date('Y-m-d', strtotime("-$daysAgo days"));
    $region = $regions[array_rand($regions)];
    $stmt->execute([$prod['id'], $qty, $prod['unit_price'], $date, $region]);
}

// Seed Previous Month
for ($i = 0; $i < 250; $i++) {
    $prod = $products[array_rand($products)];
    $qty = rand(1, 10);
    $date = date('Y-m-d', strtotime('first day of last month +' . rand(0, 27) . ' days'));
    $region = $regions[array_rand($regions)];
    $stmt->execute([$prod['id'], $qty, $prod['unit_price'], $date, $region]);
}

echo "Database successfully seeded with real product data and images!\n";
