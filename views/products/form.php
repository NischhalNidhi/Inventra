<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar split">
    <div>
        <p class="eyebrow">SYSTEM COMMAND / INVENTORY</p>
        <h1>New<br>Entry</h1>
        <p class="lead">Architectural ledger intake for precision inventory assets.</p>
    </div>
    <div class="timestamp-box">
        <span>Entry Timestamp</span>
        <strong><?= e(gmdate('Y-m-d\TH:i:s\Z')); ?></strong>
    </div>
</header>

<?php if ($errors): ?>
    <section class="form-errors">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error); ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<form class="entry-grid" method="post" enctype="multipart/form-data" action="<?= e(basePath('index.php?page=new-entry' . ($editingProduct ? '&id=' . $editingProduct['id'] : ''))); ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
    <input type="hidden" name="action" value="<?= $editingProduct ? 'update_product' : 'create_product'; ?>">
    <input type="hidden" name="image_name" value="<?= e((string) ($editingProduct['image_name'] ?? '')); ?>">
    <?php if ($editingProduct): ?>
        <input type="hidden" name="product_id" value="<?= e((string) $editingProduct['id']); ?>">
    <?php endif; ?>

    <section class="entry-card identity-card">
        <div class="section-title">Product Identity</div>
        <label>
            <span>Nominal Designation</span>
            <input type="text" name="name" placeholder="e.g. Industrial Turbine G-400" value="<?= $editingProduct ? e($editingProduct['name']) : old('name'); ?>" required>
        </label>
        <div class="inline-fields">
            <label>
                <span>Internal SKU</span>
                <input type="text" name="sku" placeholder="INV-000-0000" value="<?= $editingProduct ? e($editingProduct['sku']) : old('sku'); ?>" required>
            </label>
            <label>
                <span>Category</span>
                <select name="category_id" required>
                    <option value="">Select Category</option>
                    <?php $currentCategoryId = (string) ($editingProduct['category_id'] ?? ($_SESSION['old']['category_id'] ?? '')); ?>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']); ?>" <?= selectedIf($currentCategoryId, (string) $category['id']); ?>>
                            <?= e($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>
            <span>Description</span>
            <textarea name="description" rows="4"><?= $editingProduct ? e((string) $editingProduct['description']) : old('description'); ?></textarea>
        </label>
        <div class="inline-fields">
            <label>
                <span>Supplier</span>
                <select name="supplier_id">
                    <option value="">Unassigned Supplier</option>
                    <?php $currentSupplierId = (string) ($editingProduct['supplier_id'] ?? ($_SESSION['old']['supplier_id'] ?? '')); ?>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= e((string) $supplier['id']); ?>" <?= selectedIf($currentSupplierId, (string) $supplier['id']); ?>>
                            <?= e($supplier['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Unit Price (NPR)</span>
                <input type="number" name="unit_price" min="0" step="0.01" value="<?= $editingProduct ? e((string) $editingProduct['unit_price']) : old('unit_price', '0.00'); ?>" required>
            </label>
        </div>
    </section>

    <section class="entry-card upload-card">
        <div class="section-title">Asset Visuals</div>
        <label class="upload-zone">
            <input type="file" name="product_image" accept=".jpg,.jpeg,.png,.webp">
            <div>
                <strong>Drag technical schematic or upload asset photo</strong>
                <span>Supported: JPG / PNG / WEBP up to 2MB</span>
                <?php if ($editingProduct && !empty($editingProduct['image_name'])): ?>
                    <span>Current file: <?= e($editingProduct['image_name']); ?></span>
                <?php endif; ?>
            </div>
        </label>
        <label>
            <span>Current Asset Image</span>
            <input type="text" value="<?= e((string) ($editingProduct['image_name'] ?? 'No file uploaded')); ?>" readonly>
        </label>
    </section>

    <section class="entry-card stock-card">
        <div class="section-title">Stock Parameters</div>
        <div class="stock-controls">
            <label>
                <span>Initial Count</span>
                <div class="stepper">
                    <button type="button" class="stepper-btn" data-stepper-target="stock_quantity" data-step="-1">-</button>
                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?= $editingProduct ? e((string) $editingProduct['stock_quantity']) : old('stock_quantity', '0'); ?>" required>
                    <button type="button" class="stepper-btn" data-stepper-target="stock_quantity" data-step="1">+</button>
                </div>
            </label>
            <label>
                <span>Safety Threshold (Min)</span>
                <input type="number" name="min_threshold" min="0" value="<?= $editingProduct ? e((string) $editingProduct['min_threshold']) : old('min_threshold', '0'); ?>" required>
            </label>
        </div>
    </section>

    <aside class="entry-card action-card">
        <p class="section-title inverse">Transaction Ready</p>
        <h2><?= $editingProduct ? 'Update Inventory Record' : 'Commit to Inventory'; ?></h2>
        <p>Verify all parameters before committing this asset to the global ledger.</p>
        <button class="button wide light" type="submit"><?= $editingProduct ? 'Save Product Update' : 'Commit to Inventory'; ?></button>
    </aside>
</form>

 </div>
</main>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>
