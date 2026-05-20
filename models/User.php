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
            'SELECT id, full_name, username, email, password_hash, role, profile_image, is_active, must_change_password
             FROM users
             WHERE username = :username OR email = :email
             LIMIT 1'
        );
        $stmt->execute(['username' => $identifier, 'email' => $identifier]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findActiveByIdentifier(string $identifier): ?array
    {
        $user = $this->findByIdentifier($identifier);
        if (!$user || !(int) $user['is_active']) {
            return null;
        }

        return $user;
    }

    public function getAll(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $params = [];
        $where = '';

        if ($search !== '') {
            $where = 'WHERE full_name LIKE :full_name OR email LIKE :email OR username LIKE :username OR role LIKE :role';
            $keyword = '%' . $search . '%';
            $params = [
                'full_name' => $keyword,
                'email' => $keyword,
                'username' => $keyword,
                'role' => $keyword,
            ];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, email, username, role, profile_image, is_active, created_at
             FROM users
             ' . $where . '
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(string $search = ''): int
    {
        if ($search === '') {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        }

        $keyword = '%' . $search . '%';
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM users
             WHERE full_name LIKE :full_name OR email LIKE :email OR username LIKE :username OR role LIKE :role'
        );
        $stmt->execute([
            'full_name' => $keyword,
            'email' => $keyword,
            'username' => $keyword,
            'role' => $keyword,
        ]);

        return (int) $stmt->fetchColumn();
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
        // Fetch existing data to preserve fields not included in the update array (e.g., username or role)
        $existing = $this->findById($id);
        if (!$existing) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET full_name = :full_name, email = :email, username = :username, role = :role
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'full_name' => $data['full_name'] ?? $existing['full_name'],
            'email' => $data['email'] ?? $existing['email'],
            'username' => $data['username'] ?? $existing['username'],
            'role' => $data['role'] ?? $existing['role'],
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
            'SELECT id, full_name, email, username, role, profile_image, is_active, created_at
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

    public function createPendingSetup(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (full_name, email, username, password_hash, role, must_change_password, is_active)
             VALUES (:full_name, :email, :username, :password_hash, :role, 0, 0)'
        );
        $stmt->execute([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createPasswordToken(int $userId, string $purpose, string $tokenHash, string $expiresAt): void
    {
        $this->deletePasswordTokens($userId, $purpose);
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_tokens (user_id, token_hash, purpose, expires_at)
             VALUES (:user_id, :token_hash, :purpose, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findPasswordToken(string $tokenHash, string $purpose): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pt.*, u.email, u.username, u.full_name, u.role, u.is_active
             FROM password_tokens pt
             INNER JOIN users u ON u.id = pt.user_id
             WHERE pt.token_hash = :token_hash AND pt.purpose = :purpose
             LIMIT 1'
        );
        $stmt->execute([
            'token_hash' => $tokenHash,
            'purpose' => $purpose,
        ]);
        $token = $stmt->fetch();

        return $token ?: null;
    }

    public function deletePasswordTokenByHash(string $tokenHash): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_tokens WHERE token_hash = :token_hash');
        $stmt->execute(['token_hash' => $tokenHash]);
    }

    public function deletePasswordTokens(int $userId, string $purpose): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_tokens WHERE user_id = :user_id AND purpose = :purpose');
        $stmt->execute(['user_id' => $userId, 'purpose' => $purpose]);
    }

    public function deleteExpiredPasswordTokens(): void
    {
        $this->pdo->exec('DELETE FROM password_tokens WHERE expires_at < CURRENT_TIMESTAMP');
    }

    public function activateWithPassword(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET password_hash = :password_hash, must_change_password = 0, is_active = 1
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'password_hash' => $passwordHash,
        ]);
    }

    public function updateProfileImage(int $id, ?string $imageName): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET profile_image = :profile_image WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'profile_image' => $imageName,
        ]);
    }
}
