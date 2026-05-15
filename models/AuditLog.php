<?php

declare(strict_types=1);

class AuditLog
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(int $actorId, string $action, string $targetType, ?int $targetId = null, ?array $metadata = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log (actor_id, action, target_type, target_id, metadata)
             VALUES (:actor_id, :action, :target_type, :target_id, :metadata)'
        );

        $stmt->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata !== null ? json_encode($metadata) : null,
        ]);
    }

    public function getLogs(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.full_name as actor_name 
             FROM audit_log a 
             LEFT JOIN users u ON a.actor_id = u.id 
             ORDER BY a.created_at DESC 
             LIMIT :limit OFFSET :offset'
        );
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
