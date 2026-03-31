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
require_once __DIR__ . '/../models/ReportImportParser.php';
require_once __DIR__ . '/../models/AccessRequest.php';
require_once __DIR__ . '/../controllers/authController.php';
require_once __DIR__ . '/../controllers/userController.php';
require_once __DIR__ . '/../controllers/categoryController.php';
require_once __DIR__ . '/../controllers/supplierController.php';
require_once __DIR__ . '/../controllers/productController.php';
require_once __DIR__ . '/../controllers/stockController.php';
require_once __DIR__ . '/../controllers/purchaseOrderController.php';
require_once __DIR__ . '/../controllers/reportController.php';
require_once __DIR__ . '/../controllers/accessRequestController.php';

$pdo = getDatabaseConnection();

$userModel = new User($pdo);
$categoryModel = new Category($pdo);
$supplierModel = new Supplier($pdo);
$productModel = new Product($pdo);
$stockModel = new Stock($pdo);
$poModel = new PurchaseOrder($pdo);
$reportModel = new Report($pdo);
$accessRequestModel = new AccessRequest($pdo);

$authController = new AuthController($userModel);
$userController = new UserController($userModel);
$categoryController = new CategoryController($categoryModel);
$supplierController = new SupplierController($supplierModel);
$productController = new ProductController($productModel);
$stockController = new StockController($stockModel);
$poController = new PurchaseOrderController($poModel);
$reportController = new ReportController($reportModel, new ReportImportParser());
$accessRequestController = new AccessRequestController($accessRequestModel, $userModel);

