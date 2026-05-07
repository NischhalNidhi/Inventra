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

<section class="panel low-stock-graph-panel">
    <div class="panel-header">
        <div>
            <h2>Low Stock Alert Graph</h2>
            <p>Visual comparison of current stock levels against minimum thresholds.</p>
        </div>
        <div class="graph-legend">
            <span class="legend-item"><span class="legend-dot legend-stock"></span>Current Stock</span>
            <span class="legend-item"><span class="legend-dot legend-threshold"></span>Min Threshold</span>
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
            <p>Quick view of the products most recently changed.</p>
        </div>
        <a class="button ghost" href="<?= e(basePath('index.php?page=products')); ?>">Open Inventory</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($featuredProducts as $product): ?>
                <?php $low = (int) $product['stock_quantity'] <= (int) $product['min_threshold']; ?>
                <tr>
                    <td><?= e($product['name']); ?></td>
                    <td><?= e($product['sku']); ?></td>
                    <td><?= e($product['category_name'] ?? 'Unassigned'); ?></td>
                    <td><?= e((string) $product['stock_quantity']); ?></td>
                    <td><span class="badge <?= $low ? 'low' : 'healthy'; ?>"><?= $low ? 'LOW STOCK' : 'IN STOCK'; ?></span></td>
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
