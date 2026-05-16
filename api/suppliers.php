<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();
$authController->authorize('suppliers.view');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $supplier = $supplierModel->findById($id);
        if (!$supplier) {
            jsonResponse(['error' => 'Supplier not found.', 'code' => 'SUPPLIER_NOT_FOUND'], 404);
        }
        jsonResponse(['supplier' => $supplier]);
    }
    
    $search = trim($_GET['search'] ?? '');
    $onlyActive = (($_GET['active'] ?? '1') === '1');
    $suppliers = $supplierModel->getAll(1, 1000, $search, $onlyActive);
    jsonResponse(['suppliers' => $suppliers['data']]);
}

jsonResponse(['error' => 'Method not allowed.', 'code' => 'METHOD_NOT_ALLOWED'], 405);
