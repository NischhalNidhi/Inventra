<?php

declare(strict_types=1);

class Supplier
{
    private PDO $pdo;
    private static bool $imageColumnChecked = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureImageColumn();
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
            $conditions[] = '(name LIKE :s1 OR contact_person LIKE :s2 OR email LIKE :s3)';
            $kw = '%' . $search . '%';
            $params['s1'] = $kw;
            $params['s2'] = $kw;
            $params['s3'] = $kw;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM suppliers $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT id, name, contact_person, email, phone, image_name, is_active, created_at FROM suppliers $where ORDER BY name ASC LIMIT :limit OFFSET :offset";
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
            'INSERT INTO suppliers (name, contact_person, email, phone, image_name, is_active)
             VALUES (:name, :contact_person, :email, :phone, :image_name, 1)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'contact_person' => $data['contact_person'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'image_name' => $data['image_name'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE suppliers
             SET name = :name, contact_person = :contact_person, email = :email, phone = :phone, image_name = :image_name
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'contact_person' => $data['contact_person'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'image_name' => $data['image_name'],
        ]);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE suppliers SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function ensureImageColumn(): void
    {
        if (self::$imageColumnChecked) {
            return;
        }

        self::$imageColumnChecked = true;

        $column = $this->pdo->query("SHOW COLUMNS FROM suppliers LIKE 'image_name'")->fetch();
        if ($column) {
            return;
        }

        $this->pdo->exec("ALTER TABLE suppliers ADD COLUMN image_name VARCHAR(255) DEFAULT NULL AFTER phone");
    }
}
