<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">INVENTORY / CATEGORIES</p>
        <h1>Category Management</h1>
        <p class="lead">Define product categories and keep classification taxonomy clean.</p>
    </div>
</header>

<?php if ($authController->can('categories.manage')): ?>
<section class="panel">
    <div class="panel-header"><h2>Create Category</h2></div>
    <form class="form-grid category-form" method="post" action="<?= e(basePath('index.php?page=categories')); ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="create_category">
        <label><span>Name</span><input type="text" name="name" required></label>
        <label class="wide"><span>Description</span><input type="text" name="description"></label>
        <button class="button primary" type="submit">Create</button>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><h2>Categories</h2></div>
    <div class="table-wrap">
        <table class="category-table <?= $authController->can('categories.manage') ? '' : 'view-only'; ?>">
            <colgroup>
                <col style="width: 34%;">
                <col>
                <?php if ($authController->can('categories.manage')): ?>
                    <col style="width: 180px;">
                <?php endif; ?>
            </colgroup>
            <thead>
            <tr>
                <th>Name</th>
                <th>Description</th>
                <?php if ($authController->can('categories.manage')): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td class="category-name"><?= e($category['name']); ?></td>
                    <td><?= e((string) $category['description']); ?></td>
                    <?php if ($authController->can('categories.manage')): ?>
                        <td class="category-actions">
                            <div class="action-group">
                            <form method="post" action="<?= e(basePath('index.php?page=categories')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?= e((string) $category['id']); ?>">
                                <button class="btn-action btn-delete" type="submit" title="Delete category">
                                    <span class="material-symbols-outlined">delete</span>
                                    <span class="btn-label">Delete</span>
                                </button>
                            </form>
                            </div>
                        </td>
                    <?php endif; ?>
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
