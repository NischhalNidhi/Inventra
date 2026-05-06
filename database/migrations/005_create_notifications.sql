-- Low stock alert notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('low_stock', 'out_of_stock', 'po_update', 'system') NOT NULL DEFAULT 'low_stock',
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NOT NULL,
    product_id INT UNSIGNED DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_emailed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, is_read),
    INDEX idx_notifications_created (created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Tracks which low-stock alerts have already been generated to prevent duplicates
CREATE TABLE IF NOT EXISTS low_stock_alert_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    alert_type ENUM('low_stock', 'out_of_stock') NOT NULL,
    stock_at_alert INT UNSIGNED NOT NULL,
    threshold_at_alert INT UNSIGNED NOT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alert_log_product (product_id, resolved_at),
    CONSTRAINT fk_alert_log_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
