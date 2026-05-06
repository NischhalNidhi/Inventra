<?php

declare(strict_types=1);

class Notification
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // -----------------------------------------------------------------
    // Fetch notifications for a user
    // -----------------------------------------------------------------

    /**
     * Get unread notifications for a user, newest first.
     */
    public function getUnread(int $userId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, p.name AS product_name, p.sku AS product_sku
             FROM notifications n
             LEFT JOIN products p ON p.id = n.product_id
             WHERE n.user_id = :user_id AND n.is_read = 0
             ORDER BY n.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count unread notifications for a user.
     */
    public function countUnread(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get all notifications for a user (paginated).
     */
    public function getAll(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, p.name AS product_name, p.sku AS product_sku
             FROM notifications n
             LEFT JOIN products p ON p.id = n.product_id
             WHERE n.user_id = :user_id
             ORDER BY n.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------
    // Manage notifications
    // -----------------------------------------------------------------

    /**
     * Mark a single notification as read.
     */
    public function markRead(int $notificationId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllRead(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Create a single notification.
     */
    public function create(int $userId, string $type, string $title, string $message, ?int $productId = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, message, product_id)
             VALUES (:user_id, :type, :title, :message, :product_id)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'product_id' => $productId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Delete old read notifications (cleanup, older than 30 days).
     */
    public function purgeOld(int $daysOld = 30): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );
        $stmt->bindValue(':days', $daysOld, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    // -----------------------------------------------------------------
    // Low-stock alert generation
    // -----------------------------------------------------------------

    /**
     * Scan products and generate low-stock / out-of-stock notifications
     * for users who have the dashboard.alert_graph permission.
     *
     * Uses the low_stock_alert_log table to avoid duplicate alerts.
     * Returns the number of new alerts created.
     */
    public function generateLowStockAlerts(array $recipientUserIds, $mailer = null, string $dashboardLink = ''): int
    {
        if (empty($recipientUserIds)) {
            return 0;
        }

        // Find products at or below threshold that don't have an unresolved alert
        $products = $this->pdo->query(
            'SELECT p.id, p.name, p.sku, p.stock_quantity, p.min_threshold
             FROM products p
             WHERE p.is_archived = 0
               AND p.stock_quantity <= p.min_threshold
               AND NOT EXISTS (
                   SELECT 1 FROM low_stock_alert_log a
                   WHERE a.product_id = p.id AND a.resolved_at IS NULL
               )
             ORDER BY (p.min_threshold - p.stock_quantity) DESC'
        )->fetchAll();

        if (empty($products)) {
            return 0;
        }

        $alertCount = 0;

        foreach ($products as $product) {
            $stock = (int) $product['stock_quantity'];
            $threshold = (int) $product['min_threshold'];
            $isOutOfStock = $stock === 0;

            $alertType = $isOutOfStock ? 'out_of_stock' : 'low_stock';
            $title = $isOutOfStock
                ? 'Out of Stock: ' . $product['name']
                : 'Low Stock Alert: ' . $product['name'];
            $message = $isOutOfStock
                ? sprintf('%s (%s) is completely out of stock.', $product['name'], $product['sku'])
                : sprintf('%s (%s) has %d units — below minimum of %d.', $product['name'], $product['sku'], $stock, $threshold);

            // Log the alert to prevent duplicates
            $logStmt = $this->pdo->prepare(
                'INSERT INTO low_stock_alert_log (product_id, alert_type, stock_at_alert, threshold_at_alert)
                 VALUES (:product_id, :alert_type, :stock, :threshold)'
            );
            $logStmt->execute([
                'product_id' => $product['id'],
                'alert_type' => $alertType,
                'stock' => $stock,
                'threshold' => $threshold,
            ]);

            // Send notification to each recipient
            foreach ($recipientUserIds as $userId) {
                $this->create($userId, $alertType, $title, $message, (int) $product['id']);
                $alertCount++;
            }
        }

        // Send email to all recipients summarizing the new low stock products
        if ($mailer !== null && count($products) > 0) {
            $placeholders = implode(',', array_fill(0, count($recipientUserIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id, full_name, email FROM users WHERE id IN ($placeholders)");
            $stmt->execute($recipientUserIds);
            $users = $stmt->fetchAll();

            foreach ($users as $u) {
                $mailer->sendLowStockAlert($u['email'], $u['full_name'], $products, $dashboardLink);
            }
        }

        // Resolve old alerts for products back above threshold
        $this->pdo->exec(
            'UPDATE low_stock_alert_log a
             INNER JOIN products p ON p.id = a.product_id
             SET a.resolved_at = NOW()
             WHERE a.resolved_at IS NULL
               AND p.stock_quantity > p.min_threshold'
        );

        return $alertCount;
    }

    /**
     * Get user IDs that should receive low-stock alerts (Managers + Supervisors).
     */
    public function getAlertRecipients(): array
    {
        return array_column(
            $this->pdo->query(
                "SELECT id FROM users WHERE is_active = 1 AND role IN ('Manager', 'Supervisor')"
            )->fetchAll(),
            'id'
        );
    }
}
