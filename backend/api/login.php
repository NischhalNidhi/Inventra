<?php
// ============================================================
//  backend/api/login.php
//  Accepts a POST request with JSON body { username, password }
//  Returns JSON with status and role on success.
// ============================================================

// Start session FIRST – before any output or headers
session_start();

// Always respond with JSON
header('Content-Type: application/json');

// CORS: must use exact origin (not *) when credentials are involved
$allowed_origin = 'http://localhost';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowed_origin || str_starts_with($origin, 'http://localhost')) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── 1. Only allow POST ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

// ── 2. Read and decode the JSON body ────────────────────────
$body = file_get_contents('php://input');   // raw request body
$data = json_decode($body, true);           // decode to array

// Validate that required fields exist
if (empty($data['username']) || empty($data['password'])) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'status'  => 'error',
        'message' => 'Username and password are required.'
    ]);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];          // plain-text – verified below

// ── 3. Connect to the database ──────────────────────────────
require_once __DIR__ . '/../config/db.php';
// $conn is now available from db.php

// ── 4. Look up the user (prepared statement = safe from SQL injection) ──
$stmt = $conn->prepare(
    'SELECT id, password, role FROM users WHERE username = ? LIMIT 1'
);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // No user with that username
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid credentials.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// ── 5. Verify the password (bcrypt) ─────────────────────────
//  password_verify() compares the plain-text input against the stored hash.
if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid credentials.'
    ]);
    $conn->close();
    exit;
}

// ── 6. Credentials are correct – store in session ────────────
// session_start() was already called at the top of this file.

// Regenerate the session ID to prevent session-fixation attacks
session_regenerate_id(true);

// Store key user data in the session
$_SESSION['user_id']  = $user['id'];
$_SESSION['username'] = $username;
$_SESSION['role']     = $user['role'];

// ── 7. Return success ────────────────────────────────────────
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'role'   => $user['role']
]);

$conn->close();