<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
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
