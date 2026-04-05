<?php require __DIR__ . '/../../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">STOCK MOVEMENT / LOG</p>
        <h1>Stock Control</h1>
        <p class="lead">Record inbound and outbound movements with protected quantity checks and historical traceability.</p>
    </div>
</header>

<section class="stock-layout">
    <form class="panel stock-form ajax-stock-form" method="post" action="<?= e(basePath('index.php?page=stock')); ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="adjust_stock">
        <div class="panel-header">
            <div>
                <h2>Movement Console</h2>
                <p>Use stock in or stock out. Negative inventory is blocked automatically.</p>
            </div>
        </div>
        <div class="form-grid">
            <label>
                <span>Product</span>
                <select name="product_id" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= e((string) $product['id']); ?>"><?= e($product['name'] . ' (' . $product['sku'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Movement Type</span>
                <select name="movement_type" required>
                    <option value="in">Stock In</option>
                    <option value="out">Stock Out</option>
                </select>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" name="quantity" min="1" required>
            </label>
            <label class="wide">
                <span>Reason</span>
                <input type="text" name="reason" placeholder="Purchase order, dispatch, adjustment..." required>
            </label>
        </div>
        <button class="button primary" type="submit">Process Movement</button>
        <p id="stock-feedback" class="feedback-text"></p>
    </form>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Recent Stock History</h2>
                <p>Latest logged movements across the inventory ledger.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Previous</th>
                        <th>New</th>
                        <th>Note</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody id="stock-history-body">
                <?php foreach ($history as $entry): ?>
                    <tr>
                        <td><?= e($entry['product_name']); ?></td>
                        <td><?= e(strtoupper($entry['movement_type'])); ?></td>
                        <td><?= e((string) $entry['quantity']); ?></td>
                        <td><?= e((string) $entry['previous_quantity']); ?></td>
                        <td><?= e((string) $entry['new_quantity']); ?></td>
                        <td><?= e($entry['reason'] ?? ''); ?></td>
                        <td><?= e($entry['full_name']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

 </div>
</main>
</div>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
</body>
</html>
