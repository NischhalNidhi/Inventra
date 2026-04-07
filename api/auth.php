<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);

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
