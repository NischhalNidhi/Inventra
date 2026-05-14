<?php
$qty = (int) $product['stock_quantity'];
$threshold = (int) $product['min_threshold'];
$isOutOfStock = $qty === 0;
$isLow = !$isOutOfStock && $qty <= $threshold;

if ($isOutOfStock) {
    $badgeClass = 'out';
    $badgeText  = 'Out of Stock';
    $badgeIcon  = 'error';
} elseif ($isLow) {
    $badgeClass = 'low';
    $badgeText  = 'Low Stock';
    $badgeIcon  = 'warning';
} else {
    $badgeClass = 'healthy';
    $badgeText  = 'In Stock';
    $badgeIcon  = 'check_circle';
}

$stockPct = $threshold > 0
    ? min(round(($qty / max($threshold, 1)) * 100), 100)
    : ($qty > 0 ? 100 : 0);

$imgFile  = !empty($product['image_name']) ? basename((string) $product['image_name']) : null;
$imgDisk  = $imgFile ? dirname(__DIR__, 2) . '/public/uploads/products/' . $imgFile : null;
$hasImage = $imgDisk !== null && is_file($imgDisk);
?>
<tr class="product-row" data-product-id="<?= e((string) $product['id']); ?>">
    <td>
        <div class="product-cell-main">
            <div class="product-cell-image">
                <?php if ($hasImage): ?>
                    <img src="<?= e(basePath('uploads/products/' . $imgFile)); ?>"
                         alt="<?= e($product['name']); ?>"
                         width="48" height="48">
                <?php else: ?>
                    <span class="material-symbols-outlined product-placeholder-icon icon-muted">
                        inventory_2
                    </span>
                <?php endif; ?>
            </div>
            <div class="product-cell-info">
                <div class="product-cell-name"><?= e($product['name']); ?></div>
                <div class="product-cell-meta">
                    <?= e(($product['category_name'] ?? 'Unassigned') . ' / ' . ($product['supplier_name'] ?? 'No Supplier')); ?>
                </div>
            </div>
        </div>
    </td>
    <td class="td-sku"><?= e($product['sku']); ?></td>
    <td class="td-qty">
        <div class="stock-qty-cell <?= $badgeClass ?>">
            <span class="stock-qty-value"><?= e((string) $qty); ?></span>
            <div class="stock-qty-bar">
                <div class="stock-qty-fill <?= $badgeClass ?>" style="width: <?= $stockPct ?>%"></div>
            </div>
        </div>
    </td>
    <td class="td-price">NPR <?= e(number_format((float) $product['unit_price'], 2)); ?></td>
    <td>
        <span class="badge <?= $badgeClass ?>">
            <span class="material-symbols-outlined badge-icon"><?= $badgeIcon; ?></span>
            <?= $badgeText; ?>
        </span>
    </td>
    <td class="td-actions">
        <div class="action-group">
            <?php if ($authController->can('products.edit')): ?>
                <a class="btn-action btn-edit"
                   href="<?= e(basePath('index.php?page=new-entry&id=' . $product['id'])); ?>"
                   title="Edit product">
                    <span class="material-symbols-outlined">edit</span>
                    <span class="btn-label">Edit</span>
                </a>
            <?php endif; ?>

            <?php if ($authController->can('products.archive') && (int) $product['is_archived'] === 0): ?>
                <form method="post" action="<?= e(basePath('index.php?page=products')); ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                    <input type="hidden" name="action"     value="archive_product">
                    <input type="hidden" name="product_id" value="<?= e((string) $product['id']); ?>">
                    <button class="btn-action btn-archive" type="submit" title="Archive product">
                        <span class="material-symbols-outlined">archive</span>
                        <span class="btn-label">Archive</span>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($authController->can('products.delete')): ?>
                <form method="post"
                      action="<?= e(basePath('index.php?page=products')); ?>"
                      onsubmit="return confirm('Permanently delete this product?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                    <input type="hidden" name="action"     value="delete_product">
                    <input type="hidden" name="product_id" value="<?= e((string) $product['id']); ?>">
                    <button class="btn-action btn-delete" type="submit" title="Delete product">
                        <span class="material-symbols-outlined">delete</span>
                        <span class="btn-label">Delete</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </td>

</tr>
