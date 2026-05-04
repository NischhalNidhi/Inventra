<?php

/**
 * Inventra - Analytics and Historical Sales Seeder
 * Generates historical sales data for the current and previous month
 * to facilitate AI-driven business insights.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

$pdo = getDatabaseConnection();

echo "Generating historical sales for analytics...\n";

// Get all product IDs and prices
$products = $pdo->query("SELECT id, unit_price, category_id FROM products")->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    die("No products found. Please run seed_department_store.php first.\n");
}

$regions = ['North', 'South', 'East', 'West', 'Central'];
$stmt = $pdo->prepare("INSERT INTO sales_transactions (product_id, quantity, unit_price, sale_date, region, created_by) VALUES (?, ?, ?, ?, ?, 1)");

// 1. Seed Current Month (Up to today)
$currentMonthDays = (int)date('d');
for ($i = 0; $i < 100; $i++) {
    $prod = $products[array_rand($products)];
    $qty = rand(1, 10);
    $daysAgo = rand(0, $currentMonthDays - 1);
    $date = date('Y-m-d', strtotime("-$daysAgo days"));
    $region = $regions[array_rand($regions)];
    $stmt->execute([$prod['id'], $qty, $prod['unit_price'], $date, $region]);
}

// 2. Seed Previous Month (Full month)
for ($i = 0; $i < 120; $i++) {
    $prod = $products[array_rand($products)];
    $qty = rand(1, 10);
    // Random day in the previous month
    $date = date('Y-m-d', strtotime('first day of last month +' . rand(0, 27) . ' days'));
    $region = $regions[array_rand($regions)];
    $stmt->execute([$prod['id'], $qty, $prod['unit_price'], $date, $region]);
}

echo "Historical analytics data seeded successfully!\n";
