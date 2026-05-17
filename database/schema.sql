-- Inventra - Unified Database Schema and Seed Data
-- This file contains the complete database structure and demo data.

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

-- 1. Tables Structure

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
    attempted_at DATETIME NOT NULL,
    INDEX idx_login_attempts_ip_attempted_at (ip, attempted_at)
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
    INDEX idx_products_archived_updated (is_archived, updated_at),
    INDEX idx_products_category_archived (category_id, is_archived),
    INDEX idx_products_supplier_archived (supplier_id, is_archived),
    INDEX idx_products_stock_threshold (is_archived, stock_quantity, min_threshold),
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
    INDEX idx_movements_product_created (product_id, created_at),
    INDEX idx_movements_created (created_at),
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
    INDEX idx_purchase_orders_status (status, created_at),
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
    invoice_id VARCHAR(40) DEFAULT NULL,
    branch_code VARCHAR(10) DEFAULT NULL,
    city VARCHAR(120) DEFAULT NULL,
    customer_type VARCHAR(30) DEFAULT NULL,
    customer_gender VARCHAR(20) DEFAULT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    sale_date DATE NOT NULL,
    sale_time TIME DEFAULT NULL,
    sold_at DATETIME DEFAULT NULL,
    region VARCHAR(120) DEFAULT NULL,
    payment_method VARCHAR(40) DEFAULT NULL,
    tax_amount DECIMAL(10,4) DEFAULT NULL,
    gross_total DECIMAL(10,4) DEFAULT NULL,
    cogs DECIMAL(10,4) DEFAULT NULL,
    gross_margin_percentage DECIMAL(10,6) DEFAULT NULL,
    gross_income DECIMAL(10,4) DEFAULT NULL,
    rating DECIMAL(4,2) DEFAULT NULL,
    source ENUM('manual_entry', 'import') NOT NULL DEFAULT 'manual_entry',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sales_sale_date (sale_date),
    INDEX idx_sales_product_date (product_id, sale_date),
    INDEX idx_sales_invoice_id (invoice_id),
    INDEX idx_sales_branch_city (branch_code, city),
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

-- 2. Seed Data (Core Users and Categories)

INSERT INTO users (id, full_name, email, username, password_hash, role, is_active) VALUES
(1, 'System Manager', 'manager@inventra.local', 'manager', '$2y$12$fuzGDrJ18sy15/BTjMLJyuvMAKUV1Tls9NQ7mzZU0SzKxZujsbdYe', 'Manager', 1);

INSERT INTO categories (id, name, description) VALUES
(1, 'Beverages', 'Soft drinks, milk, juices'),
(2, 'Grocery Staples', 'Rice, bread, flour'),
(3, 'Household', 'Cleaning supplies'),
(4, 'Snacks', 'Chips, biscuits'),
(5, 'Personal Care', 'Shampoo, soap'),
(6, 'Electronics', 'Gadgets and accessories'),
(7, 'Apparel', 'Clothing and footwear'),
(8, 'Home & Kitchen', 'Appliances and decor'),
(9, 'Stationery', 'Office and school supplies');

INSERT INTO suppliers (id, name, contact_person, email, phone) VALUES
(1, 'National Foods', 'John Doe', 'john@national.local', '123456789'),
(2, 'CleanHome Co', 'Jane Doe', 'jane@clean.local', '987654321'),
(3, 'Pantry Express', 'Mike Ross', 'mike@pantry.local', '555-0199'),
(4, 'TechNova Solutions', 'Sarah Chen', 'sarah@technova.local', '555-0200'),
(5, 'Global Trends Inc', 'David Miller', 'david@globaltrends.local', '555-0201'),
(6, 'KitchenPro', 'Elena Rodriguez', 'elena@kitchenpro.local', '555-0202'),
(7, 'Office Supply Co', 'Pam Beesly', 'pam@officesupply.local', '555-0300');

