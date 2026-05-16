<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">MANAGER TOOLS / REPORTS</p>
        <h1>Store Reports</h1>
        <p class="lead">Track revenue, stock risk, and inventory flow with clearer analytics, richer exports, and AI-assisted commentary.</p>
    </div>
</header>

<?php if ($canViewInventory || $canViewDaily || $canViewMonthly): ?>
    <section class="stats-grid">
        <?php if ($canViewInventory): ?>
            <article class="stat-card primary">
                <span class="stat-label">Total SKUs</span>
                <strong><?= e((string) $inventorySummary['total_skus']); ?></strong>
                <small>Active products in the catalog</small>
            </article>
            <article class="stat-card danger">
                <span class="stat-label">Low Stock</span>
                <strong><?= e((string) $inventorySummary['low_stock_count']); ?></strong>
                <small>Items at or below threshold</small>
            </article>
            <article class="stat-card muted">
                <span class="stat-label">Inventory Value</span>
                <strong><?= e(formatCurrencyAmount((float) $inventorySummary['inventory_value'])); ?></strong>
                <small>On-hand inventory value</small>
            </article>
        <?php endif; ?>
        <?php if (!empty($salesSummary)): ?>
            <article class="stat-card primary">
                <span class="stat-label">Revenue</span>
                <strong><?= e(formatCurrencyAmount((float) $salesSummary['revenue'])); ?></strong>
                <small><?= e((string) $salesSummary['orders']); ?> orders in selected period</small>
            </article>
            <article class="stat-card muted">
                <span class="stat-label">Avg Order Value</span>
                <strong><?= e(formatCurrencyAmount((float) $salesSummary['average_order_value'])); ?></strong>
                <small><?= e((string) $salesSummary['units']); ?> units sold</small>
            </article>
            <article class="stat-card <?= (float) $salesSummary['growth_percentage'] >= 0 ? 'primary' : 'danger'; ?>">
                <span class="stat-label">Growth Vs Prior Period</span>
                <strong><?= ((float) $salesSummary['growth_percentage'] >= 0 ? '+' : '') . e(number_format((float) $salesSummary['growth_percentage'], 1)); ?>%</strong>
                <small>Comparison based on AI insight period</small>
            </article>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Report Filters</h2>
            <p>Use one date range for revenue views and a separate low-stock filter when needed.</p>
        </div>
        <?php if ($authController->can('reports.export')): ?>
            <div class="report-actions">
                <a class="button ghost small" href="<?= e(appRootPath('api/reports.php?type=export-daily-csv' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>">Export Daily CSV</a>
                <a class="button ghost small" href="<?= e(appRootPath('api/reports.php?type=export-monthly-csv' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>">Export Monthly CSV</a>
                <a class="button primary small" href="<?= e(appRootPath('api/reports.php?type=export-summary-html' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : '') . ($lowFromDate ? '&low_from_date=' . urlencode($lowFromDate) : '') . ($lowToDate ? '&low_to_date=' . urlencode($lowToDate) : '') . ($lowCategoryId !== null ? '&category_id=' . urlencode((string) $lowCategoryId) : ''))); ?>">Export Insight Report</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="report-filter-grid">
        <form method="get" action="<?= e(basePath('index.php')); ?>" class="form-grid">
            <input type="hidden" name="page" value="reports">
            <label><span>From</span><input type="date" name="from_date" value="<?= e($_GET['from_date'] ?? ''); ?>"></label>
            <label><span>To</span><input type="date" name="to_date" value="<?= e($_GET['to_date'] ?? ''); ?>"></label>
            <button class="button ghost" type="submit">Refresh Sales & Inventory</button>
        </form>
        <?php if ($authController->can('reports.low_stock')): ?>
            <form method="get" action="<?= e(basePath('index.php')); ?>" class="form-grid">
                <input type="hidden" name="page" value="reports">
                <label><span>Low Stock From</span><input type="date" name="low_from_date" value="<?= e($_GET['low_from_date'] ?? ''); ?>"></label>
                <label><span>Low Stock To</span><input type="date" name="low_to_date" value="<?= e($_GET['low_to_date'] ?? ''); ?>"></label>
                <label>
                    <span>Category</span>
                    <select name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']); ?>" <?= selectedIf($_GET['category_id'] ?? '', (string) $category['id']); ?>><?= e($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button ghost" type="submit">Refresh Low Stock</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($canViewSalesInsight)): ?>
    <section class="panel sales-insight-panel" data-sales-insight-card data-endpoint="<?= e(appRootPath('api/reports.php?type=sales-insight' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>">
        <div class="panel-header">
            <div>
                <h2>AI Sales Insight</h2>
                <p>Executive narrative generated from the current filtered sales period.</p>
            </div>
            <span class="insight-pill">Groq / <?= e($aiSalesInsightService->getConfiguredModel()); ?></span>
        </div>
        <div class="sales-insight-state is-loading" data-sales-insight-status aria-live="polite">Generating insight...</div>
        <p class="sales-insight-copy" data-sales-insight-copy hidden></p>
        <div class="sales-insight-details" data-sales-insight-details hidden>
            <div>
                <h3>Opportunities</h3>
                <ul data-sales-insight-opportunities></ul>
            </div>
            <div>
                <h3>Risks</h3>
                <ul data-sales-insight-risks></ul>
            </div>
            <div class="sales-insight-recommendation">
                <h3>Recommendation</h3>
                <p data-sales-insight-recommendation></p>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="panel report-chart-panel" data-report-charts='<?= e(json_encode($reportCharts, JSON_HEX_APOS | JSON_HEX_TAG)); ?>'>
    <div class="panel-header">
        <div>
            <h2>Visual Analytics</h2>
            <p>Revenue, category mix, low-stock severity, and stock movement in one view.</p>
        </div>
    </div>
    <div class="report-chart-grid">
        <article class="report-chart-card" data-visual-chart-card="daily_sales">
            <div class="report-chart-head">
                <div>
                    <h3>Daily Revenue</h3>
                    <span>Line trend</span>
                </div>
                <button type="button" class="button ai-spark-btn" data-ai-visual-trigger="daily_sales" title="Get AI analysis for this trend">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path></svg>
                    AI
                </button>
            </div>
            <div class="ai-visual-insight-box" data-ai-visual-insight hidden></div>
            <canvas data-report-chart="daily_sales" height="220"></canvas>
        </article>
        <article class="report-chart-card" data-visual-chart-card="monthly_sales">
            <div class="report-chart-head">
                <div>
                    <h3>Monthly Revenue</h3>
                    <span>Period totals</span>
                </div>
                <button type="button" class="button ai-spark-btn" data-ai-visual-trigger="monthly_sales" title="Get AI analysis for this period">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path></svg>
                    AI
                </button>
            </div>
            <div class="ai-visual-insight-box" data-ai-visual-insight hidden></div>
            <canvas data-report-chart="monthly_sales" height="220"></canvas>
        </article>
        <article class="report-chart-card" data-visual-chart-card="top_products">
            <div class="report-chart-head">
                <div>
                    <h3>Top Products</h3>
                    <span>Revenue contribution</span>
                </div>
                <button type="button" class="button ai-spark-btn" data-ai-visual-trigger="top_products" title="Get AI analysis for product performance">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path></svg>
                    AI
                </button>
            </div>
            <div class="ai-visual-insight-box" data-ai-visual-insight hidden></div>
            <canvas data-report-chart="top_products" height="260"></canvas>
        </article>
        <article class="report-chart-card" data-visual-chart-card="category_breakdown">
            <div class="report-chart-head">
                <div>
                    <h3>Category Breakdown</h3>
                    <span>Sales mix</span>
                </div>
                <button type="button" class="button ai-spark-btn" data-ai-visual-trigger="category_breakdown" title="Get AI analysis for category trends">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path></svg>
                    AI
                </button>
            </div>
            <div class="ai-visual-insight-box" data-ai-visual-insight hidden></div>
            <canvas data-report-chart="category_breakdown" height="260"></canvas>
        </article>
        <article class="report-chart-card" data-visual-chart-card="low_stock_severity">
            <div class="report-chart-head">
                <div>
                    <h3>Low Stock Severity</h3>
                    <span>Threshold deficit %</span>
                </div>
                <button type="button" class="button ai-spark-btn" data-ai-visual-trigger="low_stock_severity" title="Get AI analysis for stock risk">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path></svg>
                    AI
                </button>
            </div>
            <div class="ai-visual-insight-box" data-ai-visual-insight hidden></div>
            <canvas data-report-chart="low_stock_severity" height="260"></canvas>
        </article>
        <article class="report-chart-card" data-visual-chart-card="stock_movement">
            <div class="report-chart-head">
                <div>
                    <h3>Stock Movement Mix</h3>
                    <span>Volume by movement type</span>
                </div>
                <button type="button" class="button ai-spark-btn" data-ai-visual-trigger="stock_movement" title="Get AI analysis for logistics flow">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path></svg>
                    AI
                </button>
            </div>
            <div class="ai-visual-insight-box" data-ai-visual-insight hidden></div>
            <canvas data-report-chart="stock_movement" height="260"></canvas>
        </article>
    </div>
