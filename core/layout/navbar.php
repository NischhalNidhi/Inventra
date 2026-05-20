<?php
$currentPage = $currentPage ?? 'dashboard';
$user = currentUser();
?>
<aside class="sidebar">
    <nav class="sidebar-nav">
        <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=dashboard')); ?>" title="Dashboard">
            <span class="material-symbols-outlined nav-icon">grid_view</span>
            <span>Dashboard</span>
        </a>
        <a class="nav-link <?= $currentPage === 'products' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=products')); ?>" title="Inventory">
            <span class="material-symbols-outlined nav-icon">inventory_2</span>
            <span>Store Inventory</span>
        </a>
        <?php if ($authController->can('products.create')): ?>
            <a class="nav-link <?= $currentPage === 'new-entry' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=new-entry')); ?>" title="New Entry">
                <span class="material-symbols-outlined nav-icon">add</span>
                <span>Add Product</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('users.view')): ?>
            <a class="nav-link <?= $currentPage === 'users' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=users')); ?>" title="Users">
                <span class="material-symbols-outlined nav-icon">group</span>
                <span>Users</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('categories.view')): ?>
            <a class="nav-link <?= $currentPage === 'categories' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=categories')); ?>" title="Categories">
                <span class="material-symbols-outlined nav-icon">category</span>
                <span>Categories</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('suppliers.view')): ?>
            <a class="nav-link <?= $currentPage === 'suppliers' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=suppliers')); ?>" title="Suppliers">
                <span class="material-symbols-outlined nav-icon">domain</span>
                <span>Suppliers</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('po.view')): ?>
            <a class="nav-link <?= $currentPage === 'purchase-orders' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=purchase-orders')); ?>" title="PO Tracker">
                <span class="material-symbols-outlined nav-icon">local_shipping</span>
                <span>Purchase Orders</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('logistics.delivery_log')): ?>
            <a class="nav-link <?= $currentPage === 'delivery-log' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=delivery-log')); ?>" title="Delivery Log">
                <span class="material-symbols-outlined nav-icon">receipt_long</span>
                <span>Delivery Log</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('logistics.reorder')): ?>
            <a class="nav-link <?= $currentPage === 'reorder' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=reorder')); ?>" title="Reorder">
                <span class="material-symbols-outlined nav-icon">inventory</span>
                <span>Reorder</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('stock.in')): ?>
            <a class="nav-link <?= $currentPage === 'stock-in' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=stock-in')); ?>" title="Stock In">
                <span class="material-symbols-outlined nav-icon">login</span>
                <span>Stock In</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('stock.out')): ?>
            <a class="nav-link <?= $currentPage === 'stock-out' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=stock-out')); ?>" title="Stock Out">
                <span class="material-symbols-outlined nav-icon">logout</span>
                <span>Stock Out</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('reports.sales.insight')): ?>
            <a class="nav-link <?= $currentPage === 'ai-insights' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=ai-insights')); ?>" title="AI Insights">
                <span class="material-symbols-outlined nav-icon">auto_awesome</span>
                <span>AI Insights</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('reports.heatmap')): ?>
            <a class="nav-link <?= $currentPage === 'heatmap' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=heatmap')); ?>" title="Sales Heatmap">
                <span class="material-symbols-outlined nav-icon">map</span>
                <span>Sales Heatmap</span>
            </a>
        <?php endif; ?>
        <?php if ($authController->can('reports.sales.daily') || $authController->can('reports.inventory')): ?>
            <a class="nav-link <?= $currentPage === 'reports' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=reports')); ?>" title="Reports">
                <span class="material-symbols-outlined nav-icon">analytics</span>
                <span>Reports</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-pill">
            <div class="user-avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'I', 0, 1))); ?></div>
            <div class="user-pill-details">
                <p><?= e($user['full_name'] ?? ''); ?></p>
                <small><?= e($user['role'] ?? ''); ?></small>
            </div>
        </div>
    </div>
</aside>
