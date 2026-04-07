CREATE DATABASE IF NOT EXISTS inventra;
USE inventra;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS report_import_row_errors;
DROP TABLE IF EXISTS report_import_batches;
DROP TABLE IF EXISTS sales_transactions;
<<<<<<< HEAD
DROP TABLE IF EXISTS access_requests;
=======
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508
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
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
<<<<<<< HEAD
=======
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508
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
<<<<<<< HEAD
=======
    unit_price DECIMAL(10,2) DEFAULT NULL,
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508
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
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
    unit_price DECIMAL(10,2) DEFAULT NULL,
>>>>>>> 852434b589abba0bafe28d124996a032be2a96d0
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508
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
    source ENUM('manual_entry', 'import') NOT NULL DEFAULT 'manual_entry',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sales_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_sales_user FOREIGN KEY (created_by) REFERENCES users(id)
);

<<<<<<< HEAD
=======
<<<<<<< Updated upstream
<<<<<<< HEAD
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508
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

<<<<<<< HEAD
=======
=======
>>>>>>> 852434b589abba0bafe28d124996a032be2a96d0
=======
>>>>>>> Stashed changes
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508
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

INSERT INTO categories (name, description)
SELECT * FROM (
    SELECT 'Electronics' AS name, 'Electronic modules and controls' AS description
    UNION ALL
    SELECT 'Mechanical', 'Mechanical and machine components'
    UNION ALL
    SELECT 'Safety', 'Safety and compliance equipment'
    UNION ALL
    SELECT 'Logistics', 'Warehouse and logistics equipment'
) AS seed_categories
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE categories.name = seed_categories.name);

INSERT INTO users (full_name, email, username, password_hash, role, is_active)
SELECT * FROM (
    SELECT
      'System Manager' AS full_name,
      'manager@inventra.local' AS email,
      'manager' AS username,
      '$2y$12$g9t01Mpot.2IjSQCVU7K0eccXXo.nP8uTHDsumOl6X9WRzAfrwqR.' AS password_hash,
      'Manager' AS role,
      1 AS is_active
    UNION ALL
    SELECT 'Sam Supervisor', 'supervisor@inventra.local', 'supervisor', '$2y$12$g9t01Mpot.2IjSQCVU7K0eccXXo.nP8uTHDsumOl6X9WRzAfrwqR.', 'Supervisor', 1
    UNION ALL
    SELECT 'Leo Salesman', 'salesman@inventra.local', 'salesman', '$2y$12$g9t01Mpot.2IjSQCVU7K0eccXXo.nP8uTHDsumOl6X9WRzAfrwqR.', 'Salesman', 1
    UNION ALL
    SELECT 'Mina Logistic', 'logistic@inventra.local', 'logistic', '$2y$12$g9t01Mpot.2IjSQCVU7K0eccXXo.nP8uTHDsumOl6X9WRzAfrwqR.', 'Logistic Handler', 1
) AS seed_users
WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.username = seed_users.username);
