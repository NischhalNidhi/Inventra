<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();

$type = trim($_GET['type'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '') ?: null;
$toDate = trim($_GET['to_date'] ?? '') ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($type === 'sales-insight') {
        if (!$authController->can('reports.sales.insight')) {
            jsonResponse(['error' => 'Forbidden', 'code' => 'FORBIDDEN'], 403);
        }

        try {
            $lastRequest = $_SESSION['last_ai_request_at'] ?? 0;
            $secondsRemaining = 180 - (time() - $lastRequest);

            if ($secondsRemaining > 0) {
                jsonResponse(['error' => "Rate limit: Please wait {$secondsRemaining} seconds.", 'code' => 'RATE_LIMIT'], 429);
            }

            // Mark request time before closing session to allow AI call to run in background
            $_SESSION['last_ai_request_at'] = time();

            // Release session lock before making the AI request to prevent 
            // deadlocks on local servers (XAMPP/Windows).
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $salesData = $reportModel->getAdvancedSalesInsightData();
            $summary = $aiSalesInsightService->generateMonthlySalesInsight($salesData);
            jsonResponse([
                'summary' => $summary,
                'period' => $salesData['period'] ?? null,
            ]);
        } catch (Throwable $exception) {
            error_log("[Inventra AI Error] " . $exception->getMessage());
            $message = env('APP_ENV') !== 'production' 
                ? 'AI Error: ' . $exception->getMessage() 
                : 'Insight unavailable';
                
            jsonResponse(['error' => $message, 'code' => 'INSIGHT_UNAVAILABLE'], 502);
        }
    }

    if ($type === 'inventory') {
        $authController->authorize('reports.inventory');
        jsonResponse(['rows' => $reportModel->getInventoryReport($fromDate, $toDate)]);
    }
    if ($type === 'sales-monthly') {
        $authController->authorize('reports.sales.monthly');
        if ($fromDate && $toDate && strtotime($fromDate) > strtotime($toDate)) {
            jsonResponse(['error' => 'End date must be after start date.', 'code' => 'INVALID_DATE_RANGE'], 400);
        }
        jsonResponse(['rows' => $reportModel->getMonthlySales($fromDate, $toDate)]);
    }
    if ($type === 'sales-daily') {
        $authController->authorize('reports.sales.daily');
        if ($fromDate && $toDate && strtotime($fromDate) > strtotime($toDate)) {
            jsonResponse(['error' => 'End date must be after start date.', 'code' => 'INVALID_DATE_RANGE'], 400);
        }
        jsonResponse([
            'summary' => $reportModel->getDailySales($fromDate, $toDate),
            'detailed' => $reportModel->getSalesTransactionsForExport($fromDate, $toDate)
        ]);
    }
    if ($type === 'low-stock') {
        $authController->authorize('reports.low_stock');
        jsonResponse(['rows' => $reportModel->getLowStockReport()]);
    }
    if ($type === 'stock-movement') {
        $authController->authorize('reports.stock_movement');
        jsonResponse([
            'summary' => $reportModel->getStockMovementSummary($fromDate, $toDate),
            'log' => $reportModel->getStockMovementLog($fromDate, $toDate)
        ]);
    }

    // CSV export endpoints for sales reports.
    if ($type === 'export-daily-csv') {
        $authController->authorize('reports.export');
        $transactions = $reportModel->getSalesTransactionsForExport($fromDate, $toDate);
        $csvRows = array_map(fn($r) => [
            'Date' => $r['sale_date'],
            'Invoice' => $r['invoice_id'],
            'Product' => $r['product_name'],
            'Category' => $r['category_name'],
            'Qty' => $r['quantity'],
            'Price' => $r['unit_price'],
            'Total' => $r['total'],
            'Payment' => $r['payment_method'],
            'Region' => $r['region']
        ], $transactions);
        downloadCsv('daily-sales-report', ['Date', 'Invoice', 'Product', 'Category', 'Qty', 'Price', 'Total', 'Payment', 'Region'], $csvRows);
        return;
    }
    if ($type === 'export-inventory-csv') {
        $authController->authorize('reports.export');
        $data = $reportModel->getInventoryReport($fromDate, $toDate);
        $csvRows = array_map(fn($r) => [
            'Product' => $r['name'],
            'SKU' => $r['sku'],
            'Department' => $r['category_name'],
            'Price' => $r['unit_price'],
            'Stock' => $r['stock_quantity'],
            'Min Threshold' => $r['min_threshold'],
            'Last Updated' => $r['updated_at']
        ], $data);
        downloadCsv('inventory-report', ['Product', 'SKU', 'Department', 'Price', 'Stock', 'Min Threshold', 'Last Updated'], $csvRows);
        return;
    }
    if ($type === 'export-low-stock-csv') {
        $authController->authorize('reports.export');
        $lFrom = trim($_GET['low_from_date'] ?? '') ?: null;
        $lTo = trim($_GET['low_to_date'] ?? '') ?: null;
        $catId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
        $data = $reportModel->getLowStockReport($lFrom, $lTo, $catId);
        $csvRows = array_map(fn($r) => [
            'Product' => $r['name'],
            'SKU' => $r['sku'],
            'Category' => $r['category_name'],
            'Current Stock' => $r['stock_quantity'],
            'Min Stock' => $r['min_threshold'],
            'Gap' => (int)$r['min_threshold'] - (int)$r['stock_quantity'],
            'Days Below' => $r['days_below_threshold']
        ], $data);
        downloadCsv('low-stock-report', ['Product', 'SKU', 'Category', 'Current Stock', 'Min Stock', 'Gap', 'Days Below'], $csvRows);
        return;
    }
    if ($type === 'export-stock-movement-csv') {
        $authController->authorize('reports.export');
        $data = $reportModel->getStockMovementLog($fromDate, $toDate);
        $csvRows = array_map(fn($r) => [
            'Date' => $r['created_at'],
            'Product' => $r['product_name'],
            'SKU' => $r['sku'],
            'Type' => strtoupper($r['movement_type']),
            'Quantity' => $r['quantity'],
            'Previous' => $r['previous_quantity'],
            'New' => $r['new_quantity'],
            'User' => $r['full_name'],
            'Reason' => $r['reason']
        ], $data);
        downloadCsv('stock-movement-report', ['Date', 'Product', 'SKU', 'Type', 'Quantity', 'Previous', 'New', 'User', 'Reason'], $csvRows);
        return;
    }
    if ($type === 'export-monthly-csv') {
        $authController->authorize('reports.export');
        $monthlySales = $reportModel->getMonthlySales($fromDate, $toDate);
        // Format monthly data for CSV download.
        $csvRows = array_map(function ($row) {
            return [
                'Month' => $row['month'],
                'Transactions' => $row['transactions'],
                'Units Sold' => $row['units_sold'],
                'Total Revenue' => $row['total'],
            ];
        }, $monthlySales);
        downloadCsv('monthly-sales-report', ['Month', 'Transactions', 'Units Sold', 'Total Revenue'], $csvRows);
        return;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        jsonResponse(['error' => 'Invalid request token.', 'code' => 'INVALID_TOKEN'], 422);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $type === 'sales') {
    $authController->authorize('sales.record');
    $validated = $reportController->validateSale($_POST);
    if ($validated['errors']) {
        jsonResponse(['error' => implode(' ', $validated['errors']), 'code' => 'VALIDATION_ERROR'], 422);
    }
    $id = $reportModel->createSale($validated['data'], (int) currentUser()['id']);
    jsonResponse(['sale_id' => $id], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $type === 'import') {
    $authController->authorize('reports.import');
    $result = $reportController->importSales($_FILES['sales_import'] ?? [], (int) currentUser()['id']);
    if (!$result['success']) {
        jsonResponse(['error' => implode(' ', $result['errors']), 'code' => 'IMPORT_FAILED'], 422);
    }
    jsonResponse(['imported' => $result['imported'], 'skipped' => $result['skipped']], 201);
}

jsonResponse(['error' => 'Invalid report request.', 'code' => 'INVALID_REPORT_REQUEST'], 400);
