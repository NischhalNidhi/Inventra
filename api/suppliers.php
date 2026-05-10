<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../controllers/authController.php';
require_once __DIR__ . '/../controllers/supplierController.php';

$pdo = getDatabaseConnection();
$authController = new AuthController(new User($pdo));
$supplierModel = new Supplier($pdo);
$supplierController = new SupplierController($supplierModel);
require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();
$authController->authorize('suppliers.view');

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse(['suppliers' => $supplierModel->getAll(1, 200)]);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse(['error' => 'Invalid request token.', 'code' => 'INVALID_TOKEN'], 422);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController->authorize('suppliers.manage');
    $validated = $supplierController->validate($_POST, $_FILES);
    if ($validated['errors']) {
        jsonResponse(['error' => implode(' ', $validated['errors']), 'code' => 'VALIDATION_ERROR'], 422);
    }
    $supplierId = $supplierModel->create($validated['data']);
    jsonResponse(['supplier_id' => $supplierId], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && ($_GET['action'] ?? '') === 'deactivate') {
    $authController->authorize('suppliers.manage');
    $supplierModel->deactivate($id);
    jsonResponse(['message' => 'Supplier deactivated.']);
}

jsonResponse(['error' => 'Method not allowed.', 'code' => 'METHOD_NOT_ALLOWED'], 405);