</section>

<?php if ($canViewInventory): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Inventory Distribution</h2>
                <p>Category and stock status density across the active catalog.</p>
            </div>
        </div>
        <div class="distribution-heatmap" data-product-distribution-heatmap data-heatmap-rows='<?= e(json_encode($inventoryReport, JSON_HEX_APOS | JSON_HEX_TAG)); ?>'>
            <div class="heatmap-grid" data-heatmap-grid></div>
            <div class="heatmap-legend">
                <span>Lower density</span>
                <div class="heatmap-legend-scale"></div>
                <span>Higher density</span>
            </div>
            <p class="heatmap-empty-state" data-heatmap-empty hidden>No inventory rows are available for the current filter.</p>
        </div>
    </section>
<?php endif; ?>

<?php if ($authController->can('sales.record')): ?>
    <section class="panel">
        <div class="panel-header"><h2>Record In-Store Sale</h2></div>
        <form method="post" action="<?= e(basePath('index.php?page=reports')); ?>" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="record_sale">
            <label>
                <span>Product</span>
                <select name="product_id" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= e((string) $product['id']); ?>"><?= e($product['name'] . ' (' . $product['sku'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Quantity</span><input type="number" name="quantity" min="1" required></label>
            <label><span>Unit Price</span><input type="number" step="0.01" min="0.01" name="unit_price" required></label>
            <label><span>Sale Date</span><input type="date" name="sale_date" value="<?= e(todayDate()); ?>" required></label>
            <label><span>Counter / Branch</span><input type="text" name="region" placeholder="Optional"></label>
            <button class="button primary" type="submit">Record Sale</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($authController->can('reports.import')): ?>
    <section class="panel">
        <div class="panel-header"><h2>Import Sales File (CSV/XLSX)</h2></div>
        <form method="post" action="<?= e(basePath('index.php?page=reports')); ?>" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="import_sales">
            <label class="wide"><span>Sales Import File</span><input type="file" name="sales_import" accept=".csv,.xlsx" required></label>
            <button class="button primary" type="submit">Upload and Import</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>File</th><th>Type</th><th>Status</th><th>Imported</th><th>Skipped</th><th>By</th><th>Created</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($importBatches as $batch): ?>
                        <tr>
                            <td><?= e($batch['file_name']); ?></td>
                            <td><?= e(strtoupper($batch['file_type'])); ?></td>
                            <td><?= e(strtoupper($batch['status'])); ?></td>
                            <td><?= e((string) $batch['imported_rows']); ?></td>
                            <td><?= e((string) $batch['skipped_rows']); ?></td>
                            <td><?= e($batch['full_name']); ?></td>
                            <td><?= e($batch['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($inventoryReport): ?>
    <section class="panel">
        <div class="panel-header"><h2>Inventory Report</h2></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Product</th><th>SKU</th><th>Department</th><th>Price</th><th>Stock</th><th>Min</th><th>Status</th><th>Value</th><th>Updated</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($inventoryReport as $row): ?>
                        <tr>
                            <td><?= e($row['name']); ?></td>
                            <td><?= e($row['sku']); ?></td>
                            <td><?= e((string) $row['category_name']); ?></td>
                            <td><?= e(formatCurrencyAmount((float) ($row['unit_price'] ?? 0))); ?></td>
                            <td><?= e((string) $row['stock_quantity']); ?></td>
                            <td><?= e((string) $row['min_threshold']); ?></td>
                            <td><?= e(strtoupper((string) $row['stock_status'])); ?></td>
                            <td><?= e(formatCurrencyAmount((float) ($row['inventory_value'] ?? 0))); ?></td>
                            <td><?= e($row['updated_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($monthlySales): ?>
    <section class="panel">
        <div class="panel-header"><h2>Monthly Sales</h2></div>
        <div class="table-wrap">
            <table data-sales-table="monthly">
                <thead><tr><th>Month</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($monthlySales as $row): ?>
                        <tr><td><?= e($row['month']); ?></td><td><?= e(formatCurrencyAmount((float) $row['total'])); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($dailySales): ?>
    <section class="panel">
        <div class="panel-header"><h2>Daily Sales</h2></div>
        <div class="table-wrap">
            <table data-sales-table="daily">
                <thead><tr><th>Date</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($dailySales as $row): ?>
                        <tr><td><?= e($row['sale_date']); ?></td><td><?= e(formatCurrencyAmount((float) $row['total'])); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($lowStockReport): ?>
    <section class="panel">
        <div class="panel-header"><h2>Low Stock Alert Report</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Current Stock</th><th>Minimum Stock</th><th>Gap</th><th>Severity</th><th>Days Below</th></tr></thead>
                <tbody>
                    <?php foreach ($lowStockReport as $row): ?>
                        <tr>
                            <td><?= e($row['name']); ?></td>
                            <td><?= e($row['sku']); ?></td>
                            <td><?= e((string) $row['category_name']); ?></td>
                            <td><?= e((string) $row['stock_quantity']); ?></td>
                            <td><?= e((string) $row['min_threshold']); ?></td>
                            <td><?= e((string) $row['gap']); ?></td>
                            <td><?= e(number_format((float) $row['severity'], 1)); ?>%</td>
                            <td><?= e((string) $row['days_below_threshold']); ?> days</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($movementSummary): ?>
    <section class="panel">
        <div class="panel-header"><h2>Stock Movement Report</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Type</th><th>Total Qty</th><th>Events</th></tr></thead>
                <tbody>
                    <?php foreach ($movementSummary as $row): ?>
                        <tr><td><?= e(strtoupper($row['movement_type'])); ?></td><td><?= e((string) $row['total_quantity']); ?></td><td><?= e((string) $row['total_events']); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

</div>
</main>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>
