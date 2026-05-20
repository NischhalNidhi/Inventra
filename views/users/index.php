<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">STORE TEAM / ACCESS</p>
        <h1>Staff Accounts</h1>
        <p class="lead">Create staff accounts and send secure welcome setup links to new team members.</p>
    </div>
    <div class="topbar-actions">
        <form class="global-search" action="<?= e(basePath('index.php')); ?>" method="get">
            <input type="hidden" name="page" value="users">
            <input type="text" name="search" placeholder="Search staff accounts..." value="<?= e($_GET['search'] ?? ''); ?>">
        </form>
    </div>
</header>

<section class="panel">
    <?php if ($editingUser): ?>
        <div class="panel-header">
            <h2>Edit Staff Account: <?= e($editingUser['full_name']); ?></h2>
            <a href="<?= e(basePath('index.php?page=users')); ?>" class="button ghost small">Cancel Edit</a>
        </div>
        <form class="form-grid" method="post" action="<?= e(basePath('index.php?page=users')); ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" value="<?= e((string) $editingUser['id']); ?>">
            
            <label><span>Full Name</span><input type="text" name="full_name" value="<?= e($editingUser['full_name']); ?>" required></label>
            <label><span>Email</span><input type="email" name="email" value="<?= e($editingUser['email']); ?>" required></label>
            <label><span>Username</span><input type="text" value="<?= e($editingUser['username']); ?>" disabled title="Username cannot be changed"></label>
            <label>
                <span>Role</span>
                <select name="role" required>
                    <?php $roles = ['Supervisor', 'Salesman', 'Logistic Handler', 'Manager']; ?>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role; ?>" <?= $editingUser['role'] === $role ? 'selected' : ''; ?>><?= $role; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Password</span><input type="password" name="password" placeholder="Leave blank to keep current"></label>
            <button class="button primary wide" type="submit">Update Staff Details</button>
        </form>
    <?php else: ?>
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
            <label><span>Password</span><input type="password" name="password" placeholder="Optional: Set password now"></label>
            <button class="button primary wide" type="submit">Create Account and Send Welcome Email</button>
        </form>
    <?php endif; ?>
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
                    <td><div class="action-group">
                        <a href="<?= e(basePath('index.php?page=users&edit_id=' . $staff['id'])); ?>" class="button small ghost">Edit</a>
                        
                        <?php if ((int) $staff['is_active']): ?>
                            <?php if ((int) $staff['id'] !== (int) currentUser()['id']): ?>
                                <form method="post" action="<?= e(basePath('index.php?page=users')); ?>" 
                                      onsubmit="return confirm('Are you sure you want to deactivate <?= e($staff['full_name']); ?>? They will no longer be able to log in.');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                                    <input type="hidden" name="action" value="deactivate_user">
                                    <input type="hidden" name="user_id" value="<?= e((string) $staff['id']); ?>">
                                    <button class="button small danger-outline" type="submit">Deactivate</button>
                                </form>
                            <?php else: ?>
                                <span class="badge ghost" title="You cannot deactivate the account you are currently using.">Current User</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="post" action="<?= e(basePath('index.php?page=users')); ?>" 
                                  onsubmit="return confirm('Restore access for <?= e($staff['full_name']); ?>?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                                <input type="hidden" name="action" value="activate_user">
                                <input type="hidden" name="user_id" value="<?= e((string) $staff['id']); ?>">
                                <button class="button small ghost" type="submit">Reactivate</button>
                            </form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    </div>
</section>

 </div>
</main>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>
