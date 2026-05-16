<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

header('Content-Type: application/json; charset=utf-8');

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();
$authController->authorize('dashboard');

$recipients = $notificationModel->getAlertRecipients();
$notificationModel->generateLowStockAlerts($recipients, $mailer, appUrl('index.php?page=dashboard'));

echo json_encode([
    'stats'             => $productModel->getDashboardStats(),
    'alerts'            => $productModel->getDashboardAlerts(),
    'recent_activity'   => $productModel->getRecentActivity(),
    'featured_products' => $productModel->getFeaturedProducts(),
    'alert_graph'       => $productModel->getAlertGraphData(),
], JSON_UNESCAPED_SLASHES);
