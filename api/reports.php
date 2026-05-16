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

    // Generate low stock alerts instantly after a sale drops inventory
    $recipients = $notificationModel->getAlertRecipients();
    $notificationModel->generateLowStockAlerts($recipients, $mailer, appUrl('index.php?page=dashboard'));

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
