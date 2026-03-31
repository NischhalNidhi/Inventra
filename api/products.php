<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../controllers/authController.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDatabaseConnection();
$authController = new AuthController(new User($pdo));
$authController->requireAuthentication();

if (!$authController->can('products.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$productModel = new Product($pdo);
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
