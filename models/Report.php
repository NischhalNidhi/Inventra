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

    public function getCurrentMonthSalesInsightData(): array
    {
        $startDate = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $endDate = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');

        $summaryStmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantity * unit_price), 0) AS total_revenue,
                    COALESCE(SUM(quantity), 0) AS units_sold,
                    COUNT(*) AS transaction_count,
                    COALESCE(AVG(quantity * unit_price), 0) AS average_order_value
             FROM sales_transactions
             WHERE sale_date BETWEEN :start_date AND :end_date'
        );
        $summaryStmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $summary = $summaryStmt->fetch() ?: [];

        $dailyStmt = $this->pdo->prepare(
            'SELECT sale_date, SUM(quantity * unit_price) AS total_revenue
             FROM sales_transactions
             WHERE sale_date BETWEEN :start_date AND :end_date
             GROUP BY sale_date
             ORDER BY sale_date ASC'
        );
        $dailyStmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $productStmt = $this->pdo->prepare(
            'SELECT p.name,
                    SUM(st.quantity) AS units_sold,
                    SUM(st.quantity * st.unit_price) AS total_revenue
             FROM sales_transactions st
             INNER JOIN products p ON p.id = st.product_id
             WHERE st.sale_date BETWEEN :start_date AND :end_date
             GROUP BY st.product_id, p.name
             ORDER BY total_revenue DESC, units_sold DESC, p.name ASC
             LIMIT 5'
        );
        $productStmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'label' => (new DateTimeImmutable($startDate))->format('F Y'),
            ],
            'summary' => [
                'total_revenue' => round((float) ($summary['total_revenue'] ?? 0), 2),
                'units_sold' => (int) ($summary['units_sold'] ?? 0),
                'transaction_count' => (int) ($summary['transaction_count'] ?? 0),
                'average_order_value' => round((float) ($summary['average_order_value'] ?? 0), 2),
            ],
            'daily_sales' => array_map(static function (array $row): array {
                return [
                    'sale_date' => (string) $row['sale_date'],
                    'total_revenue' => round((float) ($row['total_revenue'] ?? 0), 2),
                ];
            }, $dailyStmt->fetchAll()),
            'top_products' => array_map(static function (array $row): array {
                return [
                    'name' => (string) $row['name'],
                    'units_sold' => (int) ($row['units_sold'] ?? 0),
                    'total_revenue' => round((float) ($row['total_revenue'] ?? 0), 2),
                ];
            }, $productStmt->fetchAll()),
        ];
    }

    public function getAdvancedSalesInsightData(): array
    {
        $thisMonthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $thisMonthEnd = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');
        $prevMonthStart = (new DateTimeImmutable('first day of last month'))->format('Y-m-d');
        $prevMonthEnd = (new DateTimeImmutable('last day of last month'))->format('Y-m-d');

        // This Month Summary
        $stmt = $this->pdo->prepare('SELECT SUM(quantity * unit_price) as total_revenue, COUNT(*) as transaction_count FROM sales_transactions WHERE sale_date BETWEEN ? AND ?');
        $stmt->execute([$thisMonthStart, $thisMonthEnd]);
        $thisMonth = $stmt->fetch();

        // Prev Month Summary
        $stmt->execute([$prevMonthStart, $prevMonthEnd]);
        $prevMonth = $stmt->fetch();

        // Top Products
        $stmt = $this->pdo->prepare('SELECT p.name, SUM(st.quantity * st.unit_price) as total FROM sales_transactions st JOIN products p ON p.id = st.product_id WHERE st.sale_date BETWEEN ? AND ? GROUP BY p.id ORDER BY total DESC LIMIT 3');
        $stmt->execute([$thisMonthStart, $thisMonthEnd]);
        $topProducts = $stmt->fetchAll();

        // Low Products (sold at least once but least revenue)
        $stmt = $this->pdo->prepare('SELECT p.name, SUM(st.quantity * st.unit_price) as total FROM sales_transactions st JOIN products p ON p.id = st.product_id WHERE st.sale_date BETWEEN ? AND ? GROUP BY p.id ORDER BY total ASC LIMIT 3');
        $stmt->execute([$thisMonthStart, $thisMonthEnd]);
        $lowProducts = $stmt->fetchAll();

        // Category Breakdown
        $stmt = $this->pdo->prepare('SELECT c.name, SUM(st.quantity * st.unit_price) as total FROM sales_transactions st JOIN products p ON p.id = st.product_id JOIN categories c ON c.id = p.category_id WHERE st.sale_date BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC');
        $stmt->execute([$thisMonthStart, $thisMonthEnd]);
        $categories = $stmt->fetchAll();

        return [
            'summary' => [
                'total_revenue' => $thisMonth['total_revenue'] ?? 0,
                'transaction_count' => $thisMonth['transaction_count'] ?? 0,
                'prev_month_revenue' => $prevMonth['total_revenue'] ?? 0,
            ],
            'top_products' => $topProducts,
            'low_products' => $lowProducts,
            'category_breakdown' => $categories
        ];
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
     * Retrieve detailed sales transactions with product names for CSV export.
     *
     * @param string|null $fromDate Only include sales on or after this date.
     * @param string|null $toDate   Only include sales on or before this date.
     * @return array Sales transactions with product details.
     */
    public function getSalesTransactionsForExport(?string $fromDate = null, ?string $toDate = null): array
    {
        $conditions = [];
        $params = [];
        if ($fromDate) {
            $conditions[] = 'st.sale_date >= :from_date';
            $params['from_date'] = $fromDate;
        }
        if ($toDate) {
            $conditions[] = 'st.sale_date <= :to_date';
            $params['to_date'] = $toDate;
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = $this->pdo->prepare(
            'SELECT st.sale_date, p.name AS product_name, st.quantity, st.unit_price, (st.quantity * st.unit_price) AS total
             FROM sales_transactions st
             LEFT JOIN products p ON p.id = st.product_id
             ' . $whereSql . '
             ORDER BY st.sale_date DESC, st.id DESC'
        );
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
