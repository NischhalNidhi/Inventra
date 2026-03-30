<?php
// ============================================================
//  backend/api/checkRole.php
//
//  RBAC middleware – session check + permission enforcement.
//
//  USAGE IN ANY API FILE:
//
//    // 1. Include at the top (after your JSON header)
//    require_once __DIR__ . '/checkRole.php';
//
//    // 2. Protect with a permission key
//    require_permission('stock_in');
//
//  That's it. Two lines protect any endpoint.
//
//  MULTIPLE ROLES EXAMPLE (check manually):
//    if (!has_permission('export_csv')) {
//        deny_access('export_csv');
//    }
// ============================================================

// Load the permissions map
require_once __DIR__ . '/../config/permissions.php';

// ── Start session if not already started ────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. Must be logged in ────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Unauthorized. Please log in first.'
    ]);
    exit;
}

// ── Core helper functions ────────────────────────────────────

/**
 * Check if the currently logged-in user has a given permission.
 *
 * @param  string $permission  A key from config/permissions.php
 * @return bool
 */
function has_permission(string $permission): bool
{
    $role = $_SESSION['role'] ?? '';

    // Manager always has full access
    if ($role === 'manager') {
        return true;
    }

    $map = PERMISSIONS;

    // Unknown permission key → deny by default (fail-safe)
    if (!array_key_exists($permission, $map)) {
        return false;
    }

    return in_array($role, $map[$permission], true);
}

/**
 * Require a permission – exits with 403 JSON if denied.
 *
 * @param string $permission  A key from config/permissions.php
 */
function require_permission(string $permission): void
{
    if (!has_permission($permission)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Access denied. Your role (' . ($_SESSION['role'] ?? 'unknown') . ') does not have permission for: ' . $permission
        ]);
        exit;
    }
}

/**
 * Explicitly deny access with a custom message.
 * Useful for inline conditional checks.
 *
 * @param string $permission  Feature name for the error message
 */
function deny_access(string $permission = 'this action'): void
{
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Access denied for: ' . $permission
    ]);
    exit;
}

/**
 * Get the current logged-in user's role.
 *
 * @return string  e.g. 'manager', 'supervisor', 'salesman', 'logistic'
 */
function current_role(): string
{
    return $_SESSION['role'] ?? '';
}

/**
 * Get the current logged-in user's ID.
 *
 * @return int
 */
function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}
