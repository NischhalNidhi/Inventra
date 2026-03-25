-- ============================================================
--  Inventra – Full Database Schema (v2 – RBAC update)
--  Run this in phpMyAdmin or the MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS inventra
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE inventra;

-- ============================================================
-- users table  (updated: role ENUM now has 4 real roles)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id         INT          NOT NULL AUTO_INCREMENT,
    username   VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('manager','supervisor','salesman','logistic') NOT NULL DEFAULT 'salesman',
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,   -- 1=active, 0=deactivated
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If the table already exists (with the old admin/user enum), run this to ALTER it:
-- ALTER TABLE users
--   MODIFY COLUMN role ENUM('manager','supervisor','salesman','logistic')
--   NOT NULL DEFAULT 'salesman';

-- ============================================================
-- Test users  (passwords are set by setup_db.php)
-- ============================================================
-- Run setup_db.php in the browser to auto-insert these with real hashes.
-- Manual insert reference (replace <hash> with a real bcrypt hash):
--
-- INSERT INTO users (username, password, role) VALUES
-- ('manager1',  '<hash>', 'manager'),
-- ('supervisor1','<hash>', 'supervisor'),
-- ('salesman1', '<hash>', 'salesman'),
-- ('logistic1', '<hash>', 'logistic');