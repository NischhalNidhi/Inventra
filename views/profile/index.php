<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">SETTINGS / PROFILE</p>
        <h1>My Profile</h1>
        <p class="lead">Update your personal details and profile picture.</p>
    </div>
</header>

<div class="panel" style="max-width: 600px;">
    <div class="panel-header">
        <h2>Profile Information</h2>
    </div>
    <form class="form-grid" method="post" action="<?= e(basePath('index.php')); ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="update_profile">

        <label style="grid-column: 1 / -1;">
            <span>Profile Picture</span>
            <div style="display:flex;align-items:center;gap:1rem;margin-top:0.5rem;">
                <?php if ($userImage): ?>
                    <img src="<?= e($userImage); ?>" alt="Avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:80px;height:80px;border-radius:50%;background:var(--surface-mid);display:grid;place-items:center;font-size:2rem;font-weight:700;">
                        <?= e($userInitial); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <input type="file" name="profile_image" accept="image/png, image/jpeg, image/webp" style="margin-top: 0.5rem;">
                    <small style="display:block;margin-top:0.25rem;">Max size: 2MB. Format: JPG, PNG, WEBP.</small>
                </div>
            </div>
        </label>

        <label style="grid-column: 1 / -1;">
            <span>Full Name</span>
            <input type="text" name="full_name" value="<?= e($user['full_name']); ?>" required>
        </label>
        
        <label style="grid-column: 1 / -1;">
            <span>Email</span>
            <input type="email" value="<?= e($user['email']); ?>" disabled>
            <small>Contact administrator to change email.</small>
        </label>

        <label style="grid-column: 1 / -1;">
            <span>Role</span>
            <input type="text" value="<?= e($user['role']); ?>" disabled>
        </label>

        <div style="grid-column: 1 / -1; margin-top: 1rem;">
            <button class="button primary" type="submit">Save Changes</button>
        </div>
    </form>
</div>

 </div>
</main>
</div>
<script src="<?= e(assetPath('js/app.js')); ?>"></script>
</body>
</html>
