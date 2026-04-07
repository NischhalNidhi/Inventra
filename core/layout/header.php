<?php
$flash = getFlash();
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Inventra'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(basePath('css/style.css')); ?>">
</head>
<body class="app-body" data-base-path="<?= e(basePath()); ?>" data-csrf-token="<?= e(csrfToken()); ?>">
<?php if ($flash): ?>
    <div class="toast toast-<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
<?php endif; ?>
<?php if ($user): ?>
    <div class="app-shell">
        <header class="top-nav">
            <div class="top-nav-left">
                <a class="top-brand-logo" href="<?= e(basePath('index.php?page=dashboard')); ?>" aria-label="Inventra Home">
                    <img src="<?= e(appRootPath('logo/inventra%20with%20logo.png')); ?>" alt="Inventra">
                </a>
                <nav class="top-nav-links">
                    <a class="<?= $currentPage === 'dashboard' ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=dashboard')); ?>">Dashboard</a>
                    <a class="<?= in_array($currentPage, ['products', 'new-entry'], true) ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=products')); ?>">Inventory</a>
                    <a class="<?= in_array($currentPage, ['reports'], true) ? 'active' : ''; ?>" href="<?= e(basePath('index.php?page=reports')); ?>">Analytics</a>
                </nav>
            </div>
            <div class="top-nav-right">
                <form class="top-search" action="<?= e(basePath('index.php')); ?>" method="get">
                    <input type="hidden" name="page" value="products">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" name="keyword" placeholder="Global Ledger Search..." value="<?= e($_GET['keyword'] ?? ''); ?>">
                </form>
                <div class="icon-menu" data-notifications-menu>
                    <button type="button" class="top-icon-btn" aria-label="Notifications" aria-haspopup="menu" aria-expanded="false" data-notifications-trigger>
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="dot-badge"></span>
                    </button>
                    <div class="icon-dropdown" role="menu" data-notifications-dropdown>
                        <div class="icon-dropdown-head">
                            <strong>Notifications</strong>
                            <button type="button" class="link-btn" data-clear-notifications>Clear</button>
                        </div>
                        <ul class="notif-list" data-notif-list>
                            <li><span class="material-symbols-outlined">inventory</span><div><strong>Low Stock Alert</strong><small>3 products are below threshold.</small></div></li>
                            <li><span class="material-symbols-outlined">local_shipping</span><div><strong>PO In Transit</strong><small>PO-20260331-1142 updated to in transit.</small></div></li>
                            <li><span class="material-symbols-outlined">analytics</span><div><strong>Inventory Report</strong><small>Latest metrics are ready for review.</small></div></li>
                        </ul>
                    </div>
                </div>
                <div class="icon-menu" data-settings-menu>
                    <button type="button" class="top-icon-btn" aria-label="Settings" aria-haspopup="menu" aria-expanded="false" data-settings-trigger>
                        <span class="material-symbols-outlined">settings</span>
                    </button>
                    <div class="icon-dropdown" role="menu" data-settings-dropdown>
                        <div class="icon-dropdown-head"><strong>Settings</strong></div>
                        <button class="menu-item" type="button" data-theme-toggle>
                            <span class="material-symbols-outlined">dark_mode</span>
                            <span>Toggle Light/Dark Mode</span>
                        </button>
                        <a class="menu-item" href="<?= e(basePath('index.php?page=users')); ?>">
                            <span class="material-symbols-outlined">manage_accounts</span>
                            <span>User Management</span>
                        </a>
                        <a class="menu-item" href="<?= e(basePath('index.php?page=reports')); ?>">
                            <span class="material-symbols-outlined">analytics</span>
                            <span>Reports</span>
                        </a>
                    </div>
                </div>
                <div class="profile-menu" data-profile-menu>
                    <button type="button" class="profile-trigger" aria-haspopup="menu" aria-expanded="false" data-profile-trigger>
                        <span class="top-avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'I', 0, 1))); ?></span>
                    </button>
                    <div class="profile-dropdown" role="menu" data-profile-dropdown>
                        <div class="profile-dropdown-head">
                            <div class="top-avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'I', 0, 1))); ?></div>
                            <div class="profile-dropdown-meta">
                                <strong><?= e($user['full_name'] ?? ''); ?></strong>
                                <small><?= e($user['role'] ?? ''); ?></small>
                            </div>
                        </div>
                        <form method="post" action="<?= e(basePath('index.php?page=logout')); ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                            <input type="hidden" name="action" value="logout">
                            <button class="button ghost wide" type="submit">Sign Out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <?php require __DIR__ . '/navbar.php'; ?>
        <main class="main-content">
            <div class="blueprint-grid"></div>
            <div class="main-bubble bubble-a"></div>
            <div class="main-bubble bubble-b"></div>
            <div class="main-bubble bubble-c"></div>
            <div class="main-content-inner">
<?php endif; ?>
