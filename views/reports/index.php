<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">MANAGER TOOLS / REPORTS</p>
        <h1>Store Reports</h1>
        <p class="lead">Generate inventory summaries, review low-stock items, and track sales activity with clearer store-ready reporting.</p>
    </div>
    <div class="topbar-actions">
        <button class="button ghost" onclick="window.print()"><span class="material-symbols-outlined">print</span> Print Report</button>
    </div>
</header>

<section class="panel no-print">
    <div class="panel-header">
        <h2>Visual Analytics</h2>
        <p>Graphical representation of store performance and inventory health.</p>
    </div>
    <div class="grid-3">
        <div class="chart-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0;">Sales Trend</h3>
                <button type="button" class="button ghost small" data-export-chart="salesTrendChart" aria-label="Export Sales Trend as Image"><span class="material-symbols-outlined" style="font-size: 16px; margin-right: 4px;">download</span> Export</button>
            </div>
            <canvas id="salesTrendChart" 
                data-labels="<?= e(json_encode(array_column($dailySales ?: [], 'sale_date'))); ?>" 
                data-values="<?= e(json_encode(array_column($dailySales ?: [], 'total'))); ?>">
            </canvas>
        </div>
        <div class="chart-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0;">Stock Status</h3>
                <button type="button" class="button ghost small" data-export-chart="stockStatusChart" aria-label="Export Stock Status as Image"><span class="material-symbols-outlined" style="font-size: 16px; margin-right: 4px;">download</span> Export</button>
            </div>
            <?php
            $outOfStock = (int) ($inventorySummary['out_of_stock_count'] ?? 0);
            $lowStock = (int) ($inventorySummary['low_stock_count'] ?? 0);
            $totalSkus = (int) ($inventorySummary['total_skus'] ?? 0);
            $healthy = max(0, $totalSkus - $lowStock - $outOfStock);
            ?>
            <canvas id="stockStatusChart" 
                data-labels='["Healthy", "Low Stock", "Out of Stock"]' 
                data-values='[<?= $healthy; ?>, <?= $lowStock; ?>, <?= $outOfStock; ?>]'
                data-colors='["#22c55e", "#f59e0b", "#ef4444"]'>
            </canvas>
        </div>
        <div class="chart-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0;">Category Distribution</h3>
                <button type="button" class="button ghost small" data-export-chart="categoryDistChart" aria-label="Export Category Distribution as Image"><span class="material-symbols-outlined" style="font-size: 16px; margin-right: 4px;">download</span> Export</button>
            </div>
            <?php
            $catDist = [];
            foreach ($inventoryReport as $row) {
                $cat = $row['category_name'] ?: 'Uncategorized';
                $catDist[$cat] = ($catDist[$cat] ?? 0) + 1;
            }
            ?>
            <canvas id="categoryDistChart" 
                data-labels="<?= e(json_encode(array_keys($catDist))); ?>" 
                data-values="<?= e(json_encode(array_values($catDist))); ?>">
            </canvas>
        </div>
    </div>
</section>

<?php if ($inventorySummary): ?>
<section class="stats-grid">
    <article class="stat-card primary">
        <span class="stat-label">Total SKUs</span>
        <strong><?= e((string) $inventorySummary['total_skus']); ?></strong>
        <small>Active products in the catalog</small>
    </article>
    <article class="stat-card danger">
        <span class="stat-label">Low Stock</span>
        <strong><?= e((string) $inventorySummary['low_stock_count']); ?></strong>
        <small>Items at or below minimum stock</small>
    </article>
    <article class="stat-card muted">
        <span class="stat-label">Out Of Stock</span>
        <strong><?= e((string) $inventorySummary['out_of_stock_count']); ?></strong>
        <small>Items needing immediate replenishment</small>
    </article>
    <article class="stat-card primary">
        <span class="stat-label">Inventory Value</span>
        <strong>NPR <?= e(number_format((float) $inventorySummary['inventory_value'], 2)); ?></strong>
        <small>Current stock multiplied by selling price</small>
    </article>
</section>
<?php endif; ?>

<?php if ($canViewInventory): ?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>AI Product Distribution Heat Map</h2>
            <p>Category-by-category view of where the catalog is concentrated and where stock conditions are drifting.</p>
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

<section class="panel">
    <div class="panel-header">
        <h2>Inventory Report Filters</h2>
        <form method="get" action="<?= e(basePath('index.php')); ?>" class="form-grid">
            <input type="hidden" name="page" value="reports">
            <label><span>From</span><input type="date" name="from_date" value="<?= e($_GET['from_date'] ?? ''); ?>"></label>
            <label><span>To</span><input type="date" name="to_date" value="<?= e($_GET['to_date'] ?? ''); ?>"></label>
            <button class="button ghost" type="submit">Generate Inventory Report</button>
        </form>
    </div>
