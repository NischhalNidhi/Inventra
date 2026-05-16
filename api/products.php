<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

header('Content-Type: application/json; charset=utf-8');

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();

if (!$authController->can('products.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$filters = [
    'keyword' => trim($_GET['keyword'] ?? ''),
    'category' => trim($_GET['category'] ?? ''),
    'stock_level' => trim($_GET['stock_level'] ?? ''),
    'archived' => trim($_GET['archived'] ?? ''),
];
$pagination = parsePagination($_GET);

$productsResult = $productModel->getAll($pagination['page'], $pagination['limit'], '', $filters);
$products = $productsResult['data'];
$data = array_map(
    static function (array $product) use ($authController): array {
        $qty = (int) $product['stock_quantity'];
        $threshold = (int) $product['min_threshold'];
        $isOut = $qty === 0;
        $isLow = !$isOut && $qty <= $threshold;
        $imageName = !empty($product['image_name']) ? basename((string) $product['image_name']) : null;
        $imagePath = $imageName ? dirname(__DIR__) . '/public/uploads/products/' . $imageName : null;
        if ($isOut) {
            $statusClass = 'out';
            $statusText  = 'OUT OF STOCK';
        } elseif ($isLow) {
            $statusClass = 'low';
            $statusText  = 'LOW STOCK';
        } else {
            $statusClass = 'healthy';
            $statusText  = 'IN STOCK';
        }

        return [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'image_name' => $product['image_name'] ?? null,
            'image_url' => mediaUrl(
                !empty($product['image_name']) ? 'products/' . $product['image_name'] : null,
                (string) $product['name'],
                'product'
            ),
            'category' => $product['category_name'] ?? 'Unassigned',
            'supplier' => $product['supplier_name'] ?? 'Unassigned',
            'unit_price' => (float) ($product['unit_price'] ?? 0),
            'quantity' => $qty,
            'min_stock' => $threshold,
            'status' => $statusText,
            'status_class' => $statusClass,
            'edit_url' => basePath('index.php?page=new-entry&id=' . $product['id']),
            'can_edit' => $authController->can('products.edit'),
            'can_archive' => $authController->can('products.archive'),
            'can_delete' => $authController->can('products.delete'),
        ];
    },
    $products
);

echo json_encode([
    'products' => $data,
    'page' => $pagination['page'],
    'limit' => $pagination['limit'],
    'total' => $productsResult['total'],
]);
