<?php
// ============================================================
//  backend/api/checkAuth.php
//  Simple session-only check (login guard).
//
//  Use this when you just need to know if the user is logged in,
//  without checking their specific role/permission.
//
//  For role-based checks, use checkRole.php instead:
//    require_once __DIR__ . '/checkRole.php';
//    require_permission('some_feature');
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Unauthorized. Please log in first.'
    ]);
    exit;
}
// If we reach here, the user is logged in.
// $_SESSION['user_id'] and $_SESSION['role'] are available.