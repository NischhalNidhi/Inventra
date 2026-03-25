<?php
// ============================================================
//  backend/api/php_hash_helper.php
//
//  DEVELOPMENT TOOL – DO NOT deploy to production.
//
//  Open in browser:
//  http://localhost/Inventory-Management-System/Inventra/backend/api/php_hash_helper.php
//
//  Copy the hash shown and paste it into database/schema.sql
//  as the password value for your test users.
// ============================================================

$passwords = ['1234', 'admin123', 'password'];

echo "<pre style='font-family:monospace;font-size:14px;padding:20px'>";
echo "=== Bcrypt Hash Generator ===\n\n";

foreach ($passwords as $pw) {
    $hash = password_hash($pw, PASSWORD_BCRYPT);
    echo "Plain text : $pw\n";
    echo "Bcrypt hash: $hash\n\n";
}

echo "Usage in schema.sql:\n";
echo "  INSERT INTO users (username, password, role) VALUES\n";
echo "  ('admin', '<paste hash here>', 'admin');\n";
echo "</pre>";
