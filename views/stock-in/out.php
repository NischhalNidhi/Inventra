<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">INVENTORY ADJUSTMENT</p>
        <h1>Stock Out</h1>
        <p class="lead">Remove items from central inventory for damages, internal use, or shrinkage.</p>
    </div>
</header>

<section class="panel">
    <form method="post" action="<?= e(basePath('index.php')); ?>" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="stock_out">
        
        <label class="wide">
            <span>Product</span>
            <select name="product_id" required>
                <option value="">Select Product to Remove</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e((string) $product['id']); ?>" <?= selectedIf(old('product_id'), (string) $product['id']); ?>>
                        <?= e($product['name']); ?> (<?= e($product['sku']); ?>) — Current: <?= e((string) $product['stock_quantity']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label><span>Quantity to Remove</span><input type="number" name="quantity" min="1" value="<?= e(old('quantity')); ?>" required></label>
        <label><span>Date</span><input type="date" name="date" value="<?= e(old('date') ?: date('Y-m-d')); ?>"></label>
        <label class="wide"><span>Reason / Reference</span><input type="text" name="reason" value="<?= e(old('reason')); ?>" placeholder="e.g. Damage, Expired, Sample..."></label>

        <div class="form-actions wide">
            <button class="button primary" type="submit">Record Stock Out</button>
            <a href="<?= e(basePath('index.php?page=reports')); ?>" class="button ghost">Cancel</a>
        </div>
    </form>
</section>

</div>
</main>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>