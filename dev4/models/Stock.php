<?php

declare(strict_types=1);

class Stock
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function adjustStock(
        int $productId,
        string $movementType,
        int $quantity,
        string $reason,
        int $userId,
        ?string $sourceRef = null
    ): array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('SELECT id, name, stock_quantity FROM products WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new RuntimeException('Product not found.');
            }

            $currentQuantity = (int) $product['stock_quantity'];
            $nextQuantity = $movementType === 'in'
                ? $currentQuantity + $quantity
                : $currentQuantity - $quantity;

            if ($nextQuantity < 0) {
                throw new RuntimeException('Stock out request would create negative inventory.');
            }

            $update = $this->pdo->prepare(
                'UPDATE products SET stock_quantity = :quantity, updated_by = :updated_by WHERE id = :id'
            );
            $update->execute([
                'quantity' => $nextQuantity,
                'updated_by' => $userId,
                'id' => $productId,
            ]);

            $history = $this->pdo->prepare(
                'INSERT INTO stock_movements
                 (product_id, user_id, movement_type, quantity, previous_quantity, new_quantity, reason, source_ref)
                 VALUES
                 (:product_id, :user_id, :movement_type, :quantity, :previous_quantity, :new_quantity, :reason, :source_ref)'
            );
            $history->execute([
                'product_id' => $productId,
                'user_id' => $userId,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'previous_quantity' => $currentQuantity,
                'new_quantity' => $nextQuantity,
                'reason' => $reason,
                'source_ref' => $sourceRef,
            ]);

            $this->pdo->commit();

            return [
                'product_name' => $product['name'],
                'previous_quantity' => $currentQuantity,
                'new_quantity' => $nextQuantity,
            ];
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function getRecentHistory(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sm.*, p.name AS product_name, p.sku, u.full_name
             FROM stock_movements sm
             INNER JOIN products p ON p.id = sm.product_id
             INNER JOIN users u ON u.id = sm.user_id
             ORDER BY sm.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getMovementSummary(?string $fromDate = null, ?string $toDate = null): array
    {
        $conditions = [];
        $params = [];

        if ($fromDate) {
            $conditions[] = 'DATE(created_at) >= :from_date';
            $params['from_date'] = $fromDate;
        }
        if ($toDate) {
            $conditions[] = 'DATE(created_at) <= :to_date';
            $params['to_date'] = $toDate;
        }

        $sql = 'SELECT movement_type, SUM(quantity) AS total_quantity, COUNT(*) AS movement_count
                FROM stock_movements';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' GROUP BY movement_type ORDER BY movement_type ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
