<?php

declare(strict_types=1);

class Category
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(int $page = 1, int $perPage = 25, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;
        
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE name LIKE :s1 OR description LIKE :s2';
            $kw = '%' . $search . '%';
            $params['s1'] = $kw;
            $params['s2'] = $kw;
        }
        
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM categories $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT id, name, description, created_at FROM categories $where ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function create(string $name, ?string $description): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, description) VALUES (:name, :description)'
        );
        $stmt->execute(['name' => $name, 'description' => $description]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE categories SET name = :name, description = :description WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'name' => $name, 'description' => $description]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function hasAssignedProducts(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
