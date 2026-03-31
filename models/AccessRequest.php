<?php

declare(strict_types=1);

class AccessRequest
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO access_requests (full_name, email, desired_role, message, status)
             VALUES (:full_name, :email, :desired_role, :message, "pending")'
        );
        $stmt->execute([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'desired_role' => $data['desired_role'],
            'message' => $data['message'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getAllPending(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, full_name, email, desired_role, message, status, created_at
             FROM access_requests
             WHERE status = "pending"
             ORDER BY created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, email, desired_role, message, status
             FROM access_requests
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();
        return $request ?: null;
    }

    public function approve(int $id, int $reviewerId, ?string $note): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE access_requests
             SET status = "approved", review_note = :review_note, reviewed_by = :reviewed_by, reviewed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'review_note' => $note,
            'reviewed_by' => $reviewerId,
        ]);
    }

    public function reject(int $id, int $reviewerId, ?string $note): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE access_requests
             SET status = "rejected", review_note = :review_note, reviewed_by = :reviewed_by, reviewed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'review_note' => $note,
            'reviewed_by' => $reviewerId,
        ]);
    }
}
