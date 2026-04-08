<?php

declare(strict_types=1);

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, username, email, password_hash, role, is_active, must_change_password
             FROM users
             WHERE username = :username OR email = :email
             LIMIT 1'
        );
        $stmt->execute(['username' => $identifier, 'email' => $identifier]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function getAll(int $limit = 25, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, email, username, role, is_active, created_at
             FROM users
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (full_name, email, username, password_hash, role, must_change_password, is_active)
             VALUES (:full_name, :email, :username, :password_hash, :role, :must_change_password, 1)'
        );
        $stmt->execute([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'must_change_password' => (int) ($data['must_change_password'] ?? 0),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET full_name = :full_name, email = :email, role = :role
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ]);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, email, username, role, is_active, created_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function usernameOrEmailExists(string $username, string $email, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email)';
        $params = ['username' => $username, 'email' => $email];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function markPasswordChanged(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET password_hash = :password_hash, must_change_password = 0
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'password_hash' => $passwordHash,
        ]);
    }
}
