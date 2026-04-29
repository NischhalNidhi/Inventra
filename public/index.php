<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

$cspHeader = ($_ENV['APP_ENV'] ?? '') === 'production' 
    ? 'Content-Security-Policy' 
    : 'Content-Security-Policy-Report-Only';

header($cspHeader . ": default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self';");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

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
            if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
                if (!empty($result['requires_password_setup'])) {
                    jsonResponse(['redirect' => basePath('index.php?mode=set-password')]);
                }
                jsonResponse(['redirect' => basePath('index.php?page=' . $result['landing_page'])]);
            }
            if (!empty($result['requires_password_setup'])) {
                setFlash('success', 'First-time login detected. Set your account password to continue.');
                redirectTo(basePath('index.php?mode=set-password'));
            }
            setFlash('success', 'Welcome back to Inventra.');
            redirectTo(basePath('index.php?page=' . $result['landing_page']));
        }
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            jsonResponse(['error' => implode(' ', $result['errors']), 'code' => 'AUTH_INVALID'], 401);
        }
        $errors = $result['errors'];
    }

    if (!in_array($action, ['login', 'request_password_reset', 'set_password_with_token', 'reset_password_with_token', 'set_password_first_login'], true)) {
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
                    $userModel->createPendingSetup($validated['data']);
                    $createdUser = $userModel->findByIdentifier((string) $validated['data']['email']);
                    $mailResult = $createdUser ? $authController->sendAccountSetupEmail($createdUser) : ['success' => false];
                    if ($mailResult['success']) {
                        setFlash('success', 'User account created. A setup link was sent to the staff member.');
                    } else {
                        setFlash('error', 'User account created, but the setup email could not be sent. Check mail configuration.');
                    }
                }
                redirectTo(basePath('index.php?page=users'));
                break;

            case 'request_password_reset':
                $result = $authController->requestPasswordReset((string) ($_POST['email'] ?? ''));
                if ($result['success']) {
                    setFlash('success', $result['message']);
                    redirectTo(basePath('index.php?mode=forgot-password'));
                }
                $errors = $result['errors'];
                $page = 'forgot-password';
                break;

            case 'set_password_with_token':
                $result = $authController->completePasswordTokenSetup(
                    (string) ($_POST['token'] ?? ''),
                    'account_setup',
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['password_confirm'] ?? '')
                );
                if ($result['success']) {
                    setFlash('success', 'Password set successfully. You can now sign in.');
                    redirectTo(basePath('index.php'));
                }
                $errors = $result['errors'];
                $page = 'set-password';
                break;

            case 'reset_password_with_token':
                $result = $authController->completePasswordTokenSetup(
                    (string) ($_POST['token'] ?? ''),
                    'password_reset',
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['password_confirm'] ?? '')
                );
                if ($result['success']) {
                    setFlash('success', 'Password reset successfully. You can now sign in.');
                    redirectTo(basePath('index.php'));
                }
                $errors = $result['errors'];
                $page = 'reset-password';
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

            case 'update_profile':
                $userId = (int) currentUser()['id'];
                $userRec = $userModel->findById($userId);
                $fullName = trim($_POST['full_name'] ?? '');

                if ($fullName !== '') {
                    $userModel->update($userId, [
                        'full_name' => $fullName,
                        'email' => $userRec['email'],
                        'role' => $userRec['role'],
                    ]);
                    $_SESSION['user']['full_name'] = $fullName;
                }

                if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $newName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], __DIR__ . '/uploads/avatars/' . $newName)) {
                            if (!empty($userRec['profile_image']) && is_file(__DIR__ . '/uploads/avatars/' . $userRec['profile_image'])) {
                                @unlink(__DIR__ . '/uploads/avatars/' . $userRec['profile_image']);
                            }
                            $userModel->updateProfileImage($userId, $newName);
                            $_SESSION['user']['profile_image'] = $newName;
                        }
                    } else {
                        setFlash('error', 'Invalid image format. Supported: JPG, PNG, WEBP.');
                    }
                }
                setFlash('success', 'Profile updated.');
                redirectTo(basePath('index.php?page=profile'));
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
    $authMode = match (true) {
        $requestedMode === 'forgot-password' || $page === 'forgot-password' => 'forgot-password',
        $requestedMode === 'reset-password' || $page === 'reset-password' => 'reset-password',
        $requestedMode === 'set-password' || $page === 'set-password' || $authController->hasPendingPasswordSetup() => 'set-password',
        default => 'login',
    };
    $authToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    $tokenState = ['valid' => false, 'expired' => false, 'user' => null];
    if ($authToken !== '' && $authMode === 'set-password') {
        $tokenState = $authController->getTokenState($authToken, 'account_setup');
    } elseif ($authToken !== '' && $authMode === 'reset-password') {
        $tokenState = $authController->getTokenState($authToken, 'password_reset');
    }
    $passwordSetupUser = $authMode === 'set-password' ? $authController->getPendingPasswordSetupUser() : null;
    require __DIR__ . '/../views/auth/index.php';
    exit;
}

