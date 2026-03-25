<?php
// ============================================================
//  backend/api/logout.php
//  Destroys the current session and returns a success message.
// ============================================================

session_start();

header('Content-Type: application/json');

// CORS – must match exact origin for credentials to work
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (str_starts_with($origin, 'http://localhost')) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');


// ── Clear all session variables ──────────────────────────────
$_SESSION = [];

// ── 3. Destroy the session cookie in the browser ───────────
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,        // expire in the past → browser deletes it
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// ── 4. Destroy the session on the server ────────────────────
session_destroy();

// ── 5. Respond ──────────────────────────────────────────────
http_response_code(200);
echo json_encode([
    'status'  => 'success',
    'message' => 'Logged out.'
]);