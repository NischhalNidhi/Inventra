CREATE DATABASE IF NOT EXISTS inventra;
USE inventra;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS report_import_row_errors;
DROP TABLE IF EXISTS report_import_batches;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_tokens;
DROP TABLE IF EXISTS password_reset_requests;
DROP TABLE IF EXISTS sales_transactions;
DROP TABLE IF EXISTS access_requests;
DROP TABLE IF EXISTS delivery_logs;
DROP TABLE IF EXISTS po_line_items;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS stock_history;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;


CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Manager', 'Supervisor', 'Salesman', 'Logistic Handler') NOT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    purpose ENUM('account_setup', 'password_reset') NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    identifier VARCHAR(120) DEFAULT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_login_attempts_ip_attempted_at (ip, identifier, attempted_at)
);

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    contact_person VARCHAR(120) DEFAULT NULL,
    email VARCHAR(120) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    image_name VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(30) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    image_name VARCHAR(255) DEFAULT NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    min_threshold INT UNSIGNED NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    category_id INT UNSIGNED DEFAULT NULL,
    supplier_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    updated_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_products_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    movement_type ENUM('in', 'out', 'return', 'bulk_in') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    source_ref VARCHAR(120) DEFAULT NULL,
    unit_price DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movements_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(40) NOT NULL UNIQUE,
    supplier_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'received') NOT NULL DEFAULT 'pending',
    expected_date DATE DEFAULT NULL,
    carrier_name VARCHAR(120) DEFAULT NULL,
    tracking_number VARCHAR(120) DEFAULT NULL,
    dispatch_date DATE DEFAULT NULL,
    expected_arrival DATE DEFAULT NULL,
    shipment_status ENUM('order_placed', 'dispatched', 'in_transit', 'delivered') NOT NULL DEFAULT 'order_placed',
    status_updated_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_po_created_by FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS po_line_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_ordered INT UNSIGNED NOT NULL,
    quantity_received INT UNSIGNED DEFAULT NULL,
    unit_price DECIMAL(10,2) DEFAULT NULL,
    CONSTRAINT fk_po_lines_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_po_lines_product FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS delivery_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_ordered INT UNSIGNED NOT NULL,
    quantity_received INT UNSIGNED NOT NULL,
    date_received TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_by INT UNSIGNED NOT NULL,
    CONSTRAINT fk_delivery_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    CONSTRAINT fk_delivery_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_delivery_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_delivery_user FOREIGN KEY (received_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS sales_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    sale_date DATE NOT NULL,
    region VARCHAR(120) DEFAULT NULL,
    idempotency_key CHAR(64) DEFAULT NULL UNIQUE,
    source ENUM('manual_entry', 'import') NOT NULL DEFAULT 'manual_entry',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sales_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_sales_user FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS access_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL,
    desired_role ENUM('Supervisor', 'Salesman', 'Logistic Handler') NOT NULL,
    message VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    review_note VARCHAR(255) DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_access_review_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS report_import_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_type ENUM('csv', 'xlsx') NOT NULL,
    status ENUM('completed', 'failed') NOT NULL,
    imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_batch_user FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS report_import_row_errors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    row_index INT UNSIGNED NOT NULL,
    error_message VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_row_batch FOREIGN KEY (batch_id) REFERENCES report_import_batches(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id INT UNSIGNED NOT NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id INT UNSIGNED DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_log_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO categories (name, description)
SELECT * FROM (
    SELECT 'Grocery Staples' AS name, 'Daily pantry items and packaged essentials' AS description
    UNION ALL
    SELECT 'Beverages', 'Cold drinks, juices, water, and ready-to-serve beverages'
    UNION ALL
    SELECT 'Snacks', 'Biscuits, chips, confectionery, and quick-grab treats'
    UNION ALL
    SELECT 'Household', 'Cleaning supplies, paper goods, and home-care items'
    UNION ALL
    SELECT 'Personal Care', 'Toiletries, hygiene, and self-care products'
) AS seed_categories
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE categories.name = seed_categories.name);

-- Seed users require setting a password through the setup flow or environment variable in bootstrapping.
-- Default passwords are no longer hardcoded here for security.
INSERT INTO users (full_name, email, username, password_hash, role, is_active, must_change_password)
SELECT * FROM (
    SELECT
      'System Manager' AS full_name,
      'manager@inventra.local' AS email,
      'manager' AS username,
      '' AS password_hash,
      'Manager' AS role,
      1 AS is_active,
      1 AS must_change_password
    UNION ALL
    SELECT 'Sam Supervisor', 'supervisor@inventra.local', 'supervisor', '', 'Supervisor', 1, 1
    UNION ALL
    SELECT 'Leo Salesman', 'salesman@inventra.local', 'salesman', '', 'Salesman', 1, 1
    UNION ALL
    SELECT 'Mina Logistic', 'logistic@inventra.local', 'logistic', '', 'Logistic Handler', 1, 1
) AS seed_users
WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.username = seed_users.username);
