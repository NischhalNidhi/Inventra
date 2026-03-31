<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../controllers/authController.php';

$pdo = getDatabaseConnection();
$authController = new AuthController(new User($pdo));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $result = $authController->login($_POST['identifier'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            if (!empty($result['requires_password_setup'])) {
                jsonResponse(['requires_password_setup' => true, 'code' => 'PASSWORD_SETUP_REQUIRED']);
            }
            jsonResponse(['user' => currentUser(), 'landing_page' => $result['landing_page']]);
        }
        jsonResponse(['error' => implode(' ', $result['errors']), 'code' => 'AUTH_INVALID'], 401);
    }
    if ($action === 'logout') {
        $authController->logout();
        jsonResponse(['message' => 'Logged out.']);
    }
}

jsonResponse(['error' => 'Method not allowed.', 'code' => 'METHOD_NOT_ALLOWED'], 405);
