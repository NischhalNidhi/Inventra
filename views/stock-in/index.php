<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">INVENTORY / STOCK MOVEMENT</p>
        <h1>Log Stock In</h1>
        <p class="lead">Record stock received from suppliers to keep inventory quantities accurate.</p>
    </div>
</header>

<section class="panel">
    <div class="panel-header">
        <h2>Stock In Form</h2>
    </div>
    <form class="form-grid" method="post" action="<?= e(basePath('index.php')); ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="stock_in">
        
        <label>
            <span>Product <span class="required">*</span></span>
            <select name="product_id" required>
                <option value="">Select a product...</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e((string) $product['id']); ?>" <?= selectedIf(old('product_id'), (string) $product['id']); ?>>
                        <?= e($product['name']); ?> (SKU: <?= e($product['sku']); ?>) - Stock: <?= e((string) $product['stock_quantity']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Quantity <span class="required">*</span></span>
            <input type="number" name="quantity" min="1" value="<?= e(old('quantity')); ?>" required placeholder="Enter quantity">
        </label>

        <label>
            <span>Date</span>
            <input type="date" name="date" value="<?= e(old('date') ?: date('Y-m-d')); ?>">
        </label>

        <label>
            <span>Reason</span>
            <textarea name="reason" rows="3" placeholder="Optional: Reason for stock in (e.g., supplier delivery, returned items, etc.)"><?= e(old('reason')); ?></textarea>
        </label>

        <button class="button primary" type="submit">Record Stock In</button>
    </form>
</section>

</div>
</main>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>