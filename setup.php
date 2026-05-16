<?php

/**
 * Inventra - Automated Setup Script
 * Sets up environment and database for first-time use.
 */

declare(strict_types=1);

echo "--- Inventra Setup ---\n";

// 1. Check Requirements
if (!extension_loaded('pdo_mysql')) {
    die("Error: PDO MySQL extension is required.\n");
}
if (!extension_loaded('curl')) {
    die("Error: cURL extension is required.\n");
}

// 2. Setup Environment
$root = __DIR__;
if (!is_file("$root/.env")) {
    echo "Creating .env from .env.example...\n";
    if (!copy("$root/.env.example", "$root/.env")) {
        die("Error: Could not copy .env.example. Please check permissions.\n");
    }
} else {
    echo ".env already exists, skipping copy.\n";
}

// 3. Initialize Database
require_once "$root/database/bootstrap.php";

try {
    echo "Initializing database schema...\n";
    initializeConfiguredDatabase();
    echo "Database initialized successfully.\n";
} catch (Throwable $e) {
    die("Error during database initialization: " . $e->getMessage() . "\n");
}

// 4. Seed Database
echo "Seeding database with demo data...\n";
require_once "$root/database/seed.php";

echo "\nSetup complete! You can now access Inventra.\n";
echo "Default Manager: manager / password\n";
