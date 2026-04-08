<?php

declare(strict_types=1);

class Category
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(): array
    {
        return $this->pdo->query(
            'SELECT id, name, description, created_at FROM categories ORDER BY name ASC'
        )->fetchAll();
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
