<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/PurchaseOrder.php';
require_once __DIR__ . '/../controllers/authController.php';

$pdo = getDatabaseConnection();
$authController = new AuthController(new User($pdo));
$productModel = new Product($pdo);
$poModel = new PurchaseOrder($pdo);
$authController->requireAuthentication();

$type = trim($_GET['type'] ?? '');
if ($type === 'reorder-suggestions') {
    $authController->authorize('logistics.reorder');
    jsonResponse(['products' => $productModel->getLowStockProducts()]);
}
if ($type === 'delivery-log') {
    $authController->authorize('logistics.delivery_log');
    $fromDate = trim($_GET['from_date'] ?? '');
    $toDate = trim($_GET['to_date'] ?? '');
    jsonResponse(['entries' => $poModel->getDeliveryLog($fromDate ?: null, $toDate ?: null)]);
}

jsonResponse(['error' => 'Unknown logistics type.', 'code' => 'UNKNOWN_LOGISTICS_TYPE'], 400);
