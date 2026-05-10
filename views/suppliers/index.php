<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">LOGISTICS / SUPPLIERS</p>
        <h1>Supplier Registry</h1>
        <p class="lead">Manage supplier contacts used by procurement and purchase orders.</p>
    </div>
    <div class="topbar-actions">
        <form class="global-search" action="<?= e(basePath('index.php')); ?>" method="get">
            <input type="hidden" name="page" value="suppliers">
            <input type="text" name="search" placeholder="Search suppliers..." value="<?= e($_GET['search'] ?? ''); ?>">
        </form>
    </div>
</header>

<?php if ($authController->can('suppliers.manage')): ?>
<section class="panel">
    <div class="panel-header"><h2>Create Supplier</h2></div>
    <form class="form-grid" method="post" enctype="multipart/form-data" action="<?= e(basePath('index.php?page=suppliers')); ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="create_supplier">
        <label><span>Name</span><input type="text" name="name" required></label>
        <label><span>Contact Person</span><input type="text" name="contact_person"></label>
        <label><span>Email</span><input type="email" name="email" required></label>
        <label><span>Phone</span><input type="text" name="phone"></label>
        <label class="wide"><span>Supplier Photo</span><input type="file" name="supplier_image" accept=".jpg,.jpeg,.png,.webp"></label>
        <button class="button primary" type="submit">Create</button>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><h2>Supplier Directory</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Supplier</th><th>Contact</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:14px;">
                            <button type="button"
                                    class="media-thumb-button"
                                    data-image-trigger
                                    data-image-src="<?= e(mediaUrl(!empty($supplier['image_name']) ? 'suppliers/' . $supplier['image_name'] : null, (string) $supplier['name'], 'supplier')); ?>"
                                    data-image-title="<?= e($supplier['name']); ?>">
                                <img src="<?= e(mediaUrl(!empty($supplier['image_name']) ? 'suppliers/' . $supplier['image_name'] : null, (string) $supplier['name'], 'supplier')); ?>" alt="<?= e($supplier['name']); ?>" class="media-thumb media-thumb-supplier">
                            </button>
                            <div>
                                <strong><?= e($supplier['name']); ?></strong>
                            </div>
                        </div>
                    </td>
                    <td><?= e((string) $supplier['contact_person']); ?></td>
                    <td><?= e($supplier['email']); ?></td>
                    <td><?= e((string) $supplier['phone']); ?></td>
                    <td><?= (int) $supplier['is_active'] ? 'Active' : 'Inactive'; ?></td>
                    <td><div class="action-group">
                        <?php if ($authController->can('suppliers.manage') && (int) $supplier['is_active'] === 1): ?>
                            <form method="post" action="<?= e(basePath('index.php?page=suppliers')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                                <input type="hidden" name="action" value="deactivate_supplier">
                                <input type="hidden" name="supplier_id" value="<?= e((string) $supplier['id']); ?>">
                                <button class="button small danger-outline" type="submit">Deactivate</button>
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

