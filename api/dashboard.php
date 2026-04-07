<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();
$authController->authorize('dashboard');

$type = trim($_GET['type'] ?? 'summary');
if ($type === 'summary') {
    jsonResponse(['summary' => $productModel->getDashboardStats()]);
}
if ($type === 'alert-graph') {
    $authController->authorize('dashboard.alert_graph');
    jsonResponse(['points' => $productModel->getAlertGraphData()]);
}
if ($type === 'activity') {
    $authController->authorize('dashboard.activity');
    jsonResponse(['activity' => $stockModel->getRecentHistory(20)]);
}

jsonResponse(['error' => 'Unknown dashboard type.', 'code' => 'UNKNOWN_DASHBOARD_TYPE'], 400);
