<?php require __DIR__ . '/../../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">SYSTEM CORE / INVENTORY</p>
        <h1>High Precision<br>Ledger</h1>
        <p class="lead">Real-time operational view of products, stock health, and replenishment risk.</p>
    </div>
    <div class="topbar-actions">
        <form class="global-search" action="<?= e(basePath('index.php')); ?>" method="get">
            <input type="hidden" name="page" value="products">
            <input type="text" name="keyword" placeholder="Global ledger search..." value="<?= e($_GET['keyword'] ?? ''); ?>">
        </form>
    </div>
</header>

<section class="stats-grid">
    <article class="stat-card primary">
        <span class="stat-label">Total Components</span>
        <strong><?= e((string) $stats['total_products']); ?></strong>
        <small>Active product records</small>
    </article>
    <article class="stat-card muted">
        <span class="stat-label">Stock Health</span>
        <strong><?= e((string) $stats['health_percentage']); ?>%</strong>
        <small>Products above threshold</small>
    </article>
    <article class="stat-card danger">
        <span class="stat-label">Critical Alert</span>
        <strong><?= e((string) $stats['critical_count']); ?></strong>
        <small>Products at or below minimum threshold</small>
    </article>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Structural Asset Directory</h2>
            <p>Latest inventory updates.</p>
        </div>
        <a class="button ghost" href="<?= e(basePath('index.php?page=products')); ?>">View Full Inventory</a>
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
        <p class="eyebrow">Monitoring Snapshot</p>
        <h3>Operational queue and logistics state.</h3>
        <div class="insight-stats">
            <div>
                <strong><?= e((string) $stats['pending_po']); ?></strong>
                <small>Pending PO</small>
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
                <?php foreach ($recentActivity as $event): ?>
                    <tr>
                        <td><?= e($event['product_name']); ?></td>
                        <td><?= e(strtoupper((string) $event['movement_type'])); ?></td>
                        <td><?= e((string) $event['quantity']); ?></td>
                        <td><?= e($event['full_name']); ?></td>
                    </tr>
                <?php endforeach; ?>
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
