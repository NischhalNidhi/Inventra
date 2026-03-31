<?php require __DIR__ . '/../../includes/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">ANALYTICS / REPORTING</p>
        <h1>Reports Center</h1>
        <p class="lead">Sales, inventory, low-stock, and stock movement reporting with manual import support.</p>
    </div>
</header>

<section class="panel">
    <div class="panel-header">
        <h2>Date Filters</h2>
        <form method="get" action="<?= e(basePath('index.php')); ?>" class="form-grid">
            <input type="hidden" name="page" value="reports">
            <label><span>From</span><input type="date" name="from_date" value="<?= e($_GET['from_date'] ?? ''); ?>"></label>
            <label><span>To</span><input type="date" name="to_date" value="<?= e($_GET['to_date'] ?? ''); ?>"></label>
            <button class="button ghost" type="submit">Apply</button>
        </form>
    </div>
</section>

<?php if ($authController->can('sales.record')): ?>
<section class="panel">
    <div class="panel-header"><h2>Record Sale</h2></div>
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
        <label><span>Region</span><input type="text" name="region"></label>
        <button class="button primary" type="submit">Record Sale</button>
    </form>
</section>
<?php endif; ?>

<?php if ($authController->can('reports.import')): ?>
<section class="panel">
    <div class="panel-header"><h2>Manual Report Upload (CSV/XLSX)</h2></div>
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

<?php if ($inventoryReport): ?>
<section class="panel">
    <div class="panel-header"><h2>Inventory Report</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>SKU</th><th>Category</th><th>Qty</th><th>Min</th><th>Updated</th></tr></thead>
            <tbody>
            <?php foreach ($inventoryReport as $row): ?>
                <tr>
                    <td><?= e($row['name']); ?></td>
                    <td><?= e($row['sku']); ?></td>
                    <td><?= e((string) $row['category_name']); ?></td>
                    <td><?= e((string) $row['stock_quantity']); ?></td>
                    <td><?= e((string) $row['min_threshold']); ?></td>
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
    <div class="panel-header"><h2>Daily Sales</h2></div>
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
<section class="panel">
    <div class="panel-header"><h2>Low Stock Report</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>SKU</th><th>Current</th><th>Min</th></tr></thead>
            <tbody>
            <?php foreach ($lowStockReport as $row): ?>
                <tr><td><?= e($row['name']); ?></td><td><?= e($row['sku']); ?></td><td><?= e((string) $row['stock_quantity']); ?></td><td><?= e((string) $row['min_threshold']); ?></td></tr>
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
