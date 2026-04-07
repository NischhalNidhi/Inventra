<<<<<<< HEAD
<?php require __DIR__ . '/../../includes/header.php'; ?>
=======
<?php require __DIR__ . '/../../core/layout/header.php'; ?>
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508

<header class="topbar">
    <div>
        <p class="eyebrow">LOGISTICS / PURCHASE ORDERS</p>
        <h1>PO Tracker</h1>
        <p class="lead">Create purchase orders, monitor shipment details, and receive deliveries.</p>
    </div>
</header>

<?php if ($authController->can('po.create')): ?>
<section class="panel">
    <div class="panel-header"><h2>Create Purchase Order</h2></div>
    <form class="form-grid" method="post" action="<?= e(basePath('index.php?page=purchase-orders')); ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="create_purchase_order">
        <label>
            <span>Supplier</span>
            <select name="supplier_id" required>
                <option value="">Select Supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= e((string) $supplier['id']); ?>"><?= e($supplier['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Expected Date</span><input type="date" name="expected_date"></label>
        <label class="wide"><span>Line Item 1 Product</span>
            <select name="line_product_id[]" required>
                <option value="">Select Product</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e((string) $product['id']); ?>"><?= e($product['name'] . ' (' . $product['sku'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Line Item 1 Qty</span><input type="number" name="line_quantity[]" min="1" required></label>
        <label class="wide"><span>Line Item 2 Product (Optional)</span>
            <select name="line_product_id[]">
                <option value="">Select Product</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e((string) $product['id']); ?>"><?= e($product['name'] . ' (' . $product['sku'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Line Item 2 Qty</span><input type="number" name="line_quantity[]" min="1"></label>
        <button class="button primary" type="submit">Create PO</button>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><h2>Purchase Orders</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>PO Number</th><th>Supplier</th><th>Status</th><th>Shipment</th><th>Expected</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($purchaseOrders as $po): ?>
                <tr>
                    <td><?= e($po['po_number']); ?></td>
                    <td><?= e($po['supplier_name']); ?></td>
                    <td><?= e(strtoupper($po['status'])); ?></td>
                    <td><?= e(strtoupper((string) $po['shipment_status'])); ?></td>
                    <td><?= e((string) $po['expected_date']); ?></td>
                    <td class="action-group">
                        <a class="button small ghost" href="<?= e(basePath('index.php?page=purchase-orders&id=' . $po['id'])); ?>">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($selectedPo): ?>
<section class="panel">
    <div class="panel-header"><h2>PO Detail: <?= e($selectedPo['po_number']); ?></h2></div>
    <?php if ($authController->can('po.tracking') && $selectedPo['status'] !== 'received'): ?>
        <form class="form-grid" method="post" action="<?= e(basePath('index.php?page=purchase-orders&id=' . $selectedPo['id'])); ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="update_po_tracking">
            <input type="hidden" name="po_id" value="<?= e((string) $selectedPo['id']); ?>">
            <label><span>Carrier Name</span><input type="text" name="carrier_name" value="<?= e((string) $selectedPo['carrier_name']); ?>"></label>
            <label><span>Tracking Number</span><input type="text" name="tracking_number" value="<?= e((string) $selectedPo['tracking_number']); ?>"></label>
            <label><span>Dispatch Date</span><input type="date" name="dispatch_date" value="<?= e((string) $selectedPo['dispatch_date']); ?>"></label>
            <label><span>Expected Arrival</span><input type="date" name="expected_arrival" value="<?= e((string) $selectedPo['expected_arrival']); ?>"></label>
            <label>
                <span>Shipment Status</span>
                <select name="shipment_status">
                    <option value="order_placed" <?= selectedIf((string) $selectedPo['shipment_status'], 'order_placed'); ?>>Order Placed</option>
                    <option value="dispatched" <?= selectedIf((string) $selectedPo['shipment_status'], 'dispatched'); ?>>Dispatched</option>
                    <option value="in_transit" <?= selectedIf((string) $selectedPo['shipment_status'], 'in_transit'); ?>>In Transit</option>
                    <option value="delivered" <?= selectedIf((string) $selectedPo['shipment_status'], 'delivered'); ?>>Delivered</option>
                </select>
            </label>
            <button class="button primary" type="submit">Update Tracking</button>
        </form>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Product</th><th>Ordered</th><th>Received</th></tr></thead>
            <tbody>
            <?php foreach ($selectedPo['line_items'] as $line): ?>
                <tr>
                    <td><?= e($line['product_name'] . ' (' . $line['sku'] . ')'); ?></td>
                    <td><?= e((string) $line['quantity_ordered']); ?></td>
                    <td><?= e((string) ($line['quantity_received'] ?? 0)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($authController->can('po.receive') && $selectedPo['status'] !== 'received'): ?>
        <form class="form-grid" method="post" action="<?= e(basePath('index.php?page=purchase-orders&id=' . $selectedPo['id'])); ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="receive_po">
            <input type="hidden" name="po_id" value="<?= e((string) $selectedPo['id']); ?>">
            <?php foreach ($selectedPo['line_items'] as $line): ?>
                <input type="hidden" name="line_id[]" value="<?= e((string) $line['id']); ?>">
                <label>
                    <span><?= e($line['product_name']); ?> Received Qty</span>
                    <input type="number" name="line_received[]" min="0" value="<?= e((string) $line['quantity_ordered']); ?>">
                </label>
            <?php endforeach; ?>
            <button class="button primary" type="submit">Mark PO as Received</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

 </div>
</main>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>
