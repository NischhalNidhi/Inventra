<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">LOGISTICS / SUPPLIERS</p>
        <h1>Supplier Registry</h1>
        <p class="lead">Manage supplier contacts used by procurement and purchase orders.</p>
    </div>
</header>

<?php if ($authController->can('suppliers.manage')): ?>
<section class="panel">
    <div class="panel-header"><h2>Create Supplier</h2></div>
    <form class="form-grid" method="post" action="<?= e(basePath('index.php?page=suppliers')); ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="create_supplier">
        <label><span>Name</span><input type="text" name="name" required></label>
        <label><span>Contact Person</span><input type="text" name="contact_person"></label>
        <label><span>Email</span><input type="email" name="email" required></label>
        <label><span>Phone</span><input type="text" name="phone"></label>
        <button class="button primary" type="submit">Create</button>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><h2>Supplier Directory</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><?= e($supplier['name']); ?></td>
                    <td><?= e((string) $supplier['contact_person']); ?></td>
                    <td><?= e($supplier['email']); ?></td>
                    <td><?= e((string) $supplier['phone']); ?></td>
                    <td><?= (int) $supplier['is_active'] ? 'Active' : 'Inactive'; ?></td>
                    <td class="td-actions">
                        <?php if ($authController->can('suppliers.manage') && (int) $supplier['is_active'] === 1): ?>
                            <div class="action-group">
                            <form method="post" action="<?= e(basePath('index.php?page=suppliers')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                                <input type="hidden" name="action" value="deactivate_supplier">
                                <input type="hidden" name="supplier_id" value="<?= e((string) $supplier['id']); ?>">
                                <button class="btn-action btn-delete" type="submit" title="Deactivate supplier">
                                    <span class="material-symbols-outlined">block</span>
                                    <span class="btn-label">Deactivate</span>
                                </button>
                            </form>
                            </div>
                        <?php endif; ?>
                    </td>
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
<script src="<?= e(assetPath('js/app.js')); ?>"></script>
</body>
</html>
