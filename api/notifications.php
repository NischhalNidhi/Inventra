<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

header('Content-Type: application/json; charset=utf-8');

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();

$userId = (int) currentUser()['id'];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $notifications = $notificationModel->getUnread($userId);
            $unreadCount = $notificationModel->countUnread($userId);
            echo json_encode([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ], JSON_UNESCAPED_SLASHES);
            break;

        case 'mark_read':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid security token']);
                break;
            }
            $notifId = (int) ($_POST['notification_id'] ?? 0);
            if ($notifId > 0) {
                $notificationModel->markRead($notifId, $userId);
            }
            echo json_encode(['success' => true]);
            break;

        case 'mark_all_read':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid security token']);
                break;
            }
            $notificationModel->markAllRead($userId);
            echo json_encode(['success' => true, 'message' => 'All notifications cleared.']);
            break;

        case 'check':
            // Quick poll — just returns unread count
            $unreadCount = $notificationModel->countUnread($userId);
            echo json_encode(['unread_count' => $unreadCount]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Use: list, mark_read, mark_all_read, check']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
