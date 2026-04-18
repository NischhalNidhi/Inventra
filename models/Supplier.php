<?php

declare(strict_types=1);

class Supplier
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(int $page = 1, int $perPage = 25, string $search = '', bool $activeOnly = false): array
    {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        if ($activeOnly) {
            $conditions[] = 'is_active = 1';
        }
        if ($search !== '') {
            $conditions[] = '(name LIKE :search OR contact_person LIKE :search OR email LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM suppliers $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT id, name, contact_person, email, phone, is_active, created_at FROM suppliers $where ORDER BY name ASC LIMIT :limit OFFSET :offset";
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
