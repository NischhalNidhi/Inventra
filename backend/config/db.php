<?php
// ============================================================
//  backend/config/db.php
//  Database connection – returns a mysqli connection object.
//  Include this file wherever you need the database.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default: empty password
define('DB_NAME', 'inventra');

// Create the connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check for connection errors
if ($conn->connect_error) {
    // Return a clean JSON error instead of a raw PHP warning
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// Use UTF-8 for all queries
$conn->set_charset('utf8mb4');