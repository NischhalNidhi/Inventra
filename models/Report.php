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
             (invoice_id, branch_code, city, customer_type, customer_gender, product_id, quantity, unit_price, sale_date, sale_time, sold_at, region, payment_method, tax_amount, gross_total, cogs, gross_margin_percentage, gross_income, rating, source, created_by)
             VALUES (:invoice_id, :branch_code, :city, :customer_type, :customer_gender, :product_id, :quantity, :unit_price, :sale_date, :sale_time, :sold_at, :region, :payment_method, :tax_amount, :gross_total, :cogs, :gross_margin_percentage, :gross_income, :rating, :source, :created_by)'
        );
        $stmt->execute([
            'invoice_id' => $data['invoice_id'] ?? null,
            'branch_code' => $data['branch_code'] ?? null,
            'city' => $data['city'] ?? null,
            'customer_type' => $data['customer_type'] ?? null,
            'customer_gender' => $data['customer_gender'] ?? null,
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'sale_date' => $data['sale_date'],
            'sale_time' => $data['sale_time'] ?? null,
            'sold_at' => $data['sold_at'] ?? null,
            'region' => $data['region'],
            'payment_method' => $data['payment_method'] ?? null,
            'tax_amount' => $data['tax_amount'] ?? null,
            'gross_total' => $data['gross_total'] ?? null,
            'cogs' => $data['cogs'] ?? null,
            'gross_margin_percentage' => $data['gross_margin_percentage'] ?? null,
            'gross_income' => $data['gross_income'] ?? null,
            'rating' => $data['rating'] ?? null,
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
            'SELECT p.id, p.name, p.sku, p.unit_price, p.stock_quantity, p.min_threshold, c.name AS category_name, p.updated_at
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY p.name ASC'
        );
        $stmt->execute($params);

        return array_map(function (array $row): array {
            $stock = (int) ($row['stock_quantity'] ?? 0);
            $threshold = (int) ($row['min_threshold'] ?? 0);
            $status = 'healthy';
            if ($stock <= 0) {
                $status = 'out';
            } elseif ($stock <= $threshold) {
                $status = 'low';
            }

            $row['stock_status'] = $status;
            $row['inventory_value'] = round($stock * (float) ($row['unit_price'] ?? 0), 2);

            return $row;
        }, $stmt->fetchAll());
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
        [$whereSql, $params] = $this->buildSalesDateWhere($fromDate, $toDate);
        $stmt = $this->pdo->prepare(
            'SELECT DATE_FORMAT(sale_date, "%Y-%m") AS month, SUM(quantity * unit_price) AS total
             FROM sales_transactions ' . $whereSql . '
             GROUP BY DATE_FORMAT(sale_date, "%Y-%m")
             ORDER BY month ASC'
        );
        $stmt->execute($params);

        $sql = 'SELECT DATE_FORMAT(sale_date, "%Y-%m") AS month, SUM(quantity * unit_price) AS total, COUNT(*) AS transactions, SUM(quantity) AS units_sold
                FROM sales_transactions';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' GROUP BY DATE_FORMAT(sale_date, "%Y-%m") ORDER BY month ASC';

    public function getDailySales(?string $fromDate = null, ?string $toDate = null): array
    {
        [$whereSql, $params] = $this->buildSalesDateWhere($fromDate, $toDate);
        $stmt = $this->pdo->prepare(
            'SELECT sale_date, SUM(quantity * unit_price) AS total
             FROM sales_transactions ' . $whereSql . '
             GROUP BY sale_date
             ORDER BY sale_date ASC'
        );
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            return [
                'sale_date' => (string) ($row['sale_date'] ?? ''),
                'total' => round((float) ($row['total'] ?? 0), 2),
            ];
        }, $stmt->fetchAll());
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

    public function getAdvancedSalesInsightData(?string $fromDate = null, ?string $toDate = null): array
    {
        $period = $this->resolveInsightPeriod($fromDate, $toDate);
        $summaryStmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantity * unit_price), 0) AS total_revenue,
                    COUNT(*) AS transaction_count
             FROM sales_transactions
             WHERE sale_date BETWEEN :start_date AND :end_date'
        );
        $summaryStmt->execute([
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
        ]);
        $thisMonth = $summaryStmt->fetch() ?: [];

        $summaryStmt->execute([
            'start_date' => $period['previous_start_date'],
            'end_date' => $period['previous_end_date'],
        ]);
        $prevMonth = $summaryStmt->fetch() ?: [];

        $topStmt = $this->pdo->prepare(
            'SELECT p.name, SUM(st.quantity * st.unit_price) AS total
             FROM sales_transactions st
             JOIN products p ON p.id = st.product_id
             WHERE st.sale_date BETWEEN :start_date AND :end_date
             GROUP BY p.id, p.name
             ORDER BY total DESC
             LIMIT 3'
        );
        $topStmt->execute([
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
        ]);
        $topProducts = $topStmt->fetchAll();

        $lowStmt = $this->pdo->prepare(
            'SELECT p.name, SUM(st.quantity * st.unit_price) AS total
             FROM sales_transactions st
             JOIN products p ON p.id = st.product_id
             WHERE st.sale_date BETWEEN :start_date AND :end_date
             GROUP BY p.id, p.name
             ORDER BY total ASC
             LIMIT 3'
        );
        $lowStmt->execute([
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
        ]);
        $lowProducts = $lowStmt->fetchAll();

        $categoryStmt = $this->pdo->prepare(
            'SELECT COALESCE(c.name, "Unassigned") AS name, SUM(st.quantity * st.unit_price) AS total
             FROM sales_transactions st
             JOIN products p ON p.id = st.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE st.sale_date BETWEEN :start_date AND :end_date
             GROUP BY c.id, c.name
             ORDER BY total DESC'
        );
        $categoryStmt->execute([
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
        ]);

        return [
            'period' => [
                'start_date' => $thisMonthStart,
                'end_date' => $thisMonthEnd,
                'label' => $baseDate->format('F Y'),
            ],
            'summary' => [
                'total_revenue' => round((float) ($thisMonth['total_revenue'] ?? 0), 2),
                'transaction_count' => (int) ($thisMonth['transaction_count'] ?? 0),
                'prev_month_revenue' => round((float) ($prevMonth['total_revenue'] ?? 0), 2),
            ],
            'top_products' => array_map(static function (array $row): array {
                return [
                    'name' => (string) ($row['name'] ?? ''),
                    'total' => round((float) ($row['total'] ?? 0), 2),
                ];
            }, $topProducts),
            'low_products' => array_map(static function (array $row): array {
                return [
                    'name' => (string) ($row['name'] ?? ''),
                    'total' => round((float) ($row['total'] ?? 0), 2),
                ];
            }, $lowProducts),
            'category_breakdown' => array_map(static function (array $row): array {
                return [
                    'name' => (string) ($row['name'] ?? ''),
                    'total' => round((float) ($row['total'] ?? 0), 2),
                ];
            }, $categoryStmt->fetchAll()),
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

        $sql = 'SELECT sale_date, SUM(quantity * unit_price) AS total, COUNT(*) AS transactions, SUM(quantity) AS units_sold
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
        [$whereSql, $params] = $this->buildSalesDateWhere($fromDate, $toDate, 'st.sale_date');
        $stmt = $this->pdo->prepare(
            'SELECT st.sale_date, st.invoice_id, p.name AS product_name, c.name AS category_name, st.quantity, st.unit_price, (st.quantity * st.unit_price) AS total, st.payment_method, st.region
             FROM sales_transactions st
             LEFT JOIN products p ON p.id = st.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             ' . $whereSql . '
             ORDER BY st.sale_date DESC, st.id DESC'
        );
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            return [
                'sale_date' => (string) ($row['sale_date'] ?? ''),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'category_name' => (string) ($row['category_name'] ?? ''),
                'region' => (string) ($row['region'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
                'total' => round((float) ($row['total'] ?? 0), 2),
                'source' => (string) ($row['source'] ?? ''),
            ];
        }, $stmt->fetchAll());
    }

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

        return array_map(static function (array $row): array {
            $stock = (int) ($row['stock_quantity'] ?? 0);
            $threshold = (int) ($row['min_threshold'] ?? 0);
            $row['gap'] = max(0, $threshold - $stock);
            $row['severity'] = $threshold > 0 ? round(($row['gap'] / $threshold) * 100, 1) : 0.0;

            return $row;
        }, $stmt->fetchAll());
    }

    public function getStockMovementLog(?string $fromDate = null, ?string $toDate = null): array
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

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = $this->pdo->prepare(
            'SELECT sm.*, p.name AS product_name, p.sku, u.full_name
             FROM stock_movements sm
             INNER JOIN products p ON p.id = sm.product_id
             INNER JOIN users u ON u.id = sm.user_id
             ' . $whereSql . '
             ORDER BY sm.created_at DESC'
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

        return array_map(static function (array $row): array {
            return [
                'movement_type' => (string) ($row['movement_type'] ?? ''),
                'total_quantity' => (int) ($row['total_quantity'] ?? 0),
                'total_events' => (int) ($row['total_events'] ?? 0),
            ];
        }, $stmt->fetchAll());
    }

    public function getReportDashboard(?string $fromDate = null, ?string $toDate = null, ?string $lowFromDate = null, ?string $lowToDate = null, ?int $categoryId = null): array
    {
        $inventorySummary = $this->getInventorySummary();
        $inventoryReport = $this->getInventoryReport($fromDate, $toDate);
        $monthlySales = $this->getMonthlySales($fromDate, $toDate);
        $dailySales = $this->getDailySales($fromDate, $toDate);
        $lowStockReport = $this->getLowStockReport($lowFromDate, $lowToDate, $categoryId);
        $movementSummary = $this->getStockMovementSummary($fromDate, $toDate);
        $salesTransactions = $this->getSalesTransactionsForExport($fromDate, $toDate);
        $insightData = $this->getAdvancedSalesInsightData($fromDate, $toDate);
        $charts = $this->getChartDatasets($fromDate, $toDate, $lowFromDate, $lowToDate, $categoryId);

        $salesSummary = $this->getSalesSummary($fromDate, $toDate);
        $periodLabel = $this->buildPeriodLabel($fromDate, $toDate);
        $growth = percentageChange(
            (float) ($insightData['summary']['total_revenue'] ?? 0),
            (float) ($insightData['summary']['prev_month_revenue'] ?? 0)
        );

        return [
            'period_label' => $periodLabel,
            'inventory_summary' => $inventorySummary,
            'sales_summary' => [
                'revenue' => $salesSummary['revenue'],
                'orders' => $salesSummary['orders'],
                'units' => $salesSummary['units'],
                'average_order_value' => $salesSummary['average_order_value'],
                'growth_percentage' => round($growth, 1),
            ],
            'inventory_report' => $inventoryReport,
            'monthly_sales' => $monthlySales,
            'daily_sales' => $dailySales,
            'low_stock_report' => $lowStockReport,
            'movement_summary' => $movementSummary,
            'sales_transactions' => $salesTransactions,
            'insight_data' => $insightData,
            'charts' => $charts,
        ];
    }

    public function getChartDatasets(?string $fromDate = null, ?string $toDate = null, ?string $lowFromDate = null, ?string $lowToDate = null, ?int $categoryId = null): array
    {
        $dailySales = $this->getDailySales($fromDate, $toDate);
        $monthlySales = $this->getMonthlySales($fromDate, $toDate);
        $topProducts = $this->getTopProducts($fromDate, $toDate, 6);
        $categoryBreakdown = $this->getCategoryBreakdown($fromDate, $toDate);
        $lowStock = $this->getLowStockReport($lowFromDate, $lowToDate, $categoryId);
        $movement = $this->getStockMovementSummary($fromDate, $toDate);

        return [
            'daily_sales' => [
                'labels' => array_column($dailySales, 'sale_date'),
                'values' => array_map(static fn (array $row): float => (float) $row['total'], $dailySales),
            ],
            'monthly_sales' => [
                'labels' => array_column($monthlySales, 'month'),
                'values' => array_map(static fn (array $row): float => (float) $row['total'], $monthlySales),
            ],
            'top_products' => [
                'labels' => array_column($topProducts, 'name'),
                'values' => array_map(static fn (array $row): float => (float) $row['revenue'], $topProducts),
                'units' => array_map(static fn (array $row): int => (int) $row['units_sold'], $topProducts),
            ],
            'category_breakdown' => [
                'labels' => array_column($categoryBreakdown, 'name'),
                'values' => array_map(static fn (array $row): float => (float) $row['total'], $categoryBreakdown),
            ],
            'low_stock_severity' => [
                'labels' => array_map(static fn (array $row): string => (string) $row['name'], array_slice($lowStock, 0, 8)),
                'values' => array_map(static fn (array $row): float => (float) $row['severity'], array_slice($lowStock, 0, 8)),
                'gaps' => array_map(static fn (array $row): int => (int) $row['gap'], array_slice($lowStock, 0, 8)),
            ],
            'stock_movement' => [
                'labels' => array_map(static fn (array $row): string => strtoupper((string) $row['movement_type']), $movement),
                'values' => array_map(static fn (array $row): int => (int) $row['total_quantity'], $movement),
            ],
        ];
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

    private function buildSalesDateWhere(?string $fromDate, ?string $toDate, string $column = 'sale_date'): array
    {
        $conditions = [];
        $params = [];

        if ($fromDate) {
            $conditions[] = $column . ' >= :from_date';
            $params['from_date'] = $fromDate;
        }
        if ($toDate) {
            $conditions[] = $column . ' <= :to_date';
            $params['to_date'] = $toDate;
        }

        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    private function getSalesSummary(?string $fromDate = null, ?string $toDate = null): array
    {
        [$whereSql, $params] = $this->buildSalesDateWhere($fromDate, $toDate);
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantity * unit_price), 0) AS revenue,
                    COUNT(*) AS orders,
                    COALESCE(SUM(quantity), 0) AS units,
                    COALESCE(AVG(quantity * unit_price), 0) AS average_order_value
             FROM sales_transactions ' . $whereSql
        );
        $stmt->execute($params);
        $summary = $stmt->fetch() ?: [];

        return [
            'revenue' => round((float) ($summary['revenue'] ?? 0), 2),
            'orders' => (int) ($summary['orders'] ?? 0),
            'units' => (int) ($summary['units'] ?? 0),
            'average_order_value' => round((float) ($summary['average_order_value'] ?? 0), 2),
        ];
    }

    private function getTopProducts(?string $fromDate, ?string $toDate, int $limit = 5): array
    {
        [$whereSql, $params] = $this->buildSalesDateWhere($fromDate, $toDate, 'st.sale_date');
        $stmt = $this->pdo->prepare(
            'SELECT p.name,
                    SUM(st.quantity) AS units_sold,
                    SUM(st.quantity * st.unit_price) AS revenue
             FROM sales_transactions st
             INNER JOIN products p ON p.id = st.product_id
             ' . $whereSql . '
             GROUP BY p.id, p.name
             ORDER BY revenue DESC, units_sold DESC, p.name ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            return [
                'name' => (string) ($row['name'] ?? ''),
                'units_sold' => (int) ($row['units_sold'] ?? 0),
                'revenue' => round((float) ($row['revenue'] ?? 0), 2),
            ];
        }, $stmt->fetchAll());
    }

    private function getCategoryBreakdown(?string $fromDate, ?string $toDate): array
    {
        [$whereSql, $params] = $this->buildSalesDateWhere($fromDate, $toDate, 'st.sale_date');
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(c.name, "Unassigned") AS name,
                    SUM(st.quantity * st.unit_price) AS total
             FROM sales_transactions st
             INNER JOIN products p ON p.id = st.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             ' . $whereSql . '
             GROUP BY c.id, c.name
             ORDER BY total DESC'
        );
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            return [
                'name' => (string) ($row['name'] ?? ''),
                'total' => round((float) ($row['total'] ?? 0), 2),
            ];
        }, $stmt->fetchAll());
    }

    private function resolveInsightPeriod(?string $fromDate, ?string $toDate): array
    {
        if ($fromDate && $toDate) {
            $baseStart = new DateTimeImmutable($fromDate);
            $baseEnd = new DateTimeImmutable($toDate);
        } else {
            $stmt = $this->pdo->query('SELECT MAX(sale_date) FROM sales_transactions');
            $maxDate = $stmt->fetchColumn() ?: date('Y-m-d');
            $baseDate = new DateTimeImmutable((string) $maxDate);
            $baseStart = $baseDate->modify('first day of this month');
            $baseEnd = $baseDate->modify('last day of this month');
        }

        $rangeDays = (int) $baseStart->diff($baseEnd)->days + 1;
        $prevEnd = $baseStart->modify('-1 day');
        $prevStart = $prevEnd->modify('-' . max($rangeDays - 1, 0) . ' days');

        return [
            'start_date' => $baseStart->format('Y-m-d'),
            'end_date' => $baseEnd->format('Y-m-d'),
            'previous_start_date' => $prevStart->format('Y-m-d'),
            'previous_end_date' => $prevEnd->format('Y-m-d'),
            'label' => $this->buildPeriodLabel($baseStart->format('Y-m-d'), $baseEnd->format('Y-m-d')),
        ];
    }

    private function buildPeriodLabel(?string $fromDate, ?string $toDate): string
    {
        if ($fromDate && $toDate) {
            return $fromDate . ' to ' . $toDate;
        }
        if ($fromDate) {
            return 'From ' . $fromDate;
        }
        if ($toDate) {
            return 'Until ' . $toDate;
        }

        return 'All available data';
    }
}
