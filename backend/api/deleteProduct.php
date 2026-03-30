<?php
// ============================================================
//  backend/api/deleteProduct.php
//  EXAMPLE: Admin-only protected endpoint.
//
//  This file shows the pattern for creating any protected route:
//    1. Include checkAuth.php  → blocks unauthenticated users
//    2. Call require_role()    → blocks users without correct role
//    3. Do the actual work
// ============================================================

header('Content-Type: application/json');

// ── Step 1: Must be logged in ────────────────────────────────
require_once __DIR__ . '/checkAuth.php';
// If the user is not logged in, the script stops here with 401.

// ── Step 2: Must be an admin ─────────────────────────────────
require_role('admin');
// If the logged-in user is not an admin, the script stops here with 403.

// ── Step 3: Only allow DELETE (or POST) requests ─────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed.']);
    exit;
}

// ── Step 4: Read input ───────────────────────────────────────
$data       = json_decode(file_get_contents('php://input'), true);
$product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid product_id.']);
    exit;
}

// ── Step 5: Delete from DB ───────────────────────────────────
require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
$stmt->bind_param('i', $product_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        'status'  => 'success',
        'message' => 'Product #' . $product_id . ' deleted.'
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Product not found.'
    ]);
}

$stmt->close();
$conn->close();
