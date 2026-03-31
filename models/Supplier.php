<?php

declare(strict_types=1);

class Supplier
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getAll(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, contact_person, email, phone, is_active, created_at FROM suppliers';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suppliers (name, contact_person, email, phone, is_active)
             VALUES (:name, :contact_person, :email, :phone, 1)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'contact_person' => $data['contact_person'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE suppliers
             SET name = :name, contact_person = :contact_person, email = :email, phone = :phone
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'contact_person' => $data['contact_person'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE suppliers SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
