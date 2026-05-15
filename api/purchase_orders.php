<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();
$authController->authorize('po.view');

$id = (int) ($_GET['id'] ?? 0);
$action = trim($_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($id > 0) {
        $po = $poModel->findById($id);
        if (!$po) {
            jsonResponse(['error' => 'Purchase order not found.', 'code' => 'PO_NOT_FOUND'], 404);
        }
        jsonResponse(['purchase_order' => $po]);
    }
    
    $pagination = parsePagination($_GET);
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '') ?: null;
    $poData = $poModel->getAll($pagination['page'], $pagination['limit'], $search, $status);
    jsonResponse(['purchase_orders' => $poData]);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse(['error' => 'Invalid request token.', 'code' => 'INVALID_TOKEN'], 422);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $authController->authorize('po.create');
        $validated = $poController->validateCreate($_POST);
        if ($validated['errors']) {
            jsonResponse(['error' => implode(' ', $validated['errors']), 'code' => 'VALIDATION_ERROR'], 422);
        }
        $poId = $poModel->create(
            $validated['data']['supplier_id'],
            $validated['data']['line_items'],
            (int) currentUser()['id'],
            $validated['data']['expected_date']
        );
        jsonResponse(['purchase_order' => $poModel->findById($poId)], 201);
    }
    if ($action === 'tracking') {
        $authController->authorize('po.tracking');
        $po = $poModel->findById($id);
        if (!$po) {
            jsonResponse(['error' => 'Purchase order not found.', 'code' => 'PO_NOT_FOUND'], 404);
        }
        $validated = $poController->validateTracking($_POST);
        if ($validated['errors']) {
            jsonResponse(['error' => implode(' ', $validated['errors']), 'code' => 'VALIDATION_ERROR'], 422);
        }
        $poModel->updateTracking($id, $validated['data']);
        jsonResponse(['purchase_order' => $poModel->findById($id)]);
    }
    if ($action === 'receive') {
        $authController->authorize('po.receive');
        $po = $poModel->findById($id);
        if (!$po) {
            jsonResponse(['error' => 'Purchase order not found.', 'code' => 'PO_NOT_FOUND'], 404);
        }
        $lineIds = $_POST['line_id'] ?? [];
        $lineReceived = $_POST['line_received'] ?? [];
        $receivedMap = [];
        foreach ($lineIds as $index => $lineId) {
            $receivedMap[(int) $lineId] = (int) ($lineReceived[$index] ?? 0);
        }
        $poModel->receive($id, $receivedMap, (int) currentUser()['id']);
        jsonResponse(['purchase_order' => $poModel->findById($id)]);
    }
}

jsonResponse(['error' => 'Method not allowed.', 'code' => 'METHOD_NOT_ALLOWED'], 405);
