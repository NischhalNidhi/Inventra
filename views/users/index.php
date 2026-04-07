<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">ACCESS CONTROL / STAFF</p>
        <h1>User Management</h1>
        <p class="lead">Manager-only account creation, role assignment, and deactivation.</p>
    </div>
</header>

<section class="panel">
    <div class="panel-header">
        <h2>Create Staff Account</h2>
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
        <label class="wide"><span>Password</span><input type="password" name="password" required></label>
        <button class="button primary wide" type="submit">Create User</button>
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
                    <td class="action-group">
                        <form method="post" action="<?= e(basePath('index.php?page=users')); ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                            <input type="hidden" name="action" value="deactivate_user">
                            <input type="hidden" name="user_id" value="<?= e((string) $staff['id']); ?>">
                            <button class="button small danger-outline" type="submit">Deactivate</button>
                        </form>
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
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>
