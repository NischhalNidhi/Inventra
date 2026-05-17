<?php
/**
 * Diagnostic script to verify all expected dependencies are present.
 */
declare(strict_types=1);

require_once __DIR__ . '/core/dependencies.php';

echo "--- Dependency Audit ---\n";

$deps = buildAppDependencies();

$expectedKeys = [
    // Models
    'userModel', 'categoryModel', 'productModel', 'poModel', 
    'reportModel', 'supplierModel', 'stockModel',
    // Controllers
    'authController', 'userController', 'categoryController', 
    'productController', 'poController', 'reportController', 
    'supplierController', 'stockController',
    // Services/Core
    'pdo', 'mailer', 'aiSalesInsightService'
];

$missing = [];
foreach ($expectedKeys as $key) {
    if (!isset($deps[$key])) {
        $missing[] = $key;
    }
}

if (empty($missing)) {
    echo "✅ Success: All core dependencies are correctly registered in dependencies.php\n";
} else {
    echo "❌ Error: The following dependencies are MISSING from the return array in dependencies.php:\n";
    foreach ($missing as $m) {
        echo "   - $m\n";
    }
    echo "\nCheck the return statement in buildAppDependencies() in core/dependencies.php\n";
}