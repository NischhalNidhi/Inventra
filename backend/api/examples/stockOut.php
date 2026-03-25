<?php
// ============================================================
//  backend/api/examples/stockOut.php
//  EXAMPLE: Multi-role route – manager, supervisor, salesman
//
//  Permission required: 'stock_out'
// ============================================================
header('Content-Type: application/json');

require_once __DIR__ . '/../checkRole.php';
require_permission('stock_out');   // ← allows manager, supervisor, salesman
                                   //   blocks logistic with 403

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

// Check available stock first
$check = $conn->prepare('SELECT stock FROM products WHERE id = ?');
$check->bind_param('i', $product_id);
$check->execute();
$result = $check->get_result()->fetch_assoc();
$check->close();

if (!$result) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
    $conn->close();
    exit;
}

if ($result['stock'] < $quantity) {
    http_response_code(409); // Conflict
    echo json_encode([
        'status'    => 'error',
        'message'   => 'Not enough stock.',
        'available' => $result['stock'],
        'requested' => $quantity
    ]);
    $conn->close();
    exit;
}

// Log the movement
$stmt = $conn->prepare(
    'INSERT INTO stock_movements (product_id, type, quantity, user_id, created_at)
     VALUES (?, "out", ?, ?, NOW())'
);
$user_id = current_user_id();
$stmt->bind_param('iii', $product_id, $quantity, $user_id);
$stmt->execute();
$stmt->close();

// Decrement stock
$stmt = $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
$stmt->bind_param('ii', $quantity, $product_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'status'  => 'success',
    'message' => "Stocked out $quantity units from product #$product_id.",
    'by_role' => current_role()
]);

$conn->close();
