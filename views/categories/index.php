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
    <form class="form-grid" method="post" action="<?= e(basePath('index.php?page=categories')); ?>">
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
        <table>
            <thead><tr><th>Name</th><th>Description</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= e($category['name']); ?></td>
                    <td><?= e((string) $category['description']); ?></td>
                    <td class="action-group">
                        <?php if ($authController->can('categories.manage')): ?>
                            <form method="post" action="<?= e(basePath('index.php?page=categories')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?= e((string) $category['id']); ?>">
                                <button class="button small danger-outline" type="submit">Delete</button>
                            </form>
                        <?php endif; ?>
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
