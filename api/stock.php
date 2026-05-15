<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

sendSecurityHeaders();

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();

if (!$authController->can('stock.in') && !$authController->can('stock.out')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden Access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request token.', 'code' => 'INVALID_TOKEN']);
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

try {
    $result = $stockController->handleAdjustment($_POST, (int) currentUser()['id']);
    if (!$result['success']) {
        http_response_code(422);
        echo json_encode(['error' => implode(' ', $result['errors']), 'code' => 'VALIDATION_ERROR']);
        exit;
    }

    echo json_encode(['message' => $result['message']]);
} catch (Throwable $exception) {
    error_log("Stock Adjustment Error: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'An internal server error occurred while processing stock adjustment.', 'code' => 'INTERNAL_ERROR']);
}
