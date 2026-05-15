<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">STORE OVERVIEW / TODAY</p>
        <h1>Department Store<br>Dashboard</h1>
        <p class="lead">Track stock health, urgent replenishment needs, and recent inventory activity across the store.</p>
    </div>
    <div class="topbar-actions">
        <div class="live-indicator" id="live-indicator" title="Stock levels update every 2 seconds">
            <span class="live-dot"></span>
            <span class="live-label">LIVE</span>
        </div>
        <form class="global-search" action="<?= e(basePath('index.php')); ?>" method="get">
            <input type="hidden" name="page" value="products">
            <input type="text" name="keyword" placeholder="Search products or SKU..." value="<?= e($_GET['keyword'] ?? ''); ?>">
        </form>
    </div>
</header>

<section class="stats-grid" id="dashboard-stats">
    <article class="stat-card primary">
        <span class="stat-label">Active Products</span>
        <strong id="stat-total-products"><?= e((string) $stats['total_products']); ?></strong>
        <small>Products currently listed</small>
    </article>
    <article class="stat-card muted">
        <span class="stat-label">Total Value</span>
        <strong id="stat-total-value">NPR <?= number_format($stats['total_value'] ?? 0); ?></strong>
        <small>Est. on-hand inventory value</small>
    </article>
    <article class="stat-card muted">
        <span class="stat-label">Stock Health</span>
        <strong id="stat-health-pct"><?= e((string) $stats['health_percentage']); ?>%</strong>
        <small>Products above reorder level</small>
    </article>
    <article class="stat-card danger">
        <span class="stat-label">Alerts</span>
        <strong id="stat-critical-count"><?= e((string) $stats['critical_count']); ?></strong>
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
            <tbody id="featured-products-body">
            <?php foreach ($featuredProducts as $product): ?>
                <?php
                    $fpQty = (int) $product['stock_quantity'];
                    $fpThr = (int) $product['min_threshold'];
                    $fpOut = $fpQty === 0;
                    $fpLow = !$fpOut && $fpQty <= $fpThr;
                    if ($fpOut) { $fpBadge = 'out'; $fpText = 'OUT OF STOCK'; }
                    elseif ($fpLow) { $fpBadge = 'low'; $fpText = 'LOW STOCK'; }
                    else { $fpBadge = 'healthy'; $fpText = 'IN STOCK'; }
                ?>
                <tr>
                    <td><?= e($product['name']); ?></td>
                    <td><?= e($product['sku']); ?></td>
                    <td><?= e($product['category_name'] ?? 'Unassigned'); ?></td>
                    <td><strong class="stock-inline <?= $fpBadge ?>"><?= e((string) $fpQty); ?></strong></td>
                    <td><span class="badge <?= $fpBadge ?>"><?= $fpText ?></span></td>
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
                <strong id="stat-pending-po"><?= e((string) $stats['pending_po']); ?></strong>
                <small>Pending purchase orders</small>
            </div>
            <div>
                <strong id="stat-total-suppliers"><?= e((string) $stats['total_suppliers']); ?></strong>
                <small>Active suppliers</small>
            </div>
            <div>
                <strong id="stat-out-of-stock"><?= e((string) $stats['out_of_stock']); ?></strong>
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
                <tbody id="alerts-table-body">
                <?php
                $activeAlerts = array_filter($dashboardAlerts ?? [], function($a) {
                    return (int)($a['stock_quantity'] ?? 0) > 0;
                });
                ?>
                <?php if ($activeAlerts): ?>
                    <?php foreach ($activeAlerts as $alert): ?>
                        <tr>
                            <td><?= e($alert['name']); ?></td>
                            <td><?= e($alert['sku']); ?></td>
                            <td><strong class="stock-inline low"><?= e((string) $alert['stock_quantity']); ?></strong></td>
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
                <tbody id="activity-table-body">
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
<script src="<?= e(assetPath('js/app.js')); ?>"></script>
</body>
</html>
