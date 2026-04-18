<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">LOGISTICS / REORDER</p>
        <h1>Reorder Suggestions</h1>
        <p class="lead">Products below threshold requiring procurement action.</p>
    </div>
</header>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Product</th><th>SKU</th><th>Current</th><th>Min Threshold</th><th>Gap</th></tr></thead>
            <tbody>
            <?php foreach ($lowStockProducts as $product): ?>
                <tr>
                    <td><?= e($product['name']); ?></td>
                    <td><?= e($product['sku']); ?></td>
                    <td><?= e((string) $product['stock_quantity']); ?></td>
                    <td><?= e((string) $product['min_threshold']); ?></td>
                    <td><?= e((string) ((int) $product['min_threshold'] - (int) $product['stock_quantity'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

 </div>
</main>
</div>
<script src="<?= e(assetPath('js/app.js')); ?>"></script>
</body>
</html>
