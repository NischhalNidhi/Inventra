<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">STORE TEAM / ACCESS</p>
        <h1>Staff Accounts</h1>
        <p class="lead">Create staff accounts and send secure welcome setup links to new team members.</p>
    </div>
</header>

<section class="panel">
    <div class="panel-header">
        <h2>Add Staff Account</h2>
    </div>
    <form class="form-grid" method="post" action="<?= e(basePath('index.php?page=users')); ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="create_user">
        <label><span>Full Name</span><input type="text" name="full_name" required></label>
        <label><span>Email</span><input type="email" name="email" required></label>
        <label><span>Username</span><input type="text" name="username" required></label>
        <label>
            <span>Role</span>
            <select name="role" required>
                <option value="Supervisor">Supervisor</option>
                <option value="Salesman">Salesman</option>
                <option value="Logistic Handler">Logistic Handler</option>
                <option value="Manager">Manager</option>
            </select>
        </label>
        <p class="lead wide">A one-time setup link will be emailed to this staff member. The link expires after 24 hours.</p>
        <button class="button primary wide" type="submit">Create Account and Send Welcome Email</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header"><h2>Current Staff</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $staff): ?>
                <tr>
                    <td><?= e($staff['full_name']); ?></td>
                    <td><?= e($staff['email']); ?></td>
                    <td><?= e($staff['username']); ?></td>
                    <td><?= e($staff['role']); ?></td>
                    <td><?= (int) $staff['is_active'] ? 'Active' : 'Inactive'; ?></td>
                    <td class="td-actions">
                        <div class="action-group">
                        <form method="post" action="<?= e(basePath('index.php?page=users')); ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                            <input type="hidden" name="action" value="deactivate_user">
                            <input type="hidden" name="user_id" value="<?= e((string) $staff['id']); ?>">
                            <button class="btn-action btn-delete" type="submit" title="Deactivate user">
                                <span class="material-symbols-outlined">person_off</span>
                                <span class="btn-label">Deactivate</span>
                            </button>
                        </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

 </div>
</main>
</div>
<script src="<?= e(assetPath('js/app.js')); ?>"></script>
</body>
</html>
