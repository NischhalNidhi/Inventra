<?php

declare(strict_types=1);

class Stock
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getProductOptions(): array
    {
        return $this->pdo->query(
            'SELECT id, name, sku, stock_quantity FROM products WHERE is_archived = 0 ORDER BY name ASC'
        )->fetchAll();
    }

    public function getStockLevel(int $productId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT stock_quantity FROM products WHERE id = :id'
        );
        $stmt->execute(['id' => $productId]);
        $result = $stmt->fetch();
        return $result ? (int) $result['stock_quantity'] : 0;
    }

    public function processStockIn(int $productId, int $quantity, string $reason, int $userId): array
    {
        if ($quantity <= 0) {
            return ['success' => false, 'errors' => ['Quantity must be greater than zero.']];
        }

        try {
            $this->pdo->beginTransaction();

            // Lock the row first
            $stockStmt = $this->pdo->prepare(
                'SELECT stock_quantity FROM products WHERE id = :id FOR UPDATE'
            );
            $stockStmt->execute(['id' => $productId]);
            $previous = (int) $stockStmt->fetchColumn();
            $next = $previous + $quantity;

            $stmt = $this->pdo->prepare('UPDATE products SET stock_quantity = :next WHERE id = :id');
            $stmt->execute(['next' => $next, 'id' => $productId]);

            $movementStmt = $this->pdo->prepare(
                'INSERT INTO stock_movements (product_id, user_id, movement_type, quantity, previous_quantity, new_quantity, reason)
                 VALUES (:product_id, :user_id, "in", :quantity, :previous_quantity, :new_quantity, :reason)'
            );
            $movementStmt->execute([
                'product_id' => $productId,
                'user_id' => $userId,
                'quantity' => $quantity,
                'previous_quantity' => $previous,
                'new_quantity' => $next,
                'reason' => $reason !== '' ? $reason : null,
            ]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Stock added successfully.'];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'errors' => ['Failed to add stock.']];
        }
    }

    public function processStockOut(int $productId, int $quantity, string $reason, int $userId): array
    {
        $errors = [];

        if ($quantity <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }

        $currentStock = $this->getStockLevel($productId);
        if ($quantity > $currentStock) {
            $errors[] = sprintf('Insufficient stock. Available: %d, Requested: %d.', $currentStock, $quantity);
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $this->pdo->beginTransaction();

            // Lock row and check final stock inside transaction
            $stockStmt = $this->pdo->prepare(
                'SELECT stock_quantity FROM products WHERE id = :id FOR UPDATE'
            );
            $stockStmt->execute(['id' => $productId]);
            $previous = (int) $stockStmt->fetchColumn();
            
            if ($quantity > $previous) {
                throw new RuntimeException("Insufficient stock available.");
            }
            $next = $previous - $quantity;

            $stmt = $this->pdo->prepare('UPDATE products SET stock_quantity = :next WHERE id = :id');
            $stmt->execute(['next' => $next, 'id' => $productId]);

            $movementStmt = $this->pdo->prepare(
                'INSERT INTO stock_movements (product_id, user_id, movement_type, quantity, previous_quantity, new_quantity, reason)
                 VALUES (:product_id, :user_id, "out", :quantity, :previous_quantity, :new_quantity, :reason)'
            );
            $movementStmt->execute([
                'product_id' => $productId,
                'user_id' => $userId,
                'quantity' => $quantity,
                'previous_quantity' => $previous,
                'new_quantity' => $next,
                'reason' => $reason !== '' ? $reason : null,
            ]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Stock out recorded successfully.'];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'errors' => ['Failed to record stock out: ' . $e->getMessage()]];
        }
    }
}