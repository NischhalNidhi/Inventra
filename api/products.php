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

$products = $productModel->getAll($filters, $pagination['limit'], $pagination['offset']);
$data = array_map(
    static function (array $product) use ($authController): array {
        $low = (int) $product['stock_quantity'] <= (int) $product['min_threshold'];

        return [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'category' => $product['category_name'] ?? 'Unassigned',
            'supplier' => $product['supplier_name'] ?? 'Unassigned',
            'unit_price' => (float) ($product['unit_price'] ?? 0),
            'quantity' => (int) $product['stock_quantity'],
            'min_stock' => (int) $product['min_threshold'],
            'status' => $low ? 'LOW STOCK' : 'IN STOCK',
            'status_class' => $low ? 'low' : 'healthy',
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
    'total' => $productModel->countAll($filters),
]);
