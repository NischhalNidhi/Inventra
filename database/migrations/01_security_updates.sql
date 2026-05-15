-- Security Updates Migration
-- Execute this manually to apply schema changes to an existing database.

-- 1. Create a least-privilege DB user (Run these with a strong password and uncomment)
-- CREATE USER 'inventra_app'@'127.0.0.1' IDENTIFIED BY '<strong-random-password>';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON inventra.* TO 'inventra_app'@'127.0.0.1';
-- FLUSH PRIVILEGES;

-- 2. Add idempotency_key to sales_transactions
ALTER TABLE sales_transactions 
ADD COLUMN idempotency_key CHAR(64) DEFAULT NULL UNIQUE AFTER region;

-- 2b. Add identifier to login_attempts
ALTER TABLE login_attempts 
ADD COLUMN identifier VARCHAR(120) DEFAULT NULL AFTER ip;

-- 3. Create audit_log table
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
