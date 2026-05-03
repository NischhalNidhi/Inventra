<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';
require_once __DIR__ . '/../database/bootstrap.php';

$_ENV['APP_URL'] = 'http://localhost/Inventra/public';
$_SERVER['APP_URL'] = 'http://localhost/Inventra/public';
$_ENV['MAIL_LOG_PATH'] = 'tests/_output/mail.log';
$_SERVER['MAIL_LOG_PATH'] = 'tests/_output/mail.log';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function resetTestDatabase(): void
{
    $mailLog = __DIR__ . '/_output/mail.log';
    if (is_file($mailLog)) {
        @file_put_contents($mailLog, '');
    }
    initializeConfiguredDatabase();
}

function latestTokenFromMailLog(): string
{
    $mailLog = __DIR__ . '/_output/mail.log';
    assertTrue(is_file($mailLog), 'Mail log should be created');
    $content = (string) file_get_contents($mailLog);
    preg_match_all('/token=([a-f0-9]+)/', $content, $matches);
    assertTrue(!empty($matches[1]), 'Mail log should contain a token link');

    return end($matches[1]);
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
    assertTrue($auth->can('reports.sales.insight'), 'Manager should access AI sales insights');

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
    $recentActivity = $productModel->getRecentActivity();
    assertTrue(count($recentActivity) >= 1, 'Dashboard recent activity should include stock movement events');
    $dashboardAlerts = $productModel->getDashboardAlerts();
    assertTrue(is_array($dashboardAlerts), 'Dashboard alerts should be queryable');

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
    $newUserId = $userModel->createPendingSetup($newUserData);
    assertTrue($newUserId > 0, 'Manager should be able to create pending staff accounts');
    $pendingUser = $userModel->findByIdentifier($newUserData['email']);
    assertTrue((int) $pendingUser['is_active'] === 0, 'New staff account should be inactive until setup');
    $welcomeEmail = $auth->sendAccountSetupEmail($pendingUser);
    assertTrue($welcomeEmail['success'] === true, 'Welcome setup email should be sent');

    $setupToken = latestTokenFromMailLog();
    $setup = $auth->completePasswordTokenSetup($setupToken, 'account_setup', 'StrongPass#123', 'StrongPass#123');
    assertTrue($setup['success'] === true, 'Setup token should activate the user and save password');
    $setupReuse = $auth->completePasswordTokenSetup($setupToken, 'account_setup', 'StrongPass#123', 'StrongPass#123');
    assertTrue($setupReuse['success'] === false, 'Setup token should not be reusable');

    $_SESSION = [];
    $newUserLogin = $auth->login($newUserData['username'], 'StrongPass#123');
    assertTrue($newUserLogin['success'] === true, 'Setup user should be able to log in normally');

    $unknownReset = $auth->requestPasswordReset('unknown' . $suffix . '@inventra.local');
    assertTrue($unknownReset['success'] === true, 'Unknown forgot-password requests should get generic success');
    $existingReset = $auth->requestPasswordReset($newUserData['email']);
    assertTrue($existingReset['success'] === true, 'Existing forgot-password requests should get generic success');
    $resetToken = latestTokenFromMailLog();
    $reset = $auth->completePasswordTokenSetup($resetToken, 'password_reset', 'ResetPass#123', 'ResetPass#123');
    assertTrue($reset['success'] === true, 'Password reset token should update password');
    $resetReuse = $auth->completePasswordTokenSetup($resetToken, 'password_reset', 'ResetPass#123', 'ResetPass#123');
    assertTrue($resetReuse['success'] === false, 'Password reset token should not be reusable');
    $_SESSION = [];
    $resetLogin = $auth->login($newUserData['email'], 'ResetPass#123');
    assertTrue($resetLogin['success'] === true, 'User should log in with reset password');

    $expiredToken = 'expired' . $suffix;
    $userModel->createPasswordToken($newUserId, 'password_reset', hash('sha256', $expiredToken), date('Y-m-d H:i:s', time() - 3600));
    $expiredResult = $auth->completePasswordTokenSetup($expiredToken, 'password_reset', 'AnotherPass#123', 'AnotherPass#123');
    assertTrue($expiredResult['success'] === false, 'Expired password reset token should be rejected');

    $createdProduct = $productModel->findById($productId);
    assertTrue((float) $createdProduct['unit_price'] === 1499.99, 'Products should persist unit price');
    $inventorySummary = $reportModel->getInventorySummary();
    assertTrue($inventorySummary['total_skus'] >= 1, 'Inventory summary should include active products');
    $insightData = $reportModel->getCurrentMonthSalesInsightData();
    assertTrue(isset($insightData['summary']['transaction_count']), 'AI sales insight payload should expose monthly summary data');
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
    assertTrue(str_contains($authView, 'request_password_reset'), 'Auth UI should submit a password reset request');
    assertTrue(str_contains($authView, 'reset_password_with_token'), 'Auth UI should support reset password tokens');
    assertTrue(!str_contains($authView, 'Request Access'), 'Auth UI should not show Request Access');

    $usersView = (string) file_get_contents(__DIR__ . '/../views/users/index.php');
    assertTrue(!str_contains($usersView, 'name="password"'), 'Staff creation should not ask managers for an initial password');
    assertTrue(str_contains($usersView, 'welcome setup links'), 'Staff creation should explain welcome setup emails');

    $productForm = (string) file_get_contents(__DIR__ . '/../views/products/form.php');
    assertTrue(str_contains($productForm, 'unit_price'), 'Product form should include unit price field');

    $js = (string) file_get_contents(__DIR__ . '/../public/js/app.js');
    assertTrue(str_contains($js, 'fetchProducts'), 'Frontend JS should include live search');
    assertTrue(str_contains($js, 'formatCurrency'), 'Frontend JS should format product price');
    assertTrue(str_contains($js, 'data-sales-insight-card'), 'Frontend JS should initialize the AI sales insight card');

    $reportsView = (string) file_get_contents(__DIR__ . '/../views/reports/index.php');
    assertTrue(str_contains($reportsView, 'AI Sales Insight'), 'Reports view should render the AI sales insight card');

    $dashboardView = (string) file_get_contents(__DIR__ . '/../views/dashboard/index.php');
    assertTrue(str_contains($dashboardView, 'Low Stock Watchlist'), 'Dashboard should show low-stock watchlist');
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
