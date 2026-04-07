<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';
require_once __DIR__ . '/../database/bootstrap.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function resetTestDatabase(): void
{
    initializeConfiguredDatabase();
}

function runBackendAndIntegrationTests(): void
{
    extract(buildAppDependencies(), EXTR_SKIP);
    $auth = $authController;

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
        'unit_price' => 1499.99,
        'category_id' => $categoryId,
        'supplier_id' => $supplierId,
    ], (int) $manager['id']);

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

    $newUserData = [
        'full_name' => 'Refactor User ' . $suffix,
        'email' => 'refactor' . $suffix . '@inventra.local',
        'username' => 'refactor' . $suffix,
        'password_hash' => password_hash('StrongPass#123', PASSWORD_BCRYPT, ['cost' => 12]),
        'role' => 'Supervisor',
    ];
    $newUserId = $userModel->create($newUserData);
    assertTrue($newUserId > 0, 'Manager should be able to create staff accounts');

    $_SESSION = [];
    $newUserLogin = $auth->login($newUserData['username'], 'StrongPass#123');
    assertTrue($newUserLogin['success'] === true, 'Created user should be able to log in');

    $createdProduct = $productModel->findById($productId);
    assertTrue((float) $createdProduct['unit_price'] === 1499.99, 'Products should persist unit price');
}

function runFrontendSmokeChecks(): void
{
    $files = [
        __DIR__ . '/../views/products/form.php',
        __DIR__ . '/../views/products/index.php',
        __DIR__ . '/../views/reports/index.php',
        __DIR__ . '/../views/auth/index.php',
    ];

    foreach ($files as $file) {
        assertTrue(is_file($file), 'Missing required frontend file: ' . $file);
        $content = (string) file_get_contents($file);
        assertTrue($content !== '', 'Frontend file is empty: ' . $file);
    }

    $authView = (string) file_get_contents(__DIR__ . '/../views/auth/index.php');
    assertTrue(str_contains($authView, 'Forgot Password'), 'Auth UI should show forgot password placeholder');
    assertTrue(!str_contains($authView, 'Request Access'), 'Auth UI should no longer show request access');

    $productForm = (string) file_get_contents(__DIR__ . '/../views/products/form.php');
    assertTrue(str_contains($productForm, 'unit_price'), 'Product form should include unit price field');

    $js = (string) file_get_contents(__DIR__ . '/../public/js/app.js');
    assertTrue(str_contains($js, 'fetchProducts'), 'Frontend JS should include live search');
    assertTrue(str_contains($js, 'formatCurrency'), 'Frontend JS should format product price');
}

try {
    resetTestDatabase();
    runBackendAndIntegrationTests();
    runFrontendSmokeChecks();
    echo "All tests passed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Test failure: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}
