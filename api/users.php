<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/dependencies.php';

extract(buildAppDependencies(), EXTR_SKIP);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        jsonResponse(['error' => 'Invalid request token.', 'code' => 'INVALID_TOKEN'], 422);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController->authorize('users.create');
    $validated = $userController->validateCreate($_POST);
    if ($validated['errors']) {
        jsonResponse(['error' => implode(' ', $validated['errors']), 'code' => 'VALIDATION_ERROR'], 422);
    }

    // BUG FIX (Task 3): Previously called $userModel->create() which skipped token
    // generation and welcome email dispatch entirely. Now uses createPendingSetup()
    // (is_active=0) to match the form-based flow, then fires the welcome email.
    $newId       = $userModel->createPendingSetup($validated['data']);
    $createdUser = $userModel->findById($newId);

    // --- Respond first, then send email ---
    // Flush the HTTP response to the client immediately so the Manager's UI
    // is unblocked even if the mail server is slow.
    $responseBody = json_encode(['user' => $createdUser], JSON_UNESCAPED_SLASHES);
    http_response_code(201);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . strlen($responseBody));
    echo $responseBody;

    // Signal to the SAPI that the response is complete (works on FastCGI/FPM).
    // Falls back silently on Apache mod_php where this function does not exist.
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        // Flush all output buffers so Apache sends the response payload.
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    // --- Send welcome email (fire-and-forget) ---
    // Failures are logged but must never roll back the already-created account.
    if ($createdUser) {
        $mailResult = $authController->sendAccountSetupEmail($createdUser);
        if (!$mailResult['success']) {
            error_log('[Inventra] Welcome email failed for user ID ' . ((int) $createdUser['id']) . ': ' . implode('; ', $mailResult['errors'] ?? ['Unknown error']));
        }
    }

    exit;
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

if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && ($_GET['action'] ?? '') === 'activate') {
    $authController->authorize('users.activate');
    $userModel->activate($id);
    jsonResponse(['message' => 'User reactivated.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && ($_GET['action'] ?? '') === 'deactivate') {
    $authController->authorize('users.deactivate');
    if ($id === (int) currentUser()['id']) {
        jsonResponse(['error' => 'You cannot deactivate your own account.', 'code' => 'SELF_DEACTIVATION'], 422);
    }
    $userModel->deactivate($id);
    jsonResponse(['message' => 'User deactivated.']);
}

jsonResponse(['error' => 'Method not allowed.', 'code' => 'METHOD_NOT_ALLOWED'], 405);