$page = $_GET['page'] ?? 'dashboard';
$errors = [];
$editingProduct = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid security token. Please try again.');
        redirectTo(basePath('index.php?page=' . $page));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $result = $authController->login($_POST['identifier'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            if (!empty($result['requires_password_setup'])) {
                setFlash('success', 'First-time login detected. Set your account password to continue.');
                redirectTo(basePath('index.php?mode=set-password'));
            }
            setFlash('success', 'Welcome back to Inventra.');
            redirectTo(basePath('index.php?page=' . $result['landing_page']));
        }
        $errors = $result['errors'];
    }

    if (!in_array($action, ['login', 'request_access', 'set_password_first_login'], true)) {
        $authController->requireAuthentication();
    }

    try {
        switch ($action) {
            case 'create_user':
                $authController->authorize('users.create');
                $validated = $userController->validateCreate($_POST);
                if ($validated['errors']) {
                    setFlash('error', implode(' ', $validated['errors']));
                } else {
                    $userModel->create($validated['data']);
                    setFlash('success', 'User account created.');
                }
                redirectTo(basePath('index.php?page=users'));
                break;

            case 'approve_access_request':
                $authController->authorize('users.create');
                $requestId = (int) ($_POST['request_id'] ?? 0);
                $approval = $accessRequestController->approveRequest(
                    $requestId,
                    (int) currentUser()['id'],
                    trim($_POST['review_note'] ?? '') ?: null
                );
                setFlash(
                    'success',
                    sprintf(
                        'Access request approved. Username: %s, Temporary password: %s. Password setup required on first login.',
                        $approval['username'],
                        $approval['temporary_password']
                    )
                );
                redirectTo(basePath('index.php?page=users'));
                break;

            case 'reject_access_request':
                $authController->authorize('users.create');
                $requestId = (int) ($_POST['request_id'] ?? 0);
                $request = $accessRequestModel->findById($requestId);
                if (!$request || $request['status'] !== 'pending') {
                    throw new RuntimeException('Access request not found or already processed.');
                }
                $accessRequestModel->reject(
                    $requestId,
                    (int) currentUser()['id'],
                    trim($_POST['review_note'] ?? '') ?: null
                );
                setFlash('success', 'Access request rejected.');
                redirectTo(basePath('index.php?page=users'));
                break;

            case 'update_user':
                $authController->authorize('users.edit');
                $userId = (int) ($_POST['user_id'] ?? 0);
                $validated = $userController->validateUpdate($userId, $_POST);
                if ($validated['errors']) {
                    setFlash('error', implode(' ', $validated['errors']));
                } else {
                    $userModel->update($userId, $validated['data']);
                    setFlash('success', 'User account updated.');
                }
                redirectTo(basePath('index.php?page=users'));
                break;

            case 'deactivate_user':
                $authController->authorize('users.deactivate');
                $userModel->deactivate((int) ($_POST['user_id'] ?? 0));
                setFlash('success', 'User deactivated.');
                redirectTo(basePath('index.php?page=users'));
                break;

            case 'create_category':
                $authController->authorize('categories.manage');
                $validated = $categoryController->validate($_POST);
                if ($validated['errors']) {
                    setFlash('error', implode(' ', $validated['errors']));
                } else {
                    $categoryModel->create($validated['data']['name'], $validated['data']['description']);
                    setFlash('success', 'Category added.');
                }
                redirectTo(basePath('index.php?page=categories'));
                break;

            case 'update_category':
                $authController->authorize('categories.manage');
                $categoryId = (int) ($_POST['category_id'] ?? 0);
                $validated = $categoryController->validate($_POST);
                if ($validated['errors']) {
                    setFlash('error', implode(' ', $validated['errors']));
                } else {
                    $categoryModel->update($categoryId, $validated['data']['name'], $validated['data']['description']);
                    setFlash('success', 'Category updated.');
                }
                redirectTo(basePath('index.php?page=categories'));
                break;

            case 'delete_category':
                $authController->authorize('categories.manage');
                $categoryId = (int) ($_POST['category_id'] ?? 0);
                if ($categoryModel->hasAssignedProducts($categoryId)) {
                    setFlash('error', 'Category cannot be deleted while products are assigned.');
                } else {
                    $categoryModel->delete($categoryId);
                    setFlash('success', 'Category deleted.');
                }
                redirectTo(basePath('index.php?page=categories'));
                break;

            case 'create_supplier':
                $authController->authorize('suppliers.manage');
                $validated = $supplierController->validate($_POST);
                if ($validated['errors']) {
                    setFlash('error', implode(' ', $validated['errors']));
                } else {
                    $supplierModel->create($validated['data']);
                    setFlash('success', 'Supplier created.');
                }
                redirectTo(basePath('index.php?page=suppliers'));
                break;

            case 'update_supplier':
                $authController->authorize('suppliers.manage');
                $supplierId = (int) ($_POST['supplier_id'] ?? 0);
                $validated = $supplierController->validate($_POST);
                if ($validated['errors']) {
                    setFlash('error', implode(' ', $validated['errors']));
                } else {
                    $supplierModel->update($supplierId, $validated['data']);
                    setFlash('success', 'Supplier updated.');
                }
                redirectTo(basePath('index.php?page=suppliers'));
                break;

            case 'deactivate_supplier':
                $authController->authorize('suppliers.manage');
                $supplierModel->deactivate((int) ($_POST['supplier_id'] ?? 0));
                setFlash('success', 'Supplier deactivated.');
                redirectTo(basePath('index.php?page=suppliers'));
                break;

            case 'create_product':
                $authController->authorize('products.create');
                persistOldInput($_POST);
                $result = $productController->handleCreate($_POST, $_FILES, (int) currentUser()['id']);
                if ($result['success']) {
                    clearOldInput();
                    setFlash('success', 'Product created successfully.');
                    redirectTo(basePath('index.php?page=products'));
                }
                $errors = $result['errors'];
                $page = 'new-entry';
                break;

            case 'update_product':
                $authController->authorize('products.edit');
                $productId = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);
                persistOldInput($_POST);
                $result = $productController->handleUpdate($productId, $_POST, $_FILES, (int) currentUser()['id']);
                if ($result['success']) {
                    clearOldInput();
                    setFlash('success', 'Product updated successfully.');
                    redirectTo(basePath('index.php?page=products'));
                }
                $errors = $result['errors'];
                $page = 'new-entry';
                $editingProduct = $productModel->findById($productId);
                break;

            case 'delete_product':
                $authController->authorize('products.delete');
                $productController->handleDelete((int) ($_POST['product_id'] ?? 0));
                setFlash('success', 'Product deleted successfully.');
                redirectTo(basePath('index.php?page=products'));
                break;

            case 'archive_product':
                $authController->authorize('products.archive');
                $productController->handleArchive((int) ($_POST['product_id'] ?? 0));
                setFlash('success', 'Product archived.');
                redirectTo(basePath('index.php?page=products'));
                break;

            case 'adjust_stock':
                $movementType = $_POST['movement_type'] ?? '';
                if ($movementType === 'in') {
                    $authController->authorize('stock.in');
                }
                if ($movementType === 'out') {
                    $authController->authorize('stock.out');
                }
                $result = $stockController->handleAdjustment($_POST, (int) currentUser()['id']);
                if ($result['success']) {
                    setFlash('success', $result['message']);
                    redirectTo(basePath('index.php?page=stock'));
                }
                setFlash('error', implode(' ', $result['errors']));
                redirectTo(basePath('index.php?page=stock'));
                break;

            case 'create_purchase_order':
                $authController->authorize('po.create');
                $validated = $poController->validateCreate($_POST);
                if ($validated['errors']) {
                    setFlash('error', implode(' ', $validated['errors']));
                } else {
                    $poModel->create(
                        $validated['data']['supplier_id'],
                        $validated['data']['line_items'],
                        (int) currentUser()['id'],
                        $validated['data']['expected_date']
                    );
                    setFlash('success', 'Purchase order created.');
                }
                redirectTo(basePath('index.php?page=purchase-orders'));
                break;

            case 'update_po_tracking':
                $authController->authorize('po.tracking');
                $poId = (int) ($_POST['po_id'] ?? 0);
                $validated = $poController->validateTracking($_POST);
                if ($validated['errors']) {
                    setFlash('error', implode(' ', $validated['errors']));
                } else {
                    $poModel->updateTracking($poId, $validated['data']);
                    setFlash('success', 'Shipment tracking updated.');
                }
                redirectTo(basePath('index.php?page=purchase-orders'));
                break;

            case 'receive_po':
                $authController->authorize('po.receive');
                $poId = (int) ($_POST['po_id'] ?? 0);
                $lineIds = $_POST['line_id'] ?? [];
                $lineReceived = $_POST['line_received'] ?? [];
                $receivedMap = [];
                foreach ($lineIds as $index => $lineId) {
                    $receivedMap[(int) $lineId] = (int) ($lineReceived[$index] ?? 0);
                }
                $poModel->receive($poId, $receivedMap, (int) currentUser()['id']);
                setFlash('success', 'Purchase order marked as received.');
                redirectTo(basePath('index.php?page=purchase-orders'));
                break;

            case 'record_sale':
                $authController->authorize('sales.record');
                $validated = $reportController->validateSale($_POST);
                if ($validated['errors']) {
                    setFlash('error', implode(' ', $validated['errors']));
                } else {
                    $reportModel->createSale($validated['data'], (int) currentUser()['id']);
                    setFlash('success', 'Sales transaction recorded.');
                }
                redirectTo(basePath('index.php?page=reports'));
                break;

            case 'import_sales':
                $authController->authorize('reports.import');
                $result = $reportController->importSales($_FILES['sales_import'] ?? [], (int) currentUser()['id']);
                if ($result['success']) {
                    setFlash('success', sprintf('Import complete. %d rows imported, %d skipped.', $result['imported'], $result['skipped']));
                } else {
                    setFlash('error', implode(' ', $result['errors']));
                }
                redirectTo(basePath('index.php?page=reports'));
                break;

            case 'logout':
                $authController->logout();
                redirectTo(basePath('index.php'));
                break;

            case 'request_access':
                $validation = $accessRequestController->validateCreate($_POST);
                if ($validation['errors']) {
                    $errors = $validation['errors'];
                    $page = 'request-access';
                } else {
                    $accessRequestModel->create($validation['data']);
                    setFlash('success', 'Access request submitted. A manager will review your request.');
                    redirectTo(basePath('index.php'));
                }
                break;

            case 'set_password_first_login':
                $result = $authController->completeFirstLoginPasswordSetup(
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['password_confirm'] ?? '')
                );
                if ($result['success']) {
                    setFlash('success', 'Password updated successfully. Welcome to Inventra.');
                    redirectTo(basePath('index.php?page=' . $result['landing_page']));
                }
                $errors = $result['errors'];
                $page = 'set-password';
                break;
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirectTo(basePath('index.php?page=' . $page));
    }
}

