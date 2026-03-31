<?php require __DIR__ . '/../../includes/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">SYSTEM CORE / REGISTRY</p>
        <h1>High Precision Ledger</h1>
        <p class="lead">Real-time architectural overview of structural assets and stock condition.</p>
    </div>
    <div class="topbar-actions">
        <a class="button ghost" href="<?= e(basePath('index.php?page=products')); ?>">Category Filter</a>
        <?php if ($authController->can('products.create')): ?>
            <a class="button primary" href="<?= e(basePath('index.php?page=new-entry')); ?>"><span class="material-symbols-outlined">add</span>Provision New Asset</a>
        <?php endif; ?>
    </div>
</header>

<section class="stats-grid">
    <article class="stat-card primary">
        <span class="stat-label">Active Components</span>
        <strong><?= e((string) count($products)); ?></strong>
        <small>Current filtered records</small>
    </article>
    <article class="stat-card muted">
        <span class="stat-label">Supply Integrity</span>
        <?php
        $safeCount = 0;
        foreach ($products as $item) {
            if ((int) $item['stock_quantity'] > (int) $item['min_threshold']) {
                $safeCount++;
            }
        }
        $integrity = count($products) > 0 ? round(($safeCount / count($products)) * 100, 1) : 100;
        ?>
        <strong><?= e((string) $integrity); ?>%</strong>
        <small>Products above threshold</small>
    </article>
    <article class="stat-card danger">
        <span class="stat-label">Action Required</span>
        <?php
        $criticalCount = 0;
        foreach ($products as $item) {
            if ((int) $item['stock_quantity'] <= (int) $item['min_threshold']) {
                $criticalCount++;
            }
        }
        ?>
        <strong><?= e((string) $criticalCount); ?></strong>
        <small>Items below threshold</small>
    </article>
</section>

<section class="panel">
    <div class="panel-header filter-grid">
        <label class="search-field">
            <span>Keyword</span>
            <input type="text" id="live-search" placeholder="Search name, SKU, category..." value="<?= e($filters['keyword'] ?? ''); ?>">
        </label>
        <label class="search-field">
            <span>Category Filter</span>
            <select id="category-filter">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']); ?>" <?= selectedIf($filters['category'] ?? '', (string) $category['id']); ?>>
                        <?= e($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="search-field">
            <span>Stock Level</span>
            <select id="stock-filter">
                <option value="">All Levels</option>
                <option value="healthy" <?= selectedIf($filters['stock_level'] ?? '', 'healthy'); ?>>Healthy</option>
                <option value="critical" <?= selectedIf($filters['stock_level'] ?? '', 'critical'); ?>>Critical</option>
                <option value="empty" <?= selectedIf($filters['stock_level'] ?? '', 'empty'); ?>>Out of Stock</option>
            </select>
        </label>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Asset Specification</th>
                <th>SKU Identifier</th>
                <th>Quantity</th>
                <th>Status Marker</th>
                <th>Utility</th>
            </tr>
            </thead>
            <tbody id="product-table-body">
            <?php foreach ($products as $product): ?>
                <?php require __DIR__ . '/row.php'; ?>
            <?php endforeach; ?>
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
