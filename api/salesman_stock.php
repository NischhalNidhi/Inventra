<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$type = trim($_GET['type'] ?? '') ?: trim($_POST['type'] ?? '');

if ($method === 'GET' && $type === 'list') {
    // List allocations for a salesman (query param salesman_id). Managers can list any; salesmen only their own.
    $salesmanId = isset($_GET['salesman_id']) && $_GET['salesman_id'] !== '' ? (int) $_GET['salesman_id'] : (int) currentUser()['id'];
    // Allow managers to view others
    if (currentUser()['role'] !== 'Manager' && $salesmanId !== (int) currentUser()['id']) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $rows = $salesmanStockModel->listAllocationsForSalesman($salesmanId);
    jsonResponse(['success' => true, 'rows' => $rows]);
}

if ($method === 'POST' && $type === 'allocate') {
    // Only managers can allocate
    if (currentUser()['role'] !== 'Manager') {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $salesmanId = isset($_POST['salesman_id']) ? (int) $_POST['salesman_id'] : 0;
    $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
    $note = trim($_POST['note'] ?? '') ?: null;

    if ($salesmanId <= 0 || $productId <= 0 || $quantity <= 0) {
        jsonResponse(['error' => 'Invalid input'], 422);
    }

    try {
        $id = $salesmanStockModel->allocateStock($salesmanId, $productId, $quantity, (int) currentUser()['id'], $note);
        jsonResponse(['success' => true, 'allocation_id' => $id]);
    } catch (Throwable $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}

if ($method === 'POST' && $type === 'reduce') {
    // Reduce allocation (e.g., record sale against allocation)
    $allocationId = isset($_POST['allocation_id']) ? (int) $_POST['allocation_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
    $reason = trim($_POST['reason'] ?? '') ?: null;

    if ($allocationId <= 0 || $quantity <= 0) {
        jsonResponse(['error' => 'Invalid input'], 422);
    }

    // Salesman can reduce their own allocations; managers can reduce any
    $alloc = $salesmanStockModel->getAllocationById($allocationId);
    if (!$alloc) jsonResponse(['error' => 'Allocation not found'], 404);
    $salesmanId = (int) $alloc['salesman_id'];
    if (currentUser()['role'] !== 'Manager' && $salesmanId !== (int) currentUser()['id']) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    try {
        $salesmanStockModel->reduceAllocation($allocationId, $quantity, (int) currentUser()['id'], $reason);
        jsonResponse(['success' => true]);
    } catch (Throwable $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}

jsonResponse(['error' => 'Invalid request'], 400);
