<?php
declare(strict_types=1);

// Seeder for populating sales_transactions with realistic sample rows
// Usage: php scripts/seed_heatmap.php [--count=200] [--days=90]

require_once __DIR__ . '/../core/dependencies.php';
extract(buildAppDependencies(), EXTR_SKIP);

$options = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0) {
        [$k, $v] = array_pad(explode('=', $arg, 2), 2, '');
        $options[substr($k, 2)] = $v;
    }
}

$count = isset($options['count']) && is_numeric($options['count']) ? (int) $options['count'] : 200;
$days = isset($options['days']) && is_numeric($options['days']) ? (int) $options['days'] : 90;

$productsData = $productModel->getAll(1, 1000, '', ['archived' => '0']);
$products = $productsData['data'] ?? [];
if (!$products) {
    echo "No products found. Add products first before seeding sales data." . PHP_EOL;
    exit(1);
}

$existingRegions = $reportModel->getUniqueRegions();
$defaultRegions = ['North Store', 'Central Hub', 'East Mall', 'West Plaza', 'South Store'];
$regions = $existingRegions ?: $defaultRegions;

$inserted = 0;
$now = new DateTimeImmutable();

for ($i = 0; $i < $count; $i++) {
    $prod = $products[array_rand($products)];
    $productId = (int) ($prod['id'] ?? 0);
    if ($productId <= 0) continue;

    $daysAgo = rand(0, max(1, $days - 1));
    $saleDate = $now->modify("-{$daysAgo} days")->format('Y-m-d');
    $saleTime = sprintf('%02d:%02d:%02d', rand(8, 20), rand(0, 59), rand(0, 59));

    $quantity = rand(1, 6);
    $unitPrice = isset($prod['unit_price']) ? (float) $prod['unit_price'] : rand(100, 1500) / 10.0;
    $region = $regions[array_rand($regions)];

    $invoiceId = 'SD' . strtoupper(bin2hex(random_bytes(4)));

    $data = [
        'invoice_id' => $invoiceId,
        'branch_code' => 'SEED',
        'city' => '',
        'customer_type' => 'retail',
        'customer_gender' => null,
        'product_id' => $productId,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'sale_date' => $saleDate,
        'sale_time' => $saleTime,
        'sold_at' => $saleDate . ' ' . $saleTime,
        'region' => $region,
        'payment_method' => 'cash',
        'tax_amount' => 0,
        'gross_total' => round($quantity * $unitPrice, 2),
        'cogs' => null,
        'gross_margin_percentage' => null,
        'gross_income' => null,
        'rating' => null,
    ];

    try {
        $reportModel->createSale($data, 1, 'seed');
        $inserted++;
    } catch (Throwable $e) {
        // continue on error but report
        echo "Failed to insert row: " . $e->getMessage() . PHP_EOL;
    }
}

echo "Inserted {$inserted} seeded sales rows (target {$count})." . PHP_EOL;
echo "Regions used: " . implode(', ', $regions) . PHP_EOL;
echo "Run heatmap page in the app to visualize real data.\n";
