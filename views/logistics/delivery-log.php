<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">LOGISTICS / DELIVERY LOG</p>
        <h1>Delivery History</h1>
        <p class="lead">Audit all PO receipts and quantities delivered into inventory.</p>
    </div>
</header>

<section class="panel">
    <div class="panel-header">
        <h2>Filter</h2>
        <form method="get" action="<?= e(basePath('index.php')); ?>" class="form-grid">
            <input type="hidden" name="page" value="delivery-log">
            <label><span>From</span><input type="date" name="from_date" value="<?= e($_GET['from_date'] ?? ''); ?>"></label>
            <label><span>To</span><input type="date" name="to_date" value="<?= e($_GET['to_date'] ?? ''); ?>"></label>
            <button class="button ghost" type="submit">Apply</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>PO</th><th>Supplier</th><th>Product</th><th>Ordered</th><th>Received</th><th>By</th></tr></thead>
            <tbody>
            <?php foreach ($deliveryLogs as $log): ?>
                <tr>
                    <td><?= e($log['date_received']); ?></td>
                    <td><?= e($log['po_number']); ?></td>
                    <td><?= e($log['supplier_name']); ?></td>
                    <td><?= e($log['product_name']); ?></td>
                    <td><?= e((string) $log['quantity_ordered']); ?></td>
                    <td><?= e((string) $log['quantity_received']); ?></td>
                    <td><?= e($log['full_name']); ?></td>
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
