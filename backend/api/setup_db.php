<?php
// ============================================================
//  backend/api/setup_db.php  (v2 – 4-role RBAC update)
//
//  ONE-TIME SETUP SCRIPT – visit once, then delete.
//
//  http://localhost/Inventory-Management-System/Inventra/backend/api/setup_db.php
//
//  What it does:
//    1. Creates inventra database
//    2. Creates / migrates users table to 4-role ENUM
//    3. Seeds one test user per role (all password: 1234)
//
//  DELETE THIS FILE after setup!
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Inventra DB Setup v2</title>
  <style>
    body { font-family: monospace; background: #0f1117; color: #e2e8f0; padding: 40px; }
    h2   { color: #818cf8; }
    .ok  { color: #4ade80; }
    .err { color: #f87171; }
    .warn{ color: #facc15; }
    pre  { background: #1a1d27; padding: 20px; border-radius: 8px; line-height: 1.8; }
    h3   { color: #94a3b8; margin-top: 0; }
  </style>
</head>
<body>
<h2>🔧 Inventra Database Setup <span style="font-size:.75em;color:#64748b">v2 – RBAC</span></h2>
<pre>
<?php

$conn = new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    echo "<span class='err'>✗ MySQL connection failed: " . $conn->connect_error . "</span>\n";
    exit;
}
echo "<span class='ok'>✓ Connected to MySQL</span>\n";

// 1. Database
$conn->query("CREATE DATABASE IF NOT EXISTS inventra
              CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "<span class='ok'>✓ Database 'inventra' ready</span>\n";
$conn->select_db('inventra');

// 2. Create users table with 4-role ENUM (IF NOT EXISTS)
$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id         INT          NOT NULL AUTO_INCREMENT,
        username   VARCHAR(100) NOT NULL UNIQUE,
        password   VARCHAR(255) NOT NULL,
        role       ENUM('manager','supervisor','salesman','logistic') NOT NULL DEFAULT 'salesman',
        is_active  TINYINT(1)   NOT NULL DEFAULT 1,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "<span class='ok'>✓ Table 'users' created / verified</span>\n";

// 3. Migrate existing table if it still has old admin/user ENUM
//    (safe to run even if already migrated)
$alter = $conn->query("
    ALTER TABLE users
    MODIFY COLUMN role ENUM('manager','supervisor','salesman','logistic')
    NOT NULL DEFAULT 'salesman'
");
if ($alter) {
    echo "<span class='ok'>✓ Role ENUM migrated to 4-role system</span>\n";
} else {
    echo "<span class='warn'>⚠ ENUM already up to date</span>\n";
}

// Add is_active column if it doesn't exist yet
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
echo "<span class='ok'>✓ Extra columns (is_active, created_at) ready</span>\n";


// 4. Seed one user per role
$hash = password_hash('1234', PASSWORD_BCRYPT);
echo "\n<span class='ok'>✓ Password hash generated for '1234'</span>\n\n";

$test_users = [
    ['manager1',    $hash, 'manager'],
    ['supervisor1', $hash, 'supervisor'],
    ['salesman1',   $hash, 'salesman'],
    ['logistic1',   $hash, 'logistic'],
];

foreach ($test_users as [$username, $pw_hash, $role]) {
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, role)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role)
    ");
    $stmt->bind_param('sss', $username, $pw_hash, $role);
    $stmt->execute();
    echo "<span class='ok'>✓ $username ($role)</span>\n";
    $stmt->close();
}

// 5. Show all users
echo "\n<b>All users in database:</b>\n";
$res = $conn->query("SELECT id, username, role, is_active FROM users ORDER BY id");
while ($row = $res->fetch_assoc()) {
    $active = $row['is_active'] ? '✓' : '✗';
    echo "  [{$active}] id={$row['id']}  username={$row['username']}  role={$row['role']}\n";
}

echo "\n<span class='ok'>✅ Setup complete!</span>\n\n";
echo "Login URL:\n";
echo "  http://localhost/Inventory-Management-System/Inventra/frontend/login.html\n\n";
echo "Test credentials (all password: 1234):\n";
echo "  manager1    → full access\n";
echo "  supervisor1 → stock, reports, dashboard\n";
echo "  salesman1   → stock out, basic reports\n";
echo "  logistic1   → stock in, purchase orders\n";
echo "\n<span class='err'>⚠  DELETE this file after setup!</span>\n";

$conn->close();
?>
</pre>
</body>
</html>
