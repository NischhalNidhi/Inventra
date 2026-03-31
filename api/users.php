<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../controllers/authController.php';
require_once __DIR__ . '/../controllers/userController.php';

$pdo = getDatabaseConnection();
$userModel = new User($pdo);
$authController = new AuthController($userModel);
$userController = new UserController($userModel);
$authController->requireAuthentication();
$authController->authorize('users.view');

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pagination = parsePagination($_GET);
    jsonResponse([
        'users' => $userModel->getAll($pagination['limit'], $pagination['offset']),
        'total' => $userModel->countAll(),
        'page' => $pagination['page'],
        'limit' => $pagination['limit'],
    ]);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse(['error' => 'Invalid request token.', 'code' => 'INVALID_TOKEN'], 422);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController->authorize('users.create');
    $validated = $userController->validateCreate($_POST);
    if ($validated['errors']) {
        jsonResponse(['error' => implode(' ', $validated['errors']), 'code' => 'VALIDATION_ERROR'], 422);
    }
    $newId = $userModel->create($validated['data']);
    jsonResponse(['user' => $userModel->findById($newId)], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str((string) file_get_contents('php://input'), $input);
    $authController->authorize('users.edit');
    $validated = $userController->validateUpdate($id, $input);
    if ($validated['errors']) {
        jsonResponse(['error' => implode(' ', $validated['errors']), 'code' => 'VALIDATION_ERROR'], 422);
    }
    $userModel->update($id, $validated['data']);
    jsonResponse(['user' => $userModel->findById($id)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && ($_GET['action'] ?? '') === 'deactivate') {
    $authController->authorize('users.deactivate');
    $userModel->deactivate($id);
    jsonResponse(['message' => 'User deactivated.']);
}

jsonResponse(['error' => 'Method not allowed.', 'code' => 'METHOD_NOT_ALLOWED'], 405);
