<?php
// ============================================================
//  backend/api/examples/stockIn.php
//  EXAMPLE: Multi-role route – manager, supervisor, logistic
//
//  Permission required: 'stock_in'
// ============================================================
header('Content-Type: application/json');

require_once __DIR__ . '/../checkRole.php';
require_permission('stock_in');    // ← allows manager, supervisor, logistic
                                   //   blocks salesman with 403

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST only.']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$product_id = (int)($data['product_id'] ?? 0);
$quantity   = (int)($data['quantity']   ?? 0);

if ($product_id <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'product_id and quantity required.']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Log the stock movement
$stmt = $conn->prepare(
    'INSERT INTO stock_movements (product_id, type, quantity, user_id, created_at)
     VALUES (?, "in", ?, ?, NOW())'
);
$user_id = current_user_id();
$stmt->bind_param('iii', $product_id, $quantity, $user_id);
$stmt->execute();
$stmt->close();

// Update the product stock level
$stmt = $conn->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
$stmt->bind_param('ii', $quantity, $product_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'status'  => 'success',
    'message' => "Stocked in $quantity units for product #$product_id.",
    'by_role' => current_role()
]);

$conn->close();
