<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);

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

    if (!in_array($action, ['login', 'set_password_first_login'], true)) {
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

            case 'deactivate_supplier':
                $authController->authorize('suppliers.manage');
                $supplierModel->deactivate((int) ($_POST['supplier_id'] ?? 0));
                setFlash('success', 'Supplier deactivated.');
                redirectTo(basePath('index.php?page=suppliers'));
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
    $authMode = (($requestedMode === 'set-password' || $page === 'set-password') && $authController->hasPendingPasswordSetup())
        ? 'set-password'
        : 'login';
    $passwordSetupUser = $authMode === 'set-password' ? $authController->getPendingPasswordSetupUser() : null;
    require __DIR__ . '/../views/auth/index.php';
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
        $suppliers = $supplierModel->getAll(true);
        $title = 'Inventra | New Entry';
        $currentPage = 'new-entry';
        require __DIR__ . '/../views/products/form.php';
        break;

    case 'purchase-orders':
        $authController->authorize('po.view');
        $purchaseOrders = $poModel->getAll(trim($_GET['status'] ?? '') ?: null);
        $products = $productModel->getAll(['archived' => '0'], 200, 0);
        $suppliers = $supplierModel->getAll(true);
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

    case 'suppliers':
        $authController->authorize('suppliers.view');
        $suppliers = $supplierModel->getAll();
        $title = 'Inventra | Suppliers';
        $currentPage = 'suppliers';
        require __DIR__ . '/../views/suppliers/index.php';
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
        $recentActivity = [];
        $title = 'Inventra | Dashboard';
        $currentPage = 'dashboard';
        require __DIR__ . '/../views/dashboard/index.php';
        break;
}
