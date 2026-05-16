<?php
<<<<<<< HEAD
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
=======
$isLow = (int) $product['stock_quantity'] <= (int) $product['min_threshold'];
$icon = $isLow ? 'warning' : 'inventory_2';
>>>>>>> 51334e0fd1318d84db20c218ddca5f579f213080
?>
<tr data-product-id="<?= e((string) $product['id']); ?>">
    <td>
        <div style="display:flex;align-items:center;gap:14px;">
            <button type="button"
                    class="media-thumb-button"
                    data-image-trigger
                    data-image-src="<?= e(mediaUrl(!empty($product['image_name']) ? 'products/' . $product['image_name'] : null, (string) $product['name'], 'product')); ?>"
                    data-image-title="<?= e($product['name']); ?>">
                <img src="<?= e(mediaUrl(!empty($product['image_name']) ? 'products/' . $product['image_name'] : null, (string) $product['name'], 'product')); ?>" alt="<?= e($product['name']); ?>" class="media-thumb media-thumb-product">
            </button>
            <div>
                <div style="font-weight:700;"><?= e($product['name']); ?></div>
                <div style="font-size:0.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;">
                    <?= e(($product['category_name'] ?? 'Unassigned') . ' / ' . ($product['supplier_name'] ?? 'No Supplier')); ?>
                </div>
            </div>
        </div>
    </td>
<<<<<<< HEAD
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
=======
    <td style="font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;font-size:0.8rem;"><?= e($product['sku']); ?></td>
    <td style="font-weight:800;"><?= e((string) $product['stock_quantity']); ?></td>
    <td style="text-align:right;font-weight:600;">NPR <?= e(number_format((float) $product['unit_price'], 2)); ?></td>
    <td>
        <span class="badge <?= $isLow ? 'low' : 'healthy'; ?>">
            <?= $isLow ? 'Low Stock' : 'In Stock'; ?>
>>>>>>> 51334e0fd1318d84db20c218ddca5f579f213080
        </span>
    </td>
    <td><div class="action-group">
        <?php if ($authController->can('products.edit')): ?>
            <a class="button small ghost" href="<?= e(basePath('index.php?page=new-entry&id=' . $product['id'])); ?>">Edit</a>
        <?php endif; ?>
        <?php if ($authController->can('products.archive') && (int) $product['is_archived'] === 0): ?>
            <form method="post" action="<?= e(basePath('index.php?page=products')); ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                <input type="hidden" name="action" value="archive_product">
                <input type="hidden" name="product_id" value="<?= e((string) $product['id']); ?>">
                <button class="button small ghost" type="submit">Archive</button>
            </form>
        <?php endif; ?>
        <?php if ($authController->can('products.delete')): ?>
            <form method="post" action="<?= e(basePath('index.php?page=products')); ?>" onsubmit="return confirm('Delete this product?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="product_id" value="<?= e((string) $product['id']); ?>">
                <button class="button small danger-outline" type="submit">Delete</button>
            </form>
        <?php endif; ?>
    </div></td>
</tr>

