<?php
// ============================================================
//  backend/api/examples/deleteProduct.php
//  EXAMPLE: Manager-only route
//
//  Permission required: 'delete_product'  → only manager
// ============================================================
header('Content-Type: application/json');

require_once __DIR__ . '/../checkRole.php';
require_permission('delete_product');       // ← BLOCKS everyone except manager

// ── Only allow POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST only.']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$product_id = (int)($data['product_id'] ?? 0);

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid product_id.']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
$stmt->bind_param('i', $product_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['status' => 'success', 'message' => "Product #$product_id deleted."]);
} else {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
}

$stmt->close();
$conn->close();
