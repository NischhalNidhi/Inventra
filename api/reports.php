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
$categoryId = trim($_GET['category_id'] ?? '');
$lowCategoryId = $categoryId !== '' ? (int) $categoryId : null;

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

        $chartType = trim($_GET['chart_type'] ?? 'generic');
        $charts = $reportModel->getChartDatasets($fromDate, $toDate, $lowFromDate, $lowToDate, $lowCategoryId);
        $chartData = $charts[$chartType] ?? [];
        
        $insight = $aiSalesInsightService->generateVisualInsight($chartType, $chartData);
        jsonResponse($insight);
    }

    if ($type === 'chart-data') {
        $authController->authorize('reports.inventory');
        jsonResponse([
            'charts' => $reportModel->getChartDatasets($fromDate, $toDate, $lowFromDate, $lowToDate, $lowCategoryId),
        ]);
    }

    if ($type === 'inventory') {
        $authController->authorize('reports.inventory');
        jsonResponse(['rows' => $reportModel->getInventoryReport($fromDate, $toDate)]);
    }
    if ($type === 'sales-monthly') {
        $authController->authorize('reports.sales.monthly');
        assertValidDateRange($fromDate, $toDate);
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
        jsonResponse(['rows' => $reportModel->getLowStockReport($lowFromDate, $lowToDate, $lowCategoryId)]);
    }
    if ($type === 'stock-movement') {
        $authController->authorize('reports.stock_movement');
        jsonResponse([
            'summary' => $reportModel->getStockMovementSummary($fromDate, $toDate),
            'log' => $reportModel->getStockMovementLog($fromDate, $toDate)
        ]);
    }
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
        $csvRows = array_map(static function (array $row): array {
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

function assertValidDateRange(?string $fromDate, ?string $toDate): void
{
    if ($fromDate && $toDate && strtotime($fromDate) > strtotime($toDate)) {
        jsonResponse(['error' => 'End date must be after start date.', 'code' => 'INVALID_DATE_RANGE'], 400);
    }
}

function buildReportExportHtml(array $dashboard, array $analysis): string
{
    $inventory = $dashboard['inventory_summary'];
    $sales = $dashboard['sales_summary'];
    $period = $dashboard['period_label'];
    $lowStockRows = array_slice($dashboard['low_stock_report'], 0, 8);
    $transactions = array_slice($dashboard['sales_transactions'], 0, 12);
    $topProducts = array_slice($dashboard['charts']['top_products']['labels'] ?? [], 0, 6);
    $topValues = array_slice($dashboard['charts']['top_products']['values'] ?? [], 0, 6);
    $dailyLabels = $dashboard['charts']['daily_sales']['labels'] ?? [];
    $dailyValues = $dashboard['charts']['daily_sales']['values'] ?? [];

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventra Report Export</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f7fb;color:#172033;margin:0;padding:32px}
.sheet{max-width:1100px;margin:0 auto;background:#fff;border-radius:20px;padding:32px;box-shadow:0 12px 40px rgba(15,23,42,.08)}
.hero{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;margin-bottom:24px}
.hero h1{margin:0;font-size:32px}
.hero p{margin:6px 0 0;color:#52607a}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:22px 0}
.card{background:#f8fbff;border:1px solid #dbe5f1;border-radius:16px;padding:16px}
.card strong{display:block;font-size:22px;margin-top:8px}
.section{margin-top:28px}
.section h2{margin:0 0 12px;font-size:20px}
.insight{background:#172033;color:#f8fafc;border-radius:18px;padding:20px}
.twocol{display:grid;grid-template-columns:1.2fr .8fr;gap:18px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 12px;border-bottom:1px solid #e5ebf3;text-align:left;font-size:14px}
.bar-list{display:grid;gap:10px}
.bar-row{display:grid;grid-template-columns:160px 1fr 90px;align-items:center;gap:12px}
.bar{height:12px;background:#e6eef8;border-radius:999px;overflow:hidden}
.bar span{display:block;height:100%;background:linear-gradient(90deg,#1d4ed8,#0f766e)}
.spark{width:100%;height:220px;background:#f8fbff;border:1px solid #dbe5f1;border-radius:16px;padding:12px;box-sizing:border-box}
.meta{font-size:12px;color:#64748b}
ul{margin:10px 0 0;padding-left:18px}
@media print{body{background:#fff;padding:0}.sheet{box-shadow:none;border-radius:0}}
</style>
</head>
<body>
<div class="sheet">
  <div class="hero">
    <div>
      <h1>Inventra Executive Report</h1>
      <p>Period: ' . e($period) . '</p>
    </div>
    <div class="meta">
      Generated ' . e(date('Y-m-d H:i:s')) . '<br>
      Export format: printable HTML
    </div>
  </div>

  <div class="grid">
    <div class="card"><div>Total Revenue</div><strong>' . e(formatCurrencyAmount((float) $sales['revenue'])) . '</strong></div>
    <div class="card"><div>Orders</div><strong>' . e((string) $sales['orders']) . '</strong></div>
    <div class="card"><div>Low Stock Items</div><strong>' . e((string) $inventory['low_stock_count']) . '</strong></div>
    <div class="card"><div>Inventory Value</div><strong>' . e(formatCurrencyAmount((float) $inventory['inventory_value'])) . '</strong></div>
  </div>

  <div class="section insight">
    <h2>AI Executive Insight</h2>
    <p>' . e($analysis['summary'] ?? 'Insight unavailable.') . '</p>
    <p><strong>Recommendation:</strong> ' . e($analysis['recommendation'] ?? 'No recommendation available.') . '</p>
  </div>

  <div class="section twocol">
    <div>
      <h2>Daily Revenue Trend</h2>
      ' . renderSparklineSvg($dailyLabels, $dailyValues) . '
    </div>
    <div>
      <h2>Top Products</h2>
      <div class="bar-list">' . renderBarRows($topProducts, $topValues) . '</div>
    </div>
  </div>

  <div class="section twocol">
    <div>
      <h2>Low Stock Priorities</h2>
      <table>
        <thead><tr><th>Product</th><th>Gap</th><th>Days Below</th></tr></thead>
        <tbody>' . renderLowStockRows($lowStockRows) . '</tbody>
      </table>
    </div>
    <div>
      <h2>Risk / Opportunity Snapshot</h2>
      <strong>Opportunities</strong>
      <ul>' . renderListItems($analysis['opportunities'] ?? []) . '</ul>
      <strong>Risks</strong>
      <ul>' . renderListItems($analysis['risks'] ?? []) . '</ul>
    </div>
  </div>

  <div class="section">
    <h2>Recent Sales Transactions</h2>
    <table>
      <thead><tr><th>Date</th><th>Product</th><th>Category</th><th>Qty</th><th>Total</th></tr></thead>
      <tbody>' . renderTransactionRows($transactions) . '</tbody>
    </table>
  </div>
</div>
</body>
</html>';
}

function renderSparklineSvg(array $labels, array $values): string
{
    if ($labels === [] || $values === []) {
        return '<div class="spark">No sales data available for this period.</div>';
    }

    $width = 520;
    $height = 180;
    $padding = 18;
    $max = max(array_map('floatval', $values));
    if ($max <= 0) {
        $max = 1.0;
    }

    $points = [];
    $count = count($values);
    foreach ($values as $index => $value) {
        $x = $padding + (($width - ($padding * 2)) * ($count === 1 ? 0 : ($index / ($count - 1))));
        $y = $height - $padding - (((float) $value / $max) * ($height - ($padding * 2)));
        $points[] = round($x, 2) . ',' . round($y, 2);
    }

    $polyline = implode(' ', $points);

    return '<svg class="spark" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg">
        <rect x="0" y="0" width="' . $width . '" height="' . $height . '" fill="#f8fbff"/>
        <polyline fill="none" stroke="#2563eb" stroke-width="4" points="' . e($polyline) . '"/>
        <line x1="' . $padding . '" y1="' . ($height - $padding) . '" x2="' . ($width - $padding) . '" y2="' . ($height - $padding) . '" stroke="#cbd5e1" stroke-width="2"/>
    </svg>';
}

function renderBarRows(array $labels, array $values): string
{
    if ($labels === [] || $values === []) {
        return '<div>No top products available.</div>';
    }

    $max = max(array_map('floatval', $values));
    $html = '';
    foreach ($labels as $index => $label) {
        $value = (float) ($values[$index] ?? 0);
        $width = $max > 0 ? ($value / $max) * 100 : 0;
        $html .= '<div class="bar-row"><div>' . e($label) . '</div><div class="bar"><span style="width:' . round($width, 2) . '%"></span></div><div>' . e(formatCurrencyAmount($value)) . '</div></div>';
    }

    return $html;
}

function renderLowStockRows(array $rows): string
{
    if ($rows === []) {
        return '<tr><td colspan="3">No low-stock items for this filter.</td></tr>';
    }

    $html = '';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . e((string) $row['name']) . '</td><td>' . e((string) $row['gap']) . '</td><td>' . e((string) $row['days_below_threshold']) . ' days</td></tr>';
    }

    return $html;
}

function renderTransactionRows(array $rows): string
{
    if ($rows === []) {
        return '<tr><td colspan="5">No transactions available for this period.</td></tr>';
    }

    $html = '';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . e((string) $row['sale_date']) . '</td><td>' . e((string) $row['product_name']) . '</td><td>' . e((string) $row['category_name']) . '</td><td>' . e((string) $row['quantity']) . '</td><td>' . e(formatCurrencyAmount((float) $row['total'])) . '</td></tr>';
    }

    return $html;
}

function renderListItems(array $items): string
{
    if ($items === []) {
        return '<li>None available.</li>';
    }

    $html = '';
    foreach ($items as $item) {
        $html .= '<li>' . e((string) $item) . '</li>';
    }

    return $html;
}
