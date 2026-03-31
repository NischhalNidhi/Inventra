<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Stock.php';
require_once __DIR__ . '/../models/PurchaseOrder.php';
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../models/AccessRequest.php';
require_once __DIR__ . '/../controllers/authController.php';
require_once __DIR__ . '/../controllers/accessRequestController.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function runBackendAndIntegrationTests(): void
{
    $pdo = getDatabaseConnection();
    $userModel = new User($pdo);
    $auth = new AuthController($userModel);
    $categoryModel = new Category($pdo);
    $supplierModel = new Supplier($pdo);
    $productModel = new Product($pdo);
    $stockModel = new Stock($pdo);
    $poModel = new PurchaseOrder($pdo);
    $reportModel = new Report($pdo);
    $accessRequestModel = new AccessRequest($pdo);
    $accessRequestController = new AccessRequestController($accessRequestModel, $userModel);

    $suffix = (string) random_int(100000, 999999);
    $manager = $userModel->findByIdentifier('manager');
    assertTrue((bool) $manager, 'Manager seed user must exist');

    assertTrue($auth->can('dashboard') === false, 'RBAC requires active session user context');
    $_SESSION['user'] = [
        'id' => (int) $manager['id'],
        'full_name' => $manager['full_name'],
        'username' => $manager['username'],
        'email' => $manager['email'],
        'role' => $manager['role'],
    ];
    assertTrue($auth->can('users.create'), 'Manager should be able to create users');
    assertTrue($auth->can('reports.inventory'), 'Manager should access inventory reports');

    $categoryId = $categoryModel->create('Test Category ' . $suffix, 'test');
    $supplierId = $supplierModel->create([
        'name' => 'Test Supplier ' . $suffix,
        'contact_person' => 'Test Contact',
        'email' => 'supplier' . $suffix . '@inventra.local',
        'phone' => '9800000000',
    ]);
    $productId = $productModel->create([
        'name' => 'Test Product ' . $suffix,
        'sku' => 'INV-' . $suffix,
        'description' => 'test',
        'image_name' => null,
        'stock_quantity' => 10,
        'min_threshold' => 3,
        'category_id' => $categoryId,
        'supplier_id' => $supplierId,
    ], (int) $manager['id']);

    $stockResult = $stockModel->adjustStock($productId, 'out', 4, 'test out', (int) $manager['id']);
    assertTrue($stockResult['new_quantity'] === 6, 'Stock out should update quantity');

    $negativeBlocked = false;
    try {
        $stockModel->adjustStock($productId, 'out', 1000, 'overdraw test', (int) $manager['id']);
    } catch (RuntimeException) {
        $negativeBlocked = true;
    }
    assertTrue($negativeBlocked, 'Negative stock must be blocked');

    $poId = $poModel->create($supplierId, [['product_id' => $productId, 'quantity_ordered' => 5]], (int) $manager['id'], null);
    $poDetail = $poModel->findById($poId);
    assertTrue((bool) $poDetail, 'PO should be created');
    $lineId = (int) $poDetail['line_items'][0]['id'];
    $poModel->receive($poId, [$lineId => 5], (int) $manager['id']);
    $poAfterReceive = $poModel->findById($poId);
    assertTrue($poAfterReceive['status'] === 'received', 'PO should be received');

    $locked = false;
    try {
        $poModel->updateTracking($poId, [
            'carrier_name' => 'Carrier',
            'tracking_number' => 'TRACK123',
            'dispatch_date' => todayDate(),
            'expected_arrival' => todayDate(),
            'shipment_status' => 'in_transit',
        ]);
    } catch (RuntimeException) {
        $locked = true;
    }
    assertTrue($locked, 'Received PO must be locked');

    $reportModel->createSale([
        'product_id' => $productId,
        'quantity' => 2,
        'unit_price' => 99.99,
        'sale_date' => todayDate(),
        'region' => 'Kathmandu',
    ], (int) $manager['id']);

    $daily = $reportModel->getDailySales(todayDate(), todayDate());
    assertTrue(count($daily) >= 1, 'Daily sales should include recorded transaction');
    $inventory = $reportModel->getInventoryReport();
    assertTrue(count($inventory) >= 1, 'Inventory report should return rows');

    $accessRequestId = $accessRequestModel->create([
        'full_name' => 'Request User ' . $suffix,
        'email' => 'request' . $suffix . '@inventra.local',
        'desired_role' => 'Supervisor',
        'message' => 'integration test',
    ]);
    $approval = $accessRequestController->approveRequest($accessRequestId, (int) $manager['id'], 'approved by test');
    assertTrue((bool) ($approval['username'] ?? null), 'Approval should create username');
    assertTrue((bool) ($approval['temporary_password'] ?? null), 'Approval should generate temporary password');

    $_SESSION = [];
    $firstLogin = $auth->login((string) $approval['username'], (string) $approval['temporary_password']);
    assertTrue(!empty($firstLogin['requires_password_setup']), 'Approved access request should require first login password setup');

    $setupResult = $auth->completeFirstLoginPasswordSetup('NewPass#123', 'NewPass#123');
    assertTrue($setupResult['success'] === true, 'First login password setup should succeed');

    $_SESSION = [];
    $secondLogin = $auth->login((string) $approval['username'], 'NewPass#123');
    assertTrue($secondLogin['success'] === true, 'User should log in with new password after setup');
    assertTrue(empty($secondLogin['requires_password_setup']), 'Second login should not require password setup');
}

function runFrontendSmokeChecks(): void
{
    $files = [
        __DIR__ . '/../inventra_new_entry_refined.html',
        __DIR__ . '/../inventra_inventory_ledger_unique.html',
        __DIR__ . '/../views/products/form.php',
        __DIR__ . '/../views/products/index.php',
        __DIR__ . '/../views/reports/index.php',
    ];

    foreach ($files as $file) {
        assertTrue(is_file($file), 'Missing required frontend file: ' . $file);
        $content = (string) file_get_contents($file);
        assertTrue($content !== '', 'Frontend file is empty: ' . $file);
    }

    $js = (string) file_get_contents(__DIR__ . '/../public/js/app.js');
    assertTrue(str_contains($js, 'fetchProducts'), 'Frontend JS should include live search');
    assertTrue(str_contains($js, 'ajax-stock-form'), 'Frontend JS should include stock AJAX form behavior');
}

try {
    runBackendAndIntegrationTests();
    runFrontendSmokeChecks();
    echo "All tests passed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Test failure: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}