if (!isLoggedIn()) {
    $requestedMode = $_GET['mode'] ?? '';
    $authMode = 'login';
    if (($requestedMode === 'set-password' || $page === 'set-password') && $authController->hasPendingPasswordSetup()) {
        $authMode = 'set-password';
    } elseif ($requestedMode === 'request-access' || $page === 'request-access') {
        $authMode = 'request-access';
    }
    $passwordSetupUser = $authMode === 'set-password' ? $authController->getPendingPasswordSetupUser() : null;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Inventra Access</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(basePath('css/style.css')); ?>">
    </head>
    <body class="login-body" data-theme-enabled="true">
    <div class="auth-shell">
        <section class="auth-left">
            <div class="auth-left-content">
                <img class="auth-logo" src="<?= e(appRootPath('logo/inventra%20with%20logo.png')); ?>" alt="Inventra logo">
                <h1>Manage your inventory easily</h1>
                <ul class="auth-feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span><div><strong>Real-time Tracking</strong><small>Monitor stock levels across multiple warehouses instantly.</small></div></li>
                    <li><span class="material-symbols-outlined">check_circle</span><div><strong>Automated Restocking</strong><small>Set intelligent thresholds for zero-stock prevention.</small></div></li>
                    <li><span class="material-symbols-outlined">check_circle</span><div><strong>Detailed Analytics</strong><small>Predictive insights into turnover and demand trends.</small></div></li>
                </ul>
            </div>
        </section>
        <section class="auth-right">
            <div class="auth-card">
                <h2>
                    <?= $authMode === 'request-access'
                        ? 'Request Access'
                        : ($authMode === 'set-password' ? 'Set New Password' : 'Sign In'); ?>
                </h2>
                <p>
                    <?= $authMode === 'request-access'
                        ? 'Submit your details to request access approval from a manager.'
                        : ($authMode === 'set-password'
                            ? 'Set your permanent password to activate your approved account.'
                            : 'Enter your credentials to access your ledger.'); ?>
                </p>
                <?php foreach ($errors as $error): ?>
                    <p class="error-line"><?= e($error); ?></p>
                <?php endforeach; ?>

                <?php if ($authMode === 'request-access'): ?>
                    <form class="login-form" method="post" action="<?= e(basePath('index.php?mode=request-access')); ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                        <input type="hidden" name="action" value="request_access">
                        <label><span>Full Name</span><input type="text" name="full_name" required></label>
                        <label><span>Email</span><input type="email" name="email" required></label>
                        <label>
                            <span>Desired Role</span>
                            <select name="desired_role" required>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Salesman">Salesman</option>
                                <option value="Logistic Handler">Logistic Handler</option>
                            </select>
                        </label>
                        <label><span>Message (Optional)</span><textarea name="message" rows="3" placeholder="Tell us your warehouse/team context"></textarea></label>
                        <button class="button primary wide" type="submit">Submit Request</button>
                    </form>
                    <div class="auth-footer-note">Already have access? <a href="<?= e(basePath('index.php')); ?>">Sign In</a></div>
                <?php elseif ($authMode === 'set-password' && $passwordSetupUser): ?>
                    <form class="login-form" method="post" action="<?= e(basePath('index.php?mode=set-password')); ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                        <input type="hidden" name="action" value="set_password_first_login">
                        <label><span>Account</span><input type="text" value="<?= e((string) $passwordSetupUser['email']); ?>" readonly></label>
                        <label><span>New Password</span><input type="password" name="password" required></label>
                        <label><span>Confirm Password</span><input type="password" name="password_confirm" required></label>
                        <button class="button primary wide" type="submit">Save Password</button>
                    </form>
                    <div class="auth-footer-note"><a href="<?= e(basePath('index.php')); ?>">Back to Sign In</a></div>
                <?php else: ?>
                    <form class="login-form" method="post" action="<?= e(basePath('index.php')); ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                        <input type="hidden" name="action" value="login">
                        <label><span>Email</span><input type="text" name="identifier" placeholder="name@company.com" required></label>
                        <label><span>Password</span><input type="password" name="password" required></label>
                        <div class="auth-aux-row">
                            <label class="auth-checkbox"><input type="checkbox"> Keep me signed in</label>
                            <a href="<?= e(basePath('index.php?mode=request-access')); ?>">Request Access</a>
                        </div>
                        <button class="button primary wide" type="submit">Sign In <span class="material-symbols-outlined">arrow_forward</span></button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <script src="<?= e(basePath('js/app.js')); ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

$authController->requireAuthentication();

$pagination = parsePagination($_GET);
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

switch ($page) {
    case 'users':
        $authController->authorize('users.view');
        $users = $userModel->getAll($pagination['limit'], $pagination['offset']);
        $pendingAccessRequests = $accessRequestModel->getAllPending();
        $title = 'Inventra | Users';
        $currentPage = 'users';
        require __DIR__ . '/../views/users/index.php';
        break;

    case 'categories':
        $authController->authorize('categories.view');
        $categories = $categoryModel->getAll();
        $title = 'Inventra | Categories';
        $currentPage = 'categories';
        require __DIR__ . '/../views/categories/index.php';
        break;

    case 'suppliers':
        $authController->authorize('suppliers.view');
        $suppliers = $supplierModel->getAll();
        $title = 'Inventra | Suppliers';
        $currentPage = 'suppliers';
        require __DIR__ . '/../views/suppliers/index.php';
        break;

    case 'products':
        $authController->authorize('products.view');
        $filters = [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'category' => trim($_GET['category'] ?? ''),
            'stock_level' => trim($_GET['stock_level'] ?? ''),
            'archived' => trim($_GET['archived'] ?? ''),
        ];
        $products = $productModel->getAll($filters, $pagination['limit'], $pagination['offset']);
        $categories = $productModel->getCategories();
        $title = 'Inventra | Inventory';
        $currentPage = 'products';
        require __DIR__ . '/../views/products/index.php';
        break;

    case 'new-entry':
        if (isset($_GET['id'])) {
            $authController->authorize('products.edit');
            $editingProduct = $productModel->findById((int) $_GET['id']);
            if (!$editingProduct) {
                setFlash('error', 'Requested product was not found.');
                redirectTo(basePath('index.php?page=products'));
            }
        } else {
            $authController->authorize('products.create');
        }
        $categories = $productModel->getCategories();
        $suppliers = $productModel->getSuppliers();
        $title = 'Inventra | New Entry';
        $currentPage = 'new-entry';
        require __DIR__ . '/../views/products/form.php';
        break;

    case 'stock':
        $authController->authorize('stock.view');
        $products = $productModel->getAll(['archived' => '0'], 200, 0);
        $history = $stockModel->getRecentHistory(20);
        $title = 'Inventra | Stock';
        $currentPage = 'stock';
        require __DIR__ . '/../views/stock/index.php';
        break;

    case 'purchase-orders':
        $authController->authorize('po.view');
        $purchaseOrders = $poModel->getAll(trim($_GET['status'] ?? '') ?: null);
        $suppliers = $supplierModel->getAll(true);
        $products = $productModel->getAll(['archived' => '0'], 200, 0);
        $selectedPo = isset($_GET['id']) ? $poModel->findById((int) $_GET['id']) : null;
        $title = 'Inventra | Purchase Orders';
        $currentPage = 'purchase-orders';
        require __DIR__ . '/../views/purchase-orders/index.php';
        break;

    case 'delivery-log':
        $authController->authorize('logistics.delivery_log');
        $deliveryLogs = $poModel->getDeliveryLog($fromDate !== '' ? $fromDate : null, $toDate !== '' ? $toDate : null);
        $title = 'Inventra | Delivery Log';
        $currentPage = 'delivery-log';
        require __DIR__ . '/../views/logistics/delivery-log.php';
        break;

    case 'reorder':
        $authController->authorize('logistics.reorder');
        $lowStockProducts = $productModel->getLowStockProducts();
        $title = 'Inventra | Reorder Suggestions';
        $currentPage = 'reorder';
        require __DIR__ . '/../views/logistics/reorder.php';
        break;

    case 'reports':
        $canViewMonthly = $authController->can('reports.sales.monthly');
        $canViewDaily = $authController->can('reports.sales.daily');
        $canViewInventory = $authController->can('reports.inventory');
        $canViewLow = $authController->can('reports.low_stock');
        $canViewMovement = $authController->can('reports.stock_movement');
        $authController->authorize($canViewDaily ? 'reports.sales.daily' : 'reports.inventory');

        $inventoryReport = $canViewInventory ? $reportModel->getInventoryReport($fromDate ?: null, $toDate ?: null) : [];
        $monthlySales = $canViewMonthly ? $reportModel->getMonthlySales($fromDate ?: null, $toDate ?: null) : [];
        $dailySales = $canViewDaily ? $reportModel->getDailySales($fromDate ?: null, $toDate ?: null) : [];
        $lowStockReport = $canViewLow ? $reportModel->getLowStockReport() : [];
        $movementSummary = $canViewMovement ? $reportModel->getStockMovementSummary($fromDate ?: null, $toDate ?: null) : [];
        $importBatches = $authController->can('reports.import') ? $reportModel->getImportBatches(12) : [];
        $products = $productModel->getAll(['archived' => '0'], 200, 0);

        $title = 'Inventra | Reports';
        $currentPage = 'reports';
        require __DIR__ . '/../views/reports/index.php';
        break;

    case 'logout':
        $authController->logout();
        redirectTo(basePath('index.php'));
        break;

    case 'dashboard':
    default:
        $authController->authorize('dashboard');
        $stats = $productModel->getDashboardStats();
        $featuredProducts = $productModel->getFeaturedProducts();
        $alertGraph = $productModel->getAlertGraphData();
        $recentActivity = $stockModel->getRecentHistory(8);
        $title = 'Inventra | Dashboard';
        $currentPage = 'dashboard';
        require __DIR__ . '/../views/dashboard/index.php';
        break;
}
