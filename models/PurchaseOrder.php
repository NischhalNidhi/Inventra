<?php

declare(strict_types=1);

class PurchaseOrder
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function generatePONumber(): string
    {
        do {
            $poNumber = 'PO-' . date('Ymd') . '-' . random_int(1000, 9999);
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM purchase_orders WHERE po_number = :po_number');
            $stmt->execute(['po_number' => $poNumber]);
        } while ((int) $stmt->fetchColumn() > 0);

        return $poNumber;
    }

    public function getAll(?string $status = null): array
    {
        $sql = 'SELECT po.*, s.name AS supplier_name
                FROM purchase_orders po
                INNER JOIN suppliers s ON s.id = po.supplier_id';
        $params = [];
        if ($status) {
            $sql .= ' WHERE po.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY po.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT po.*, s.name AS supplier_name
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $po = $stmt->fetch();
        if (!$po) {
            return null;
        }

        $lineStmt = $this->pdo->prepare(
            'SELECT li.*, p.name AS product_name, p.sku
             FROM po_line_items li
             INNER JOIN products p ON p.id = li.product_id
             WHERE li.po_id = :po_id'
        );
        $lineStmt->execute(['po_id' => $id]);
        $po['line_items'] = $lineStmt->fetchAll();

        return $po;
    }

    public function create(int $supplierId, array $lineItems, int $userId, ?string $expectedDate): int
    {
        $this->pdo->beginTransaction();

        try {
            $poNumber = $this->generatePONumber();
            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_orders (po_number, supplier_id, status, expected_date, created_by)
                 VALUES (:po_number, :supplier_id, "pending", :expected_date, :created_by)'
            );
            $stmt->execute([
                'po_number' => $poNumber,
                'supplier_id' => $supplierId,
                'expected_date' => $expectedDate ?: null,
                'created_by' => $userId,
            ]);

            $poId = (int) $this->pdo->lastInsertId();
            $lineStmt = $this->pdo->prepare(
                'INSERT INTO po_line_items (po_id, product_id, quantity_ordered)
                 VALUES (:po_id, :product_id, :quantity_ordered)'
            );

            foreach ($lineItems as $line) {
                $lineStmt->execute([
                    'po_id' => $poId,
                    'product_id' => $line['product_id'],
                    'quantity_ordered' => $line['quantity_ordered'],
                ]);
            }

            $this->pdo->commit();
            return $poId;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function updateTracking(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare('SELECT status FROM purchase_orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $status = $stmt->fetchColumn();

        if ($status === false) {
            throw new RuntimeException('Purchase order not found.');
        }
        if ($status === 'received') {
            throw new RuntimeException('Received purchase order is locked.');
        }

        $update = $this->pdo->prepare(
            'UPDATE purchase_orders
             SET carrier_name = :carrier_name,
                 tracking_number = :tracking_number,
                 dispatch_date = :dispatch_date,
                 expected_arrival = :expected_arrival,
                 shipment_status = :shipment_status,
                 status_updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'id' => $id,
            'carrier_name' => $data['carrier_name'],
            'tracking_number' => $data['tracking_number'],
            'dispatch_date' => $data['dispatch_date'],
            'expected_arrival' => $data['expected_arrival'],
            'shipment_status' => $data['shipment_status'],
        ]);
    }

    public function receive(int $id, array $quantitiesReceivedByLineId, int $userId): void
    {
        $this->pdo->beginTransaction();

        try {
            $poStmt = $this->pdo->prepare('SELECT * FROM purchase_orders WHERE id = :id FOR UPDATE');
            $poStmt->execute(['id' => $id]);
            $po = $poStmt->fetch();

            if (!$po) {
                throw new RuntimeException('Purchase order not found.');
            }
            if ($po['status'] === 'received') {
                throw new RuntimeException('Purchase order already received.');
            }

            $lineStmt = $this->pdo->prepare('SELECT * FROM po_line_items WHERE po_id = :po_id FOR UPDATE');
            $lineStmt->execute(['po_id' => $id]);
            $lines = $lineStmt->fetchAll();

            $updateProduct = $this->pdo->prepare(
                'UPDATE products SET stock_quantity = stock_quantity + :received_qty, updated_by = :updated_by WHERE id = :product_id'
            );
            $updateLine = $this->pdo->prepare(
                'UPDATE po_line_items SET quantity_received = :quantity_received WHERE id = :line_id'
            );
            $insertDelivery = $this->pdo->prepare(
                'INSERT INTO delivery_logs (po_id, supplier_id, product_id, quantity_ordered, quantity_received, received_by)
                 VALUES (:po_id, :supplier_id, :product_id, :quantity_ordered, :quantity_received, :received_by)'
            );
            $insertMovement = $this->pdo->prepare(
                'INSERT INTO stock_movements
                 (product_id, user_id, movement_type, quantity, previous_quantity, new_quantity, reason, source_ref)
                 VALUES (:product_id, :user_id, "in", :quantity, :previous_quantity, :new_quantity, :reason, :source_ref)'
            );
            $productStmt = $this->pdo->prepare('SELECT stock_quantity FROM products WHERE id = :id FOR UPDATE');

            foreach ($lines as $line) {
                $receivedQty = (int) ($quantitiesReceivedByLineId[$line['id']] ?? $line['quantity_ordered']);
                if ($receivedQty < 0) {
                    throw new RuntimeException('Received quantity cannot be negative.');
                }

                $productStmt->execute(['id' => $line['product_id']]);
                $previous = (int) $productStmt->fetchColumn();
                $next = $previous + $receivedQty;

                $updateProduct->execute([
                    'received_qty' => $receivedQty,
                    'updated_by' => $userId,
                    'product_id' => $line['product_id'],
                ]);
                $updateLine->execute([
                    'quantity_received' => $receivedQty,
                    'line_id' => $line['id'],
                ]);
                $insertDelivery->execute([
                    'po_id' => $id,
                    'supplier_id' => $po['supplier_id'],
                    'product_id' => $line['product_id'],
                    'quantity_ordered' => $line['quantity_ordered'],
                    'quantity_received' => $receivedQty,
                    'received_by' => $userId,
                ]);
                $insertMovement->execute([
                    'product_id' => $line['product_id'],
                    'user_id' => $userId,
                    'quantity' => $receivedQty,
                    'previous_quantity' => $previous,
                    'new_quantity' => $next,
                    'reason' => 'PO received',
                    'source_ref' => (string) $po['po_number'],
                ]);
            }

            $updatePo = $this->pdo->prepare(
                'UPDATE purchase_orders
                 SET status = "received",
                     shipment_status = "delivered",
                     status_updated_at = NOW()
                 WHERE id = :id'
            );
            $updatePo->execute(['id' => $id]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function getDeliveryLog(?string $fromDate = null, ?string $toDate = null): array
    {
        $conditions = [];
        $params = [];
        if ($fromDate) {
            $conditions[] = 'DATE(dl.date_received) >= :from_date';
            $params['from_date'] = $fromDate;
        }
        if ($toDate) {
            $conditions[] = 'DATE(dl.date_received) <= :to_date';
            $params['to_date'] = $toDate;
        }

        $sql = 'SELECT dl.*, po.po_number, p.name AS product_name, s.name AS supplier_name, u.full_name
                FROM delivery_logs dl
                INNER JOIN purchase_orders po ON po.id = dl.po_id
                INNER JOIN products p ON p.id = dl.product_id
                INNER JOIN suppliers s ON s.id = dl.supplier_id
                INNER JOIN users u ON u.id = dl.received_by';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY dl.date_received DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
