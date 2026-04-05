<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
$authController->requireAuthentication();
$authController->authorize('categories.view');

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse(['categories' => $categoryModel->getAll()]);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse(['error' => 'Invalid request token.', 'code' => 'INVALID_TOKEN'], 422);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController->authorize('categories.manage');
    $validated = $categoryController->validate($_POST);
    if ($validated['errors']) {
        jsonResponse(['error' => implode(' ', $validated['errors']), 'code' => 'VALIDATION_ERROR'], 422);
    }
    $newId = $categoryModel->create($validated['data']['name'], $validated['data']['description']);
    jsonResponse(['category_id' => $newId], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $authController->authorize('categories.manage');
    if ($categoryModel->hasAssignedProducts($id)) {
        jsonResponse(['error' => 'Category has assigned products.', 'code' => 'CATEGORY_IN_USE'], 409);
    }
    $categoryModel->delete($id);
    jsonResponse(['message' => 'Category deleted.']);
}

jsonResponse(['error' => 'Method not allowed.', 'code' => 'METHOD_NOT_ALLOWED'], 405);
