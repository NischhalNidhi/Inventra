<?php
// ============================================================
//  backend/config/permissions.php
//
//  Central permission map for the Inventra RBAC system.
//
//  Every feature in the system is listed here as a KEY.
//  The VALUE is an array of roles that are ALLOWED to use it.
//
//  HOW TO USE IN AN API FILE:
//    require_once __DIR__ . '/../config/permissions.php';
//    require_once __DIR__ . '/checkRole.php';
//    require_permission('stock_in');   ← one line to protect a route
//
//  HOW TO ADD A NEW FEATURE:
//    1. Add a new key to PERMISSIONS below.
//    2. List which roles can use it.
//    3. Call require_permission('your_new_key') in the API file.
// ============================================================

define('PERMISSIONS', [

    // ── Authentication ──────────────────────────────────────
    'login'                     => ['manager', 'supervisor', 'salesman', 'logistic'],

    // ── User Management (Manager only) ──────────────────────
    'create_staff'              => ['manager'],
    'manage_roles'              => ['manager'],
    'edit_user'                 => ['manager'],

    // ── Products ────────────────────────────────────────────
    'add_product'               => ['manager'],
    'edit_product'              => ['manager'],
    'delete_product'            => ['manager'],
    'view_products'             => ['manager', 'supervisor', 'salesman', 'logistic'],
    'upload_product_image'      => ['manager'],

    // ── Categories ──────────────────────────────────────────
    'manage_categories'         => ['manager'],          // add/edit/delete
    'view_categories'           => ['manager', 'supervisor', 'salesman', 'logistic'],

    // ── Stock ───────────────────────────────────────────────
    'set_min_stock'             => ['manager'],
    'stock_in'                  => ['manager', 'supervisor', 'logistic'],
    'stock_out'                 => ['manager', 'supervisor', 'salesman'],
    'view_stock'                => ['manager', 'supervisor', 'salesman', 'logistic'],
    'view_stock_history'        => ['manager', 'supervisor', 'logistic'],

    // ── Dashboard & Alerts ──────────────────────────────────
    'view_dashboard'            => ['manager', 'supervisor', 'salesman'],
    'low_stock_alerts'          => ['manager', 'supervisor', 'salesman', 'logistic'],
    'low_stock_graph'           => ['manager', 'supervisor'],

    // ── Suppliers & Purchase Orders ─────────────────────────
    'manage_suppliers'          => ['manager'],
    'create_purchase_order'     => ['manager', 'logistic'],
    'receive_purchase_order'    => ['manager', 'logistic'],
    'track_shipment'            => ['manager', 'logistic'],
    'view_po_tracker'           => ['manager', 'logistic'],
    'view_reorder_list'         => ['manager', 'supervisor', 'logistic'],
    'view_delivery_log'         => ['manager', 'logistic'],

    // ── Reports ─────────────────────────────────────────────
    'view_monthly_sales'        => ['manager', 'supervisor'],
    'view_daily_sales'          => ['manager', 'supervisor', 'salesman'],
    'date_range_filter'         => ['manager', 'supervisor', 'salesman'],
    'export_csv'                => ['manager'],
    'low_stock_report'          => ['manager', 'supervisor', 'salesman'],
    'stock_movement_report'     => ['manager', 'supervisor'],
    'generate_inventory_report' => ['manager'],

    // ── AI Features ─────────────────────────────────────────
    'ai_sales_insight'          => ['manager'],
    'ai_reorder_prediction'     => ['manager', 'supervisor', 'logistic'],
    'ai_distribution_heatmap'   => ['manager'],

]);
