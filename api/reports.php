<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();

$type = trim($_GET['type'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '') ?: null;
$toDate = trim($_GET['to_date'] ?? '') ?: null;
$lowFromDate = trim($_GET['low_from_date'] ?? '') ?: null;
$lowToDate = trim($_GET['low_to_date'] ?? '') ?: null;
$lowCategoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($type === 'geographic-data') {
        if (!$authController->can('reports.heatmap')) {
            jsonResponse(['error' => 'Forbidden', 'code' => 'FORBIDDEN'], 403);
        }
        $productId = isset($_GET['product_id']) && $_GET['product_id'] !== '' ? (int)$_GET['product_id'] : null;
        $region = trim($_GET['region'] ?? '') ?: null;
        
        $data = $reportModel->getGeographicSalesData($productId, $fromDate, $toDate, $region);
        jsonResponse(['success' => true, 'data' => $data]);
    }

    if ($type === 'geographic-insight') {
        if (!$authController->can('reports.heatmap')) {
            jsonResponse(['error' => 'Forbidden', 'code' => 'FORBIDDEN'], 403);
        }
        
        try {
            $productId = isset($_GET['product_id']) && $_GET['product_id'] !== '' ? (int)$_GET['product_id'] : null;
            $region = trim($_GET['region'] ?? '') ?: null;
            
            $distributionData = $reportModel->getGeographicSalesData($productId, $fromDate, $toDate, $region);
            
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            
            $insight = $aiSalesInsightService->generateGeographicInsight($distributionData);
            jsonResponse([
                'success' => true,
                'insight' => $insight
            ]);
        } catch (Throwable $exception) {
            error_log("[Inventra Heatmap AI Error] " . $exception->getMessage());
            jsonResponse(['error' => 'Insight unavailable', 'code' => 'INSIGHT_UNAVAILABLE'], 502);
        }
    }

    if ($type === 'sales-insight') {
        if (!$authController->can('reports.sales.insight')) {
            jsonResponse(['error' => 'Forbidden', 'code' => 'FORBIDDEN'], 403);
        }

        try {
            $salesData = $reportModel->getAdvancedSalesInsightData();
            $analysis = $aiSalesInsightService->generateSalesAnalysis($salesData);
            jsonResponse([
                'summary' => $analysis['summary'],
                'analysis' => $analysis,
                'period' => $salesData['period'] ?? null,
            ]);
        } catch (Throwable $exception) {
            jsonResponse(['error' => 'Insight unavailable', 'code' => 'INSIGHT_UNAVAILABLE'], 502);
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
        jsonResponse(['rows' => $reportModel->getDailySales($fromDate, $toDate)]);
    }
    if ($type === 'low-stock') {
        $authController->authorize('reports.low_stock');
        jsonResponse(['rows' => $reportModel->getLowStockReport()]);
    }
    if ($type === 'stock-movement') {
        $authController->authorize('reports.stock_movement');
        jsonResponse(['rows' => $reportModel->getStockMovementSummary($fromDate, $toDate)]);
    }

    // CSV export endpoints for sales reports.
    if ($type === 'export-daily-csv') {
        $authController->authorize('reports.export');
        $transactions = $reportModel->getSalesTransactionsForExport($fromDate, $toDate);
        downloadCsv('daily-sales-report', ['Date', 'Product', 'Quantity', 'Unit Price', 'Total'], $transactions);
        return;
    }
    if ($type === 'export-monthly-csv') {
        $authController->authorize('reports.export');
        $monthlySales = $reportModel->getMonthlySales($fromDate, $toDate);
        // Format monthly data for CSV download.
        $csvRows = array_map(function ($row) {
            return [
                $row['month'],
                '',
                '',
                '',
                $row['total'],
            ];
        }, $monthlySales);
        downloadCsv('monthly-sales-report', ['Month', 'Product', 'Quantity', 'Unit Price', 'Total'], $csvRows);
        return;
    }
    if ($type === 'export-summary-html') {
        $authController->authorize('reports.export');
        $dashboard = $reportModel->getReportDashboard($fromDate, $toDate, $lowFromDate, $lowToDate, $lowCategoryId);
        $salesData = $reportModel->getAdvancedSalesInsightData($fromDate, $toDate);
        $analysis = [];
        try {
            $analysis = $aiSalesInsightService->generateMonthlySalesInsight($salesData);
        } catch (Throwable $e) {
            error_log("Failed to generate AI sales insight for HTML export: " . $e->getMessage());
        }
        $html = buildReportExportHtml($dashboard, $analysis);
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventra-executive-report-' . date('Y-m-d') . '.html"');
        echo $html;
        return;
    }
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse(['error' => 'Invalid request token.', 'code' => 'INVALID_TOKEN'], 422);
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