</section>

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
            <thead><tr><th>File</th><th>Type</th><th>Status</th><th>Imported</th><th>Skipped</th><th>By</th><th>Created</th></tr></thead>
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

<?php if ($authController->can('reports.low_stock')): ?>
<!-- Low stock report filter panel is only shown to authorized roles -->
<section class="panel">
    <div class="panel-header"><h2>Low Stock Alert Filters</h2></div>
    <form method="get" action="<?= e(basePath('index.php')); ?>" class="form-grid">
        <input type="hidden" name="page" value="reports">
        <label><span>From</span><input type="date" name="low_from_date" value="<?= e($_GET['low_from_date'] ?? ''); ?>"></label>
        <label><span>To</span><input type="date" name="low_to_date" value="<?= e($_GET['low_to_date'] ?? ''); ?>"></label>
        <label>
            <span>Category</span>
            <select name="category_id">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']); ?>" <?= selectedIf($_GET['category_id'] ?? '', (string) $category['id']); ?>><?= e($category['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button ghost" type="submit">Filter Low Stock</button>
    </form>
</section>
<?php endif; ?>

<?php if ($inventoryReport): ?>
<section class="panel">
    <div class="panel-header">
        <h2>Inventory Report</h2>
        <?php if ($authController->can('reports.export')): ?>
            <a href="<?= e(appRootPath('api/reports.php?type=export-inventory-csv' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>" class="button ghost small">Export to CSV</a>
        <?php endif; ?>
    </div>
    <p class="lead">Use this summary for manager review, shift handover, or printing.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Product</th><th>SKU</th><th>Department</th><th>Price (NPR)</th><th>Stock</th><th>Min</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
            <?php foreach ($inventoryReport as $row): ?>
                <?php $isLow = (int) $row['stock_quantity'] <= (int) $row['min_threshold']; ?>
                <tr>
                    <td><?= e($row['name']); ?></td>
                    <td><?= e($row['sku']); ?></td>
                    <td><?= e((string) $row['category_name']); ?></td>
                    <td><?= e(number_format((float) ($row['unit_price'] ?? 0), 2)); ?></td>
                    <td><?= e((string) $row['stock_quantity']); ?></td>
                    <td><?= e((string) $row['min_threshold']); ?></td>
                    <td><?= $isLow ? 'Low Stock' : 'Healthy'; ?></td>
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
    <div class="panel-header">
        <h2>Monthly Sales</h2>
        <?php if ($authController->can('reports.export')): ?>
            <a href="<?= e(appRootPath('api/reports.php?type=export-monthly-csv' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>" class="button ghost small">Export to CSV</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($canViewSalesInsight)): ?>
    <article class="insight-card sales-ai-insight" data-sales-insight-card data-endpoint="<?= e(appRootPath('api/reports.php?type=sales-insight')); ?>">
        <div class="panel-header">
            <h3>AI Sales Insight</h3>
            <span class="insight-pill">Manager Only</span>
        </div>
        <p class="insight-kicker">Current month summary</p>
        <div class="sales-insight-state is-loading" data-sales-insight-status aria-live="polite">Generating insight...</div>
        <p class="sales-insight-copy" data-sales-insight-copy hidden></p>
    </article>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Month</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($monthlySales as $row): ?>
                <tr><td><?= e($row['month']); ?></td><td><?= e(number_format((float) $row['total'], 2)); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($dailySales): ?>
<section class="panel">
    <div class="panel-header">
        <h2>Daily Sales</h2>
        <?php if ($authController->can('reports.export')): ?>
            <a href="<?= e(appRootPath('api/reports.php?type=export-daily-csv' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>" class="button ghost small">Export to CSV</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($dailySales as $row): ?>
                <tr><td><?= e($row['sale_date']); ?></td><td><?= e(number_format((float) $row['total'], 2)); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($lowStockReport): ?>
<!-- Low stock alert report table summarises urgency per product -->
<section class="panel">
    <div class="panel-header"><h2>Low Stock Alert Report</h2></div>
    <p class="lead">Products below threshold are ordered by urgency and include time below threshold.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Current Stock</th><th>Minimum Stock</th><th>Gap</th><th>Days Below</th></tr></thead>
            <tbody>
            <?php foreach ($lowStockReport as $row): ?>
                <?php $gap = (int) $row['min_threshold'] - (int) $row['stock_quantity']; ?>
                <tr>
                    <td><?= e($row['name']); ?></td>
                    <td><?= e($row['sku']); ?></td>
                    <td><?= e((string) $row['category_name']); ?></td>
                    <td><?= e((string) $row['stock_quantity']); ?></td>
                    <td><?= e((string) $row['min_threshold']); ?></td>
                    <td><?= e((string) $gap); ?></td>
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
