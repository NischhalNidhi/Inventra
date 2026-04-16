<?php
$title = 'Inventra Access';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(basePath('css/style.css')); ?>">
</head>
<body class="login-body" data-theme-enabled="true">
<div class="auth-shell">
    <section class="auth-left">
        <div class="auth-left-content">
            <img class="auth-logo" src="<?= e(appRootPath('logo/inventra_with_logo.png')); ?>" alt="Inventra logo">
            <h1>Manage your inventory easily</h1>
            <ul class="auth-feature-list">
                <li><span class="material-symbols-outlined">check_circle</span><div><strong>Real-time Tracking</strong><small>Monitor stock levels across multiple warehouses instantly.</small></div></li>
                <li><span class="material-symbols-outlined">check_circle</span><div><strong>Automated Restocking</strong><small>Set intelligent thresholds for zero-stock prevention.</small></div></li>
                <li><span class="material-symbols-outlined">check_circle</span><div><strong>Detailed Analytics</strong><small>Predictive insights into turnover and demand trends.</small></div></li>
            </ul>
        </div>
    </section>
    <section class="auth-right">
        <div class="auth-card">
            <h2><?= $authMode === 'set-password' ? 'Set New Password' : 'Sign In'; ?></h2>
            <p>
                <?= $authMode === 'set-password'
                    ? 'Set your permanent password to activate your approved account.'
                    : 'Enter your credentials to access your ledger.'; ?>
            </p>
            <?php foreach ($errors as $error): ?>
                <p class="error-line"><?= e($error); ?></p>
            <?php endforeach; ?>

            <?php if ($authMode === 'set-password' && $passwordSetupUser): ?>
                <form class="login-form" method="post" action="<?= e(basePath('index.php?mode=set-password')); ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                    <input type="hidden" name="action" value="set_password_first_login">
                    <label><span>Account</span><input type="text" value="<?= e((string) $passwordSetupUser['email']); ?>" readonly></label>
                    <label><span>New Password</span><input type="password" name="password" required></label>
                    <label><span>Confirm Password</span><input type="password" name="password_confirm" required></label>
                    <button class="button primary wide" type="submit">Save Password</button>
                </form>
                <div class="auth-footer-note"><a href="<?= e(basePath('index.php')); ?>">Back to Sign In</a></div>
            <?php else: ?>
                <form class="login-form" method="post" action="<?= e(basePath('index.php')); ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                    <input type="hidden" name="action" value="login">
                    <label><span>Email</span><input type="text" name="identifier" placeholder="name@company.com" required></label>
                    <label><span>Password</span><input type="password" name="password" required></label>
                    <div class="auth-aux-row">
                        <label class="auth-checkbox"><input type="checkbox"> Keep me signed in</label>
                        <button class="link-btn" type="button" aria-disabled="true" title="Forgot password flow will be implemented later.">Forgot Password</button>
                    </div>
                    <button class="button primary wide" type="submit">Sign In <span class="material-symbols-outlined">arrow_forward</span></button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>
