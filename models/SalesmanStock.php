<?php

declare(strict_types=1);

class SalesmanStock
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allocateStock(int $salesmanId, int $productId, int $quantity, int $createdBy, ?string $note = null): int
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be > 0');
        }

        $this->pdo->beginTransaction();
        try {
            // Reduce central product stock
            $stmt = $this->pdo->prepare('SELECT stock_quantity FROM products WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $productId]);
            $row = $stmt->fetch();
            $current = (int) ($row['stock_quantity'] ?? 0);
            if ($current < $quantity) {
                throw new RuntimeException('Insufficient central stock to allocate');
            }

            $prev = $current;
            $new = $current - $quantity;
            $upd = $this->pdo->prepare('UPDATE products SET stock_quantity = :new, updated_at = NOW() WHERE id = :id');
            $upd->execute(['new' => $new, 'id' => $productId]);

            // Create allocation
            $ins = $this->pdo->prepare('INSERT INTO salesman_stock_allocations (salesman_id, product_id, quantity_allocated, quantity_remaining, note, created_by) VALUES (:salesman_id, :product_id, :q, :q, :note, :created_by)');
            $ins->execute([
                'salesman_id' => $salesmanId,
                'product_id' => $productId,
                'q' => $quantity,
                'note' => $note,
                'created_by' => $createdBy,
            ]);
            $allocationId = (int) $this->pdo->lastInsertId();

            // Log central stock movement
            $mov = $this->pdo->prepare('INSERT INTO stock_movements (product_id, user_id, movement_type, quantity, previous_quantity, new_quantity, reason, source_ref) VALUES (:product_id, :user_id, :movement_type, :quantity, :previous_quantity, :new_quantity, :reason, :source_ref)');
            $mov->execute([
                'product_id' => $productId,
                'user_id' => $createdBy,
                'movement_type' => 'out',
                'quantity' => $quantity,
                'previous_quantity' => $prev,
                'new_quantity' => $new,
                'reason' => 'Allocation to salesman id ' . $salesmanId,
                'source_ref' => 'allocation:' . $allocationId,
            ]);

            // Log allocation movement
            $sm = $this->pdo->prepare('INSERT INTO salesman_stock_movements (allocation_id, product_id, user_id, movement_type, quantity, previous_allocation, new_allocation, reason) VALUES (:allocation_id, :product_id, :user_id, :movement_type, :quantity, :previous_allocation, :new_allocation, :reason)');
            $sm->execute([
                'allocation_id' => $allocationId,
                'product_id' => $productId,
                'user_id' => $createdBy,
                'movement_type' => 'allocate',
                'quantity' => $quantity,
                'previous_allocation' => 0,
                'new_allocation' => $quantity,
                'reason' => $note,
            ]);

            $this->pdo->commit();
            return $allocationId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listAllocationsForSalesman(int $salesmanId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.*, p.name AS product_name, p.sku FROM salesman_stock_allocations a JOIN products p ON p.id = a.product_id WHERE a.salesman_id = :sid ORDER BY a.created_at DESC');
        $stmt->execute(['sid' => $salesmanId]);
        return $stmt->fetchAll();
    }

    public function reduceAllocation(int $allocationId, int $quantity, int $userId, ?string $reason = null): void
    {
        if ($quantity <= 0) throw new InvalidArgumentException('Quantity must be > 0');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM salesman_stock_allocations WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $allocationId]);
            $alloc = $stmt->fetch();
            if (!$alloc) throw new RuntimeException('Allocation not found');

            $remaining = (int) $alloc['quantity_remaining'];
            if ($remaining < $quantity) throw new RuntimeException('Not enough allocation remaining');

            $prevAlloc = $remaining;
            $newAlloc = $remaining - $quantity;

            $upd = $this->pdo->prepare('UPDATE salesman_stock_allocations SET quantity_remaining = :new WHERE id = :id');
            $upd->execute(['new' => $newAlloc, 'id' => $allocationId]);

            $sm = $this->pdo->prepare('INSERT INTO salesman_stock_movements (allocation_id, product_id, user_id, movement_type, quantity, previous_allocation, new_allocation, reason) VALUES (:allocation_id, :product_id, :user_id, :movement_type, :quantity, :previous_allocation, :new_allocation, :reason)');
            $sm->execute([
                'allocation_id' => $allocationId,
                'product_id' => $alloc['product_id'],
                'user_id' => $userId,
                'movement_type' => 'sale',
                'quantity' => $quantity,
                'previous_allocation' => $prevAlloc,
                'new_allocation' => $newAlloc,
                'reason' => $reason,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getAllocationById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT a.*, p.name AS product_name FROM salesman_stock_allocations a JOIN products p ON p.id = a.product_id WHERE a.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