INSERT INTO products (id, name, sku, description, image_name, stock_quantity, min_threshold, unit_price, category_id, supplier_id, created_by, updated_by) VALUES
(1, 'Full Cream Milk 1L', 'BVG-MILK-1', 'Fresh full cream milk from local farms', 'milk.png', 50, 20, 120.00, 1, 1, 1, 1),
(2, 'Classic Cola 500ml', 'BVG-COLA-1', 'Refreshing carbonated soft drink', 'cola.png', 120, 30, 65.00, 1, 1, 1, 1),
(3, 'Green Tea Bags (25 Pack)', 'BVG-TEA-G', 'Pure organic green tea for daily wellness', 'tea.png', 80, 20, 180.00, 1, 1, 1, 1),
(4, 'Organic Whole Wheat Bread', 'GRC-BREAD-1', 'Freshly baked whole wheat bread daily', 'bread.png', 30, 15, 80.00, 2, 1, 1, 1),
(5, 'Basmati Rice 5kg', 'GRC-RICE-5', 'Premium long grain basmati rice for fine dining', 'rice.png', 60, 15, 850.00, 2, 3, 1, 1),
(6, 'Sunflower Cooking Oil 1L', 'GRC-OIL-1', 'Refined sunflower oil for healthy cooking', 'oil.png', 45, 12, 280.00, 2, 3, 1, 1),
(7, 'All-Purpose Cleaner', 'HHD-CLN-1', 'Tough on stains, gentle on surfaces', 'cleaner.png', 40, 10, 250.00, 3, 2, 1, 1),
(8, 'Dishwashing Liquid 500ml', 'HHD-DISH-1', 'Cuts through grease effectively', 'dishwash.png', 45, 12, 120.00, 3, 2, 1, 1),
(9, 'Paper Napkins (50 Pack)', 'HHD-NAP-1', 'Soft and absorbent 2-ply napkins', 'napkins.png', 200, 50, 45.00, 3, 7, 1, 1),
(10, 'Potato Chips (Classic)', 'SNK-CHIPS-1', 'Crunchy salted classic potato chips', 'chips.png', 100, 30, 50.00, 4, 1, 1, 1),
(11, 'Digestive Biscuits', 'SNK-BIS-1', 'High-fiber digestive biscuits for tea time', 'biscuits.png', 60, 20, 95.00, 4, 1, 1, 1),
(12, 'Dark Chocolate Bar', 'SNK-CHOC-1', '70% cocoa rich dark chocolate', 'chocolate.png', 50, 15, 150.00, 4, 5, 1, 1),
(13, 'Anti-Dandruff Shampoo', 'PC-SHMP-1', 'Scalp care shampoo for healthy hair', 'shampoo.png', 25, 10, 350.00, 5, 2, 1, 1),
(14, 'Moisturizing Soap Bar', 'PC-SOAP-1', 'Enriched with vitamin E for soft skin', 'soap.png', 150, 40, 45.00, 5, 2, 1, 1),
(15, 'Gentle Face Wash', 'PC-FWASH-1', 'Daily face wash for all skin types', 'facewash.png', 40, 15, 195.00, 5, 2, 1, 1),
(16, 'AA Alkaline Batteries', 'ELC-BATT-AA', 'Long-lasting power for everyday devices', 'batteries.png', 300, 100, 250.00, 6, 4, 1, 1),
(17, 'USB-C Charging Cable', 'ELC-CABLE-C', 'Fast charging and data sync cable 1m', 'cable.png', 80, 20, 450.00, 6, 4, 1, 1),
(18, 'LED Desk Lamp', 'ELC-LAMP-1', 'Eye-friendly adjustable LED lamp', 'lamp.png', 15, 5, 1200.00, 6, 4, 1, 1),
(19, 'A5 Spiral Notebook', 'STN-NB-A5', 'High-quality ruled paper notebook', 'notebook.png', 120, 30, 85.00, 9, 7, 1, 1),
(20, 'Ballpoint Pens (Blue)', 'STN-PEN-B', 'Smooth writing blue ink pens (Pack of 10)', 'pens.png', 100, 25, 120.00, 9, 7, 1, 1),
(21, 'Cotton Crew Neck T-Shirt', 'APR-TSH-1', '100% breathable cotton t-shirt', 'tshirt.png', 50, 15, 550.00, 7, 5, 1, 1);

-- 3. Sample Transactions (Last 30 days)

INSERT INTO sales_transactions (invoice_id, product_id, quantity, unit_price, sale_date, sale_time, region, payment_method, customer_type, created_by) VALUES
('INV-S001', 1, 2, 120.00, CURDATE(), '10:30:00', 'North Store', 'Cash', 'Member', 1),
('INV-S002', 5, 1, 850.00, CURDATE(), '11:15:00', 'Central Hub', 'Credit Card', 'Normal', 1),
('INV-S003', 10, 3, 50.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:20:00', 'East Mall', 'Mobile Pay', 'Member', 1),
('INV-S004', 16, 2, 250.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '16:45:00', 'West Plaza', 'Debit Card', 'Normal', 1),
('INV-S005', 2, 5, 65.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00', 'South Store', 'Cash', 'Member', 1),
('INV-S006', 7, 1, 250.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '12:30:00', 'North Store', 'Credit Card', 'Normal', 1),
('INV-S007', 12, 1, 150.00, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '15:10:00', 'Central Hub', 'Mobile Pay', 'Member', 1),
('INV-S008', 19, 2, 85.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '11:00:00', 'East Mall', 'Cash', 'Normal', 1),
('INV-S009', 4, 2, 80.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), '13:20:00', 'West Plaza', 'Credit Card', 'Member', 1),
('INV-S010', 8, 3, 120.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY), '10:45:00', 'South Store', 'Debit Card', 'Normal', 1);

-- Note: Historical analytics data is usually generated via PHP for larger datasets.
-- This SQL file provides the essential structure and initial data for a usable system.
