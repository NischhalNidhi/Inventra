<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">SYSTEM CORE / INVENTORY</p>
        <h1>Inventory Ledger</h1>
        <p class="lead">Search, monitor, and manage active product records from a single operational view.</p>
    </div>
</header>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Inventory Search</h2>
            <p>Filter products by name, category, or stock state.</p>
        </div>
        <?php if ($authController->can('products.create')): ?>
            <a class="button primary" href="<?= e(basePath('index.php?page=new-entry')); ?>">New Entry</a>
        <?php endif; ?>
    </div>
    <form class="form-grid" method="get" action="<?= e(basePath('index.php')); ?>">
        <input type="hidden" name="page" value="products">
        <label>
            <span>Keyword</span>
            <input id="live-search" type="text" name="keyword" value="<?= e($filters['keyword']); ?>" placeholder="Search name or SKU">
        </label>
        <label>
            <span>Category</span>
            <select id="category-filter" name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']); ?>" <?= selectedIf($filters['category'], (string) $category['id']); ?>>
                        <?= e($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Stock State</span>
            <select id="stock-filter" name="stock_level">
                <option value="">All Levels</option>
                <option value="healthy" <?= selectedIf($filters['stock_level'], 'healthy'); ?>>Healthy</option>
                <option value="critical" <?= selectedIf($filters['stock_level'], 'critical'); ?>>Low Stock</option>
                <option value="empty" <?= selectedIf($filters['stock_level'], 'empty'); ?>>Out of Stock</option>
            </select>
        </label>
        <label>
            <span>Archive State</span>
            <select name="archived">
                <option value="" <?= selectedIf($filters['archived'], ''); ?>>Active Only</option>
                <option value="0" <?= selectedIf($filters['archived'], '0'); ?>>Active</option>
                <option value="1" <?= selectedIf($filters['archived'], '1'); ?>>Archived</option>
            </select>
        </label>
        <button class="button ghost" type="submit">Apply Filters</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Product Inventory</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody id="product-table-body">
            <?php if ($products): ?>
                <?php foreach ($products as $product): ?>
                    <?php require __DIR__ . '/row.php'; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6">No products match the current filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

 </div>
</main>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>