$authController->requireAuthentication();

$pagination = parsePagination($_GET);
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');
// Preserve separate date/category filters for the low stock alert report page.
$lowFromDate = trim($_GET['low_from_date'] ?? '');
$lowToDate = trim($_GET['low_to_date'] ?? '');
$lowCategoryId = trim($_GET['category_id'] ?? '');

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
        $search = trim($_GET['search'] ?? '');
        $categoriesData = $categoryModel->getAll($pagination['page'], $pagination['limit'], $search);
        $categories = $categoriesData['data'];
        $totalItems = $categoriesData['total'];
        $currentPageNum = $pagination['page'];
        $perPage = $pagination['limit'];
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
        $search = trim($_GET['search'] ?? '');
        $productsData = $productModel->getAll($pagination['page'], $pagination['limit'], $search, $filters);
        $products = $productsData['data'];
        $totalItems = $productsData['total'];
        $currentPageNum = $pagination['page'];
        $perPage = $pagination['limit'];
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
        $suppliersData = $supplierModel->getAll(1, 200, '', true);
        $suppliers = $suppliersData['data'];
        $title = 'Inventra | New Entry';
        $currentPage = 'new-entry';
        require __DIR__ . '/../views/products/form.php';
        break;

    case 'purchase-orders':
        $authController->authorize('po.view');
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '') ?: null;
        $poData = $poModel->getAll($pagination['page'], $pagination['limit'], $search, $status);
        $purchaseOrders = $poData['data'];
        $totalItems = $poData['total'];
        $currentPageNum = $pagination['page'];
        $perPage = $pagination['limit'];
        $productsData = $productModel->getAll(1, 200, '', ['archived' => '0']);
        $products = $productsData['data'];
        $suppliersData = $supplierModel->getAll(1, 200, '', true);
        $suppliers = $suppliersData['data'];
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
        $lowStockCategoryId = $lowCategoryId !== '' ? (int) $lowCategoryId : null;
        // Use low stock filters only when the user can access the low stock report.
        $lowStockReport = $canViewLow ? $reportModel->getLowStockReport($lowFromDate ?: null, $lowToDate ?: null, $lowStockCategoryId) : [];
        $movementSummary = $canViewMovement ? $reportModel->getStockMovementSummary($fromDate ?: null, $toDate ?: null) : [];
        $importBatches = $authController->can('reports.import') ? $reportModel->getImportBatches(12) : [];
        $productsData = $productModel->getAll(1, 200, '', ['archived' => '0']);
        $products = $productsData['data'];
        $categories = $productModel->getCategories();

        $title = 'Inventra | Reports';
        $currentPage = 'reports';
        require __DIR__ . '/../views/reports/index.php';
        break;

    case 'suppliers':
        $authController->authorize('suppliers.view');
        $search = trim($_GET['search'] ?? '');
        $suppliersData = $supplierModel->getAll($pagination['page'], $pagination['limit'], $search);
        $suppliers = $suppliersData['data'];
        $totalItems = $suppliersData['total'];
        $currentPageNum = $pagination['page'];
        $perPage = $pagination['limit'];
        $title = 'Inventra | Suppliers';
        $currentPage = 'suppliers';
        require __DIR__ . '/../views/suppliers/index.php';
        break;

    case 'logout':
        $authController->logout();
        redirectTo(basePath('index.php'));
        break;

    case 'profile':
        $title = 'Inventra | My Profile';
        $currentPage = 'profile';
        require __DIR__ . '/../views/profile/index.php';
        break;

    case 'dashboard':
    default:
        $authController->authorize('dashboard');
        $stats = $productModel->getDashboardStats();
        $featuredProducts = $productModel->getFeaturedProducts();
        $alertGraph = $productModel->getAlertGraphData();
        $dashboardAlerts = $productModel->getDashboardAlerts();
        $recentActivity = $authController->can('dashboard.activity') ? $productModel->getRecentActivity() : [];
        $title = 'Inventra | Dashboard';
        $currentPage = 'dashboard';
        require __DIR__ . '/../views/dashboard/index.php';
        break;
}
