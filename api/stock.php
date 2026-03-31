<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Stock.php';
require_once __DIR__ . '/../controllers/authController.php';
require_once __DIR__ . '/../controllers/stockController.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDatabaseConnection();
$authController = new AuthController(new User($pdo));
$authController->requireAuthentication();

if (!$authController->can('stock.in') && !$authController->can('stock.out')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request token.']);
    exit;
}

$movementType = $_POST['movement_type'] ?? '';
if ($movementType === 'in' && !$authController->can('stock.in')) {
    http_response_code(403);
    echo json_encode(['error' => 'Stock in not allowed for this role.']);
    exit;
}

if ($movementType === 'out' && !$authController->can('stock.out')) {
    http_response_code(403);
    echo json_encode(['error' => 'Stock out not allowed for this role.']);
    exit;
}

$stockController = new StockController(new Stock($pdo));

try {
    $result = $stockController->handleAdjustment($_POST, (int) currentUser()['id']);
    if (!$result['success']) {
        http_response_code(422);
        echo json_encode(['error' => implode(' ', $result['errors'])]);
        exit;
    }

    echo json_encode(['message' => $result['message']]);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()]);
}
