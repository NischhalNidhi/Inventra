<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">STORE OVERVIEW / TODAY</p>
        <h1>Department Store<br>Dashboard</h1>
        <p class="lead">Track stock health, urgent replenishment needs, and recent inventory activity across the store.</p>
    </div>
    <div class="topbar-actions">
        <form class="global-search" action="<?= e(basePath('index.php')); ?>" method="get">
            <input type="hidden" name="page" value="products">
            <input type="text" name="keyword" placeholder="Search products or SKU..." value="<?= e($_GET['keyword'] ?? ''); ?>">
        </form>
    </div>
</header>

<section class="stats-grid">
    <article class="stat-card primary">
        <span class="stat-label">Active Products</span>
        <strong><?= e((string) $stats['total_products']); ?></strong>
        <small>Products currently listed</small>
    </article>
    <article class="stat-card muted">
        <span class="stat-label">Total Value</span>
        <strong>NPR <?= number_format($stats['total_value'] ?? 0); ?></strong>
        <small>Est. on-hand inventory value</small>
    </article>
    <article class="stat-card muted">
        <span class="stat-label">Stock Health</span>
        <strong><?= e((string) $stats['health_percentage']); ?>%</strong>
        <small>Products above reorder level</small>
    </article>
    <article class="stat-card danger">
        <span class="stat-label">Alerts</span>
        <strong><?= e((string) $stats['critical_count']); ?></strong>
        <small>At or below minimum stock</small>
    </article>
</section>

<section class="panel ai-insight-panel" style="border-left: 4px solid #6366f1; background: #f8fafc;">
    <div class="panel-header">
        <div>
            <h2 style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #6366f1;"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path><path d="M5 3v4"></path><path d="M19 17v4"></path><path d="M3 5h4"></path><path d="M17 19h4"></path></svg>
                AI Smart Insight
            </h2>
            <p>Automated business analysis based on recent sales trends.</p>
        </div>
    </div>
    <div style="padding: 1rem; font-size: 1.1rem; line-height: 1.6; color: #1e293b;">
        <p><em><?= e($aiInsight); ?></em></p>
    </div>
</section>

<section class="panel low-stock-graph-panel">
    <div class="panel-header">
        <div>
            <h2>Low Stock Overview</h2>
            <p>Visual stock comparison against minimum thresholds.</p>
        </div>
    </div>
    <div class="graph-controls">
        <button type="button" class="button ghost small graph-filter-btn active" data-graph-filter="low">Low Stock Only</button>
        <button type="button" class="button ghost small graph-filter-btn" data-graph-filter="all">All Products</button>
    </div>
    <div class="graph-container" id="low-stock-graph-container"
         data-alert-graph='<?= e(json_encode($alertGraph, JSON_HEX_APOS | JSON_HEX_TAG)); ?>'>
        <canvas id="low-stock-canvas"></canvas>
        <p class="graph-empty-state" id="graph-empty-state" style="display:none;">
            <span class="material-symbols-outlined">check_circle</span>
            All products are above their minimum stock thresholds.
        </p>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Recently Updated Products</h2>
            <p>Search and filter products directly from the dashboard.</p>
        </div>
        <a class="button ghost" href="<?= e(basePath('index.php?page=products')); ?>">Open Inventory</a>
    </div>
    <form class="form-grid dashboard-product-filters" action="#" method="get">
        <label>
            <span>Search</span>
            <input id="live-search" type="text" name="keyword" placeholder="Search product, SKU, or category...">
        </label>
        <label>
            <span>Category</span>
            <select id="category-filter" name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']); ?>"><?= e($category['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody id="product-table-body">
            <?php foreach ($featuredProducts as $product): ?>
                <?php $low = (int) $product['stock_quantity'] <= (int) $product['min_threshold']; ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:14px;">
                            <button type="button"
                                    class="media-thumb-button"
                                    data-image-trigger
                                    data-image-src="<?= e(mediaUrl(!empty($product['image_name']) ? 'products/' . $product['image_name'] : null, (string) $product['name'], 'product')); ?>"
                                    data-image-title="<?= e($product['name']); ?>">
                                <img src="<?= e(mediaUrl(!empty($product['image_name']) ? 'products/' . $product['image_name'] : null, (string) $product['name'], 'product')); ?>" alt="<?= e($product['name']); ?>" class="media-thumb media-thumb-product">
                            </button>
                            <strong><?= e($product['name']); ?></strong>
                        </div>
                    </td>
                    <td><?= e($product['sku']); ?></td>
                    <td><?= e($product['category_name'] ?? 'Unassigned'); ?></td>
                    <td><?= e((string) $product['stock_quantity']); ?></td>
                    <td><span class="badge <?= $low ? 'low' : 'healthy'; ?>"><?= $low ? 'LOW STOCK' : 'IN STOCK'; ?></span></td>
                    <td><a class="button small ghost" href="<?= e(basePath('index.php?page=products')); ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="insight-grid">
    <article class="insight-card">
        <p class="eyebrow">Store Snapshot</p>
        <h3>What needs attention before the next replenishment cycle.</h3>
        <div class="insight-stats">
            <div>
                <strong><?= e((string) $stats['pending_po']); ?></strong>
                <small>Pending purchase orders</small>
            </div>
            <div>
                <strong><?= e((string) $stats['total_suppliers']); ?></strong>
                <small>Active suppliers</small>
            </div>
            <div>
                <strong><?= e((string) $stats['out_of_stock']); ?></strong>
                <small>Out of stock</small>
            </div>
        </div>
    </article>
    <article class="panel">
        <div class="panel-header">
            <div>
                <h2>Low Stock Watchlist</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>On Hand</th>
                    <th>Minimum</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($dashboardAlerts): ?>
                    <?php foreach ($dashboardAlerts as $alert): ?>
                        <tr>
                            <td><?= e($alert['name']); ?></td>
                            <td><?= e($alert['sku']); ?></td>
                            <td><?= e((string) $alert['stock_quantity']); ?></td>
                            <td><?= e((string) $alert['min_threshold']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No low-stock items right now.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
    <article class="panel">
        <div class="panel-header">
            <div>
                <h2>Recent Activity</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>User</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($recentActivity): ?>
                    <?php foreach ($recentActivity as $event): ?>
                        <tr>
                            <td><?= e($event['product_name']); ?></td>
                            <td><?= e(strtoupper((string) $event['movement_type'])); ?></td>
                            <td><?= e((string) $event['quantity']); ?></td>
                            <td><?= e($event['full_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No recent stock activity yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

 </div>
</main>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>
