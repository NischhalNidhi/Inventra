<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">STORE PERFORMANCE / ANALYTICS</p>
        <h1>Business Reports</h1>
        <p class="lead">View detailed inventory summaries, sales trends, and AI-driven insights for store optimization.</p>
    </div>
</header>

<?php if (!empty($inventorySummary)): ?>
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
                <p>Category-by-category view of where the catalog is concentrated and where stock conditions are drifting.
                </p>
            </div>
        </div>
        <div class="distribution-heatmap" data-product-distribution-heatmap
            data-heatmap-rows='<?= e(json_encode($inventoryReport, JSON_HEX_APOS | JSON_HEX_TAG)); ?>'>
            <div class="heatmap-grid" data-heatmap-grid></div>
            <div class="heatmap-legend">
                <span>Lower density</span>
                <div class="heatmap-legend-scale"></div>
                <span>Higher density</span>
            </div>
            <p class="heatmap-empty-state" data-heatmap-empty hidden>No inventory rows are available for the current filter.
            </p>
        </div>
    </section>
<?php endif; ?>

<?php if ($authController->can('sales.record')): ?>
    <section class="panel">
        <div class="panel-header">
            <h2>Record In-Store Sale</h2>
        </div>
        <form method="post" action="<?= e(basePath('index.php?page=reports')); ?>" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="record_sale">
            <label>
                <span>Product</span>
                <select name="product_id" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= e((string) $product['id']); ?>">
                            <?= e($product['name'] . ' (' . $product['sku'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Quantity</span><input type="number" name="quantity" min="1" required></label>
            <label><span>Unit Price</span><input type="number" step="0.01" min="0.01" name="unit_price" required></label>
            <label><span>Sale Date</span><input type="date" name="sale_date" value="<?= e(todayDate()); ?>"
                    required></label>
            <label><span>Counter / Branch</span><input type="text" name="region" placeholder="Optional"></label>
            <button class="button primary" type="submit">Record Sale</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($authController->can('reports.import')): ?>
    <section class="panel">
        <div class="panel-header">
            <h2>Import Sales File (CSV/XLSX)</h2>
        </div>
        <form method="post" action="<?= e(basePath('index.php?page=reports')); ?>" enctype="multipart/form-data"
            class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="import_sales">
            <div class="wide" style="display: flex; align-items: flex-end; gap: 16px;">
                <label style="flex: 1; margin: 0;">
                    <span>Sales Import File</span>
                    <input type="file" name="sales_import" accept=".csv,.xlsx" required>
                </label>
                <button class="button primary" type="submit">Upload and Import</button>
            </div>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Imported</th>
                        <th>Skipped</th>
                        <th>By</th>
                        <th>Created</th>
                    </tr>
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

<?php if ($canViewInventory): ?>
    <section class="panel">
        <div class="panel-header">
            <h2>Inventory Report</h2>
            <?php if ($authController->can('reports.export')): ?>
                <a href="<?= e(appRootPath('api/reports.php?type=export-inventory-csv' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>"
                    class="button ghost small">Export to CSV</a>
            <?php endif; ?>
        </div>
        <div class="panel-header">
            <h3>Filter by Date Range</h3>
            <form method="get" action="<?= e(basePath('index.php')); ?>" class="form-grid">
                <input type="hidden" name="page" value="reports">
                <label><span>Start Date</span><input type="date" name="from_date"
                        value="<?= e($_GET['from_date'] ?? ''); ?>"></label>
                <label><span>End Date</span><input type="date" name="to_date" value="<?= e($_GET['to_date'] ?? ''); ?>"></label>
                <button class="button ghost" type="submit">Apply Filter</button>
            </form>
        </div>

        <?php if ($inventoryReport): ?>
        <h3>Inventory Details</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Department</th>
                        <th>Price (NPR)</th>
                        <th>Stock</th>
                        <th>Min</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
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
        <?php else: ?>
            <p class="lead" style="padding: 20px 0;">No products match your current inventory filters.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($monthlySales): ?>
    <section class="panel">
        <div class="panel-header">
            <h2>Monthly Sales</h2>
            <?php if ($authController->can('reports.export')): ?>
                <a href="<?= e(appRootPath('api/reports.php?type=export-monthly-csv' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>"
                    class="button ghost small">Export to CSV</a>
            <?php endif; ?>
        </div>
        <div class="panel-header">
            <h3>Filter by Date Range</h3>
            <form class="form-grid sales-filter-form" data-sales-filter="monthly">
                <label><span>Start Date</span><input type="date" name="from_date"
                        value="<?= e($_GET['from_date'] ?? ''); ?>"></label>
                <label><span>End Date</span><input type="date" name="to_date"
                        value="<?= e($_GET['to_date'] ?? ''); ?>"></label>
                <button class="button ghost" type="submit">Apply Filter</button>
            </form>
        </div>
        <?php if (!empty($canViewSalesInsight)): ?>
            <article class="insight-card sales-ai-insight" data-sales-insight-card
                data-endpoint="<?= e(appRootPath('api/reports.php?type=sales-insight')); ?>">
                <div class="panel-header">
                    <h3>AI Sales Insight</h3>
                    <span class="insight-pill">Manager Only</span>
                </div>
                <p class="insight-kicker">Current month summary</p>
                <div class="sales-insight-state is-loading" data-sales-insight-status aria-live="polite">Generating insight...
                </div>
                <p class="sales-insight-copy" data-sales-insight-copy hidden></p>
            </article>
        <?php endif; ?>
        <h3>Monthly Summary</h3>
        <div class="table-wrap">
            <table data-sales-table="monthly">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Transactions</th>
                        <th>Units Sold</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlySales as $row): ?>
                        <tr>
                            <td><?= e($row['month']); ?></td>
                            <td><strong><?= e((string) $row['transactions']); ?></strong></td>
                            <td><?= e((string) $row['units_sold']); ?></td>
                            <td><?= e(number_format((float) $row['total'], 2)); ?></td>
                        </tr>
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
                <a href="<?= e(appRootPath('api/reports.php?type=export-daily-csv' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>"
                    class="button ghost small">Export to CSV</a>
            <?php endif; ?>
        </div>
        <div class="panel-header">
            <h3>Filter by Date Range</h3>
            <form class="form-grid sales-filter-form" data-sales-filter="daily">
                <label><span>Start Date</span><input type="date" name="from_date"
                        value="<?= e($_GET['from_date'] ?? ''); ?>"></label>
                <label><span>End Date</span><input type="date" name="to_date"
                        value="<?= e($_GET['to_date'] ?? ''); ?>"></label>
                <button class="button ghost" type="submit">Apply Filter</button>
            </form>
        </div>
        <h3>Daily Summary</h3>
        <div class="table-wrap">
            <table data-sales-table="daily">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transactions</th>
                        <th>Units Sold</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailySales as $row): ?>
                        <tr>
                            <td><?= e($row['sale_date']); ?></td>
                            <td><strong><?= e((string) $row['transactions']); ?></strong></td>
                            <td><?= e((string) $row['units_sold']); ?></td>
                            <td><?= e(number_format((float) $row['total'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 style="margin-top: 2rem;">Detailed Sales Log</h3>
        <div class="table-wrap">
            <table data-sales-table="daily-detailed">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Region</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($detailedSales): ?>
                        <?php foreach ($detailedSales as $row): ?>
                            <tr>
                                <td><small><?= e($row['sale_date']); ?></small></td>
                                <td><code><?= e($row['invoice_id'] ?? '-'); ?></code></td>
                                <td><strong><?= e($row['product_name']); ?></strong></td>
                                <td><?= e($row['category_name'] ?? '-'); ?></td>
                                <td><?= e((string) $row['quantity']); ?></td>
                                <td><?= e(number_format((float) $row['unit_price'], 2)); ?></td>
                                <td><strong><?= e(number_format((float) $row['total'], 2)); ?></strong></td>
                                <td><small><?= e($row['payment_method'] ?? '-'); ?></small></td>
                                <td><small><?= e($row['region'] ?? '-'); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9">No sales recorded for the selected period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($authController->can('reports.low_stock')): ?>
    <section class="panel">
        <div class="panel-header">
            <h2>Low Stock Alert Report</h2>
            <?php if ($authController->can('reports.export')): ?>
                <a href="<?= e(appRootPath('api/reports.php?type=export-low-stock-csv' . 
                    (!empty($_GET['low_from_date']) ? '&low_from_date=' . urlencode($_GET['low_from_date']) : '') . 
                    (!empty($_GET['low_to_date']) ? '&low_to_date=' . urlencode($_GET['low_to_date']) : '') . 
                    (!empty($_GET['category_id']) ? '&category_id=' . urlencode($_GET['category_id']) : ''))); ?>" 
                    class="button ghost small">Export to CSV</a>
            <?php endif; ?>
        </div>
        <div class="panel-header">
            <h3>Filter Results</h3>
            <form method="get" action="<?= e(basePath('index.php')); ?>" class="form-grid">
                <input type="hidden" name="page" value="reports">
                <label><span>Start Date</span><input type="date" name="low_from_date"
                        value="<?= e($_GET['low_from_date'] ?? ''); ?>"></label>
                <label><span>End Date</span><input type="date" name="low_to_date"
                        value="<?= e($_GET['low_to_date'] ?? ''); ?>"></label>
                <label>
                    <span>Category</span>
                    <select name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']); ?>" <?= selectedIf($_GET['category_id'] ?? '', (string) $category['id']); ?>><?= e($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button ghost" type="submit" style="align-self: end;">Apply Filter</button>
            </form>
        </div>
        <?php if ($lowStockReport): ?>
        <h3>Urgency Log</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Gap</th>
                        <th>Days Below</th>
                    </tr>
                </thead>
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
        <?php else: ?>
            <p class="lead" style="padding: 20px 0;">No products match your current low-stock filters.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($movementSummary): ?>
    <section class="panel">
        <div class="panel-header">
            <h2>Stock Movement Report</h2>
            <?php if ($authController->can('reports.export')): ?>
                <a href="<?= e(appRootPath('api/reports.php?type=export-stock-movement-csv' . ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''))); ?>" 
                    class="button ghost small">Export to CSV</a>
            <?php endif; ?>
        </div>
        <div class="panel-header">
            <h3>Filter by Date Range</h3>
            <form method="get" action="<?= e(basePath('index.php')); ?>" class="form-grid">
                <input type="hidden" name="page" value="reports">
                <label><span>Start Date</span><input type="date" name="from_date" value="<?= e($_GET['from_date'] ?? ''); ?>"></label>
                <label><span>End Date</span><input type="date" name="to_date" value="<?= e($_GET['to_date'] ?? ''); ?>"></label>
                <button class="button ghost" type="submit">Apply Filter</button>
            </form>
        </div>
        <h3>Summary by Type</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Total Qty</th>
                        <th>Events</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movementSummary as $row): ?>
                        <tr>
                            <td><?= e(strtoupper($row['movement_type'])); ?></td>
                            <td><?= e((string) $row['total_quantity']); ?></td>
                            <td><?= e((string) $row['total_events']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 style="margin-top: 2rem;">Detailed Movement Log</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Prev</th>
                        <th>New</th>
                        <th>User</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($movementLog)): ?>
                        <?php foreach ($movementLog as $row): ?>
                            <tr>
                                <td><?= e($row['created_at']); ?></td>
                                <td><strong><?= e($row['product_name']); ?></strong></td>
                                <td><small><?= e($row['sku']); ?></small></td>
                                <td><span class="badge <?= $row['movement_type'] === 'in' ? 'healthy' : 'low'; ?>"><?= e(strtoupper($row['movement_type'])); ?></span></td>
                                <td><strong><?= e((string) $row['quantity']); ?></strong></td>
                                <td><?= e((string) $row['previous_quantity']); ?></td>
                                <td><?= e((string) $row['new_quantity']); ?></td>
                                <td><?= e($row['full_name']); ?></td>
                                <td><small><?= e($row['reason'] ?? '-'); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9">No movements found for the selected period.</td></tr>
                    <?php endif; ?>
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