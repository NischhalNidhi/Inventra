<?php

declare(strict_types=1);

class Report
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createSale(array $data, int $userId, string $source = 'manual_entry'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sales_transactions
             (product_id, quantity, unit_price, sale_date, region, source, created_by)
             VALUES (:product_id, :quantity, :unit_price, :sale_date, :region, :source, :created_by)'
        );
        $stmt->execute([
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'sale_date' => $data['sale_date'],
            'region' => $data['region'],
            'source' => $source,
            'created_by' => $userId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getInventoryReport(?string $fromDate = null, ?string $toDate = null): array
    {
        $conditions = ['p.is_archived = 0'];
        $params = [];
        if ($fromDate) {
            $conditions[] = 'DATE(p.updated_at) >= :from_date';
            $params['from_date'] = $fromDate;
        }
        if ($toDate) {
            $conditions[] = 'DATE(p.updated_at) <= :to_date';
            $params['to_date'] = $toDate;
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.name, p.sku, p.unit_price, p.stock_quantity, p.min_threshold, c.name AS category_name, p.updated_at
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY p.name ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getInventorySummary(): array
    {
        $summary = $this->pdo->query(
            'SELECT COUNT(*) AS total_skus,
                    SUM(CASE WHEN stock_quantity <= min_threshold THEN 1 ELSE 0 END) AS low_stock_count,
                    SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock_count,
                    SUM(stock_quantity * unit_price) AS inventory_value
             FROM products
             WHERE is_archived = 0'
        )->fetch();

        return [
            'total_skus' => (int) ($summary['total_skus'] ?? 0),
            'low_stock_count' => (int) ($summary['low_stock_count'] ?? 0),
            'out_of_stock_count' => (int) ($summary['out_of_stock_count'] ?? 0),
            'inventory_value' => round((float) ($summary['inventory_value'] ?? 0), 2),
        ];
    }

    public function getMonthlySales(?string $fromDate = null, ?string $toDate = null): array
    {
        $conditions = [];
        $params = [];
        if ($fromDate) {
            $conditions[] = 'sale_date >= :from_date';
            $params['from_date'] = $fromDate;
        }
        if ($toDate) {
            $conditions[] = 'sale_date <= :to_date';
            $params['to_date'] = $toDate;
        }

        $sql = 'SELECT DATE_FORMAT(sale_date, "%Y-%m") AS month, SUM(quantity * unit_price) AS total
                FROM sales_transactions';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' GROUP BY DATE_FORMAT(sale_date, "%Y-%m") ORDER BY month ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDailySales(?string $fromDate = null, ?string $toDate = null): array
    {
        $conditions = [];
        $params = [];
        if ($fromDate) {
            $conditions[] = 'sale_date >= :from_date';
            $params['from_date'] = $fromDate;
        }
        if ($toDate) {
            $conditions[] = 'sale_date <= :to_date';
            $params['to_date'] = $toDate;
        }

        $sql = 'SELECT sale_date, SUM(quantity * unit_price) AS total
                FROM sales_transactions';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' GROUP BY sale_date ORDER BY sale_date ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve low stock products with optional category and date filtering.
     *
     * @param string|null $fromDate   Only include products updated on or after this date.
     * @param string|null $toDate     Only include products updated on or before this date.
     * @param int|null    $categoryId Only include products from this category.
     * @return array Low stock products with days below threshold metadata.
     */
    public function getLowStockReport(?string $fromDate = null, ?string $toDate = null, ?int $categoryId = null): array
    {
        $conditions = ['p.is_archived = 0', 'p.stock_quantity <= p.min_threshold'];
        $params = [];

        if ($categoryId !== null) {
            $conditions[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        if ($fromDate) {
            $conditions[] = 'DATE(p.updated_at) >= :low_from_date';
            $params['low_from_date'] = $fromDate;
        }
        if ($toDate) {
            $conditions[] = 'DATE(p.updated_at) <= :low_to_date';
            $params['low_to_date'] = $toDate;
        }

        // Determine how long the product has remained below threshold.
        // Prefer the most recent movement that crossed the threshold; otherwise fall back to first below-threshold movement or product update.
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.name, p.sku, p.stock_quantity, p.min_threshold, c.name AS category_name,
                    TIMESTAMPDIFF(DAY,
                        COALESCE(
                            (SELECT MAX(sm.created_at) FROM stock_movements sm
                             WHERE sm.product_id = p.id AND sm.previous_quantity > p.min_threshold AND sm.new_quantity <= p.min_threshold),
                            (SELECT MIN(sm.created_at) FROM stock_movements sm
                             WHERE sm.product_id = p.id AND sm.new_quantity <= p.min_threshold),
                            p.updated_at
                        ),
                        NOW()
                    ) AS days_below_threshold
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY (p.min_threshold - p.stock_quantity) DESC, p.name ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getStockMovementSummary(?string $fromDate = null, ?string $toDate = null): array
    {
        $conditions = [];
        $params = [];
        if ($fromDate) {
            $conditions[] = 'DATE(sm.created_at) >= :from_date';
            $params['from_date'] = $fromDate;
        }
        if ($toDate) {
            $conditions[] = 'DATE(sm.created_at) <= :to_date';
            $params['to_date'] = $toDate;
        }

        $sql = 'SELECT sm.movement_type, SUM(sm.quantity) AS total_quantity, COUNT(*) AS total_events
                FROM stock_movements sm';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' GROUP BY sm.movement_type ORDER BY sm.movement_type ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createImportBatch(string $fileName, string $fileType, string $status, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO report_import_batches (file_name, file_type, status, created_by)
             VALUES (:file_name, :file_type, :status, :created_by)'
        );
        $stmt->execute([
            'file_name' => $fileName,
            'file_type' => $fileType,
            'status' => $status,
            'created_by' => $userId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setImportBatchStats(int $batchId, int $importedRows, int $skippedRows, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE report_import_batches
             SET imported_rows = :imported_rows, skipped_rows = :skipped_rows, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $batchId,
            'imported_rows' => $importedRows,
            'skipped_rows' => $skippedRows,
            'status' => $status,
        ]);
    }

    public function addImportRowError(int $batchId, int $rowIndex, string $errorMessage): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO report_import_row_errors (batch_id, row_index, error_message)
             VALUES (:batch_id, :row_index, :error_message)'
        );
        $stmt->execute([
            'batch_id' => $batchId,
            'row_index' => $rowIndex,
            'error_message' => $errorMessage,
        ]);
    }

    public function getImportBatches(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.*, u.full_name
             FROM report_import_batches b
             INNER JOIN users u ON u.id = b.created_by
             ORDER BY b.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
