<?php
$flash = getFlash();
$user = currentUser();
$topSearchPage = in_array($currentPage ?? '', ['categories', 'suppliers', 'users', 'purchase-orders', 'products'], true)
    ? $currentPage
    : 'products';
$topSearchName = $topSearchPage === 'products' ? 'keyword' : 'search';
$topSearchValue = $_GET[$topSearchName] ?? '';
$topSearchPlaceholderMap = [
    'categories' => 'Search categories...',
    'suppliers' => 'Search suppliers...',
    'users' => 'Search staff accounts...',
    'purchase-orders' => 'Search PO number or supplier...',
    'products' => 'Search products or SKU...',
];
$topSearchPlaceholder = $topSearchPlaceholderMap[$topSearchPage] ?? 'Search products or SKU...';
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
    <link rel="stylesheet" href="<?= e(basePath('css/style.css')) . '?v=' . filemtime(dirname(__DIR__, 2) . '/public/css/style.css'); ?>">
</head>
<body class="app-body" data-base-path="<?= e(basePath()); ?>" data-app-root-path="<?= e(appRootPath()); ?>" data-csrf-token="<?= e(csrfToken()); ?>">
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
                    <input type="hidden" name="page" value="<?= e($topSearchPage); ?>">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" name="<?= e($topSearchName); ?>" placeholder="<?= e($topSearchPlaceholder); ?>" value="<?= e((string) $topSearchValue); ?>">
                </form>
                <div class="icon-menu" data-notifications-menu>
                    <?php
                        $unreadCount = isset($notificationModel) ? $notificationModel->countUnread((int)$user['id']) : 0;
                        $notifications = isset($notificationModel) ? $notificationModel->getUnread((int)$user['id'], 5) : [];
                    ?>
                    <button type="button" class="top-icon-btn" aria-label="Notifications" aria-haspopup="menu" aria-expanded="false" data-notifications-trigger>
                        <span class="material-symbols-outlined">notifications</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="dot-badge" data-unread-badge></span>
                        <?php else: ?>
                            <span class="dot-badge" data-unread-badge style="display:none;"></span>
                        <?php endif; ?>
                    </button>
                    <div class="icon-dropdown" role="menu" data-notifications-dropdown>
                        <div class="icon-dropdown-head">
                            <strong>Notifications</strong>
                            <button type="button" class="link-btn" data-clear-notifications>Clear</button>
                        </div>
                        <ul class="notif-list" data-notif-list>
                            <?php if (empty($notifications)): ?>
                                <li data-empty-notif><span class="material-symbols-outlined">notifications_off</span><div><strong>No new notifications</strong><small>All caught up!</small></div></li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <?php 
                                        $icon = 'info';
                                        if ($notif['type'] === 'low_stock' || $notif['type'] === 'out_of_stock') $icon = 'inventory';
                                        if ($notif['type'] === 'po_update') $icon = 'local_shipping';
                                    ?>
                                    <li data-notif-id="<?= e((string)$notif['id']) ?>">
                                        <span class="material-symbols-outlined"><?= $icon ?></span>
                                        <div>
                                            <strong><?= e($notif['title']) ?></strong>
                                            <small><?= e($notif['message']) ?></small>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                <div class="media-lightbox" data-image-lightbox hidden>
                    <div class="media-lightbox-backdrop" data-lightbox-close></div>
                    <div class="media-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Image preview">
                        <button type="button" class="media-lightbox-close" data-lightbox-close aria-label="Close image preview">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                        <img src="" alt="" class="media-lightbox-image" data-lightbox-image>
                        <div class="media-lightbox-caption" data-lightbox-caption></div>
                    </div>
                </div>
<?php endif; ?>
