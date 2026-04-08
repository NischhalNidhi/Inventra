<?php

declare(strict_types=1);

class Product
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getCategories(): array
    {
        return $this->pdo->query(
            'SELECT id, name FROM categories ORDER BY name ASC'
        )->fetchAll();
    }

    public function getSuppliers(): array
    {
        return $this->pdo->query(
            'SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC'
        )->fetchAll();
    }

    public function getDashboardStats(): array
    {
        $stats = [
            'total_products' => (int) $this->pdo->query('SELECT COUNT(*) FROM products WHERE is_archived = 0')->fetchColumn(),
            'critical_count' => (int) $this->pdo->query('SELECT COUNT(*) FROM products WHERE is_archived = 0 AND stock_quantity <= min_threshold')->fetchColumn(),
            'out_of_stock' => (int) $this->pdo->query('SELECT COUNT(*) FROM products WHERE is_archived = 0 AND stock_quantity = 0')->fetchColumn(),
            'total_suppliers' => (int) $this->pdo->query('SELECT COUNT(*) FROM suppliers WHERE is_active = 1')->fetchColumn(),
            'pending_po' => (int) $this->pdo->query('SELECT COUNT(*) FROM purchase_orders WHERE status = "pending"')->fetchColumn(),
        ];

        $healthy = (int) $this->pdo->query('SELECT COUNT(*) FROM products WHERE is_archived = 0 AND stock_quantity > min_threshold')->fetchColumn();
        $stats['health_percentage'] = $stats['total_products'] > 0
            ? (int) round(($healthy / $stats['total_products']) * 100)
            : 100;

        return $stats;
    }

    public function getFeaturedProducts(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.is_archived = 0
             ORDER BY p.updated_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAll(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        [$whereSql, $params] = $this->buildFilterQuery($filters);
        $sql = 'SELECT p.*, c.name AS category_name, s.name AS supplier_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN suppliers s ON s.id = p.supplier_id ' . $whereSql . '
                ORDER BY p.updated_at DESC, p.name ASC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int
    {
        [$whereSql, $params] = $this->buildFilterQuery($filters);
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN suppliers s ON s.id = p.supplier_id ' . $whereSql
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.name AS category_name, s.name AS supplier_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    public function create(array $data, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO products
             (name, sku, description, image_name, stock_quantity, min_threshold, unit_price, category_id, supplier_id, created_by, updated_by)
             VALUES
             (:name, :sku, :description, :image_name, :stock_quantity, :min_threshold, :unit_price, :category_id, :supplier_id, :created_by, :updated_by)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'sku' => $data['sku'],
            'description' => $data['description'],
            'image_name' => $data['image_name'],
            'stock_quantity' => $data['stock_quantity'],
            'min_threshold' => $data['min_threshold'],
            'unit_price' => $data['unit_price'],
            'category_id' => $data['category_id'],
            'supplier_id' => $data['supplier_id'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE products
             SET name = :name,
                 sku = :sku,
                 description = :description,
                 image_name = :image_name,
                 stock_quantity = :stock_quantity,
                 min_threshold = :min_threshold,
                    unit_price = :unit_price,
                 category_id = :category_id,
                 supplier_id = :supplier_id,
                 updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'sku' => $data['sku'],
            'description' => $data['description'],
            'image_name' => $data['image_name'],
            'stock_quantity' => $data['stock_quantity'],
            'min_threshold' => $data['min_threshold'],
            'unit_price' => $data['unit_price'],
            'category_id' => $data['category_id'],
            'supplier_id' => $data['supplier_id'],
            'updated_by' => $userId,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function archive(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE products SET is_archived = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function skuExists(string $sku, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM products WHERE sku = :sku';
        $params = ['sku' => $sku];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function getLowStockProducts(): array
    {
        return $this->pdo->query(
            'SELECT p.id, p.name, p.sku, p.stock_quantity, p.min_threshold
             FROM products p
             WHERE p.is_archived = 0 AND p.stock_quantity <= p.min_threshold
             ORDER BY (p.min_threshold - p.stock_quantity) DESC, p.name ASC'
        )->fetchAll();
    }

    public function getProductMovements(int $productId, int $limit = 25): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sm.*, u.full_name
             FROM stock_movements sm
             INNER JOIN users u ON u.id = sm.user_id
             WHERE sm.product_id = :product_id
             ORDER BY sm.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAlertGraphData(): array
    {
        return $this->pdo->query(
            'SELECT name, stock_quantity, min_threshold
             FROM products
             WHERE is_archived = 0
             ORDER BY name ASC'
        )->fetchAll();
    }

    private function buildFilterQuery(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['keyword'])) {
            $conditions[] = '(p.name LIKE :keyword OR p.sku LIKE :keyword OR c.name LIKE :keyword)';
            $params['keyword'] = '%' . trim($filters['keyword']) . '%';
        }

        if (!empty($filters['category'])) {
            $conditions[] = 'c.id = :category_id';
            $params['category_id'] = (int) $filters['category'];
        }

        if (isset($filters['archived']) && $filters['archived'] !== '') {
            $conditions[] = 'p.is_archived = :is_archived';
            $params['is_archived'] = (int) $filters['archived'];
        } else {
            $conditions[] = 'p.is_archived = 0';
        }

        if (!empty($filters['stock_level'])) {
            if ($filters['stock_level'] === 'critical') {
                $conditions[] = 'p.stock_quantity <= p.min_threshold';
            } elseif ($filters['stock_level'] === 'healthy') {
                $conditions[] = 'p.stock_quantity > p.min_threshold';
            } elseif ($filters['stock_level'] === 'empty') {
                $conditions[] = 'p.stock_quantity = 0';
            }
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$whereSql, $params];
    }
}
