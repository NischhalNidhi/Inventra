<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

$pdo = getDatabaseConnection();
$userModel = new User($pdo);

$password = env('SEED_MANAGER_PASSWORD');
if (empty($password)) {
    // Generate a secure random password if none is provided via environment
    $password = bin2hex(random_bytes(12));
    echo "NOTICE: Using randomly generated password for seed users: " . $password . "\n";
} else {
    echo "Using environment-provided password for seed users.\n";
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$seedUsers = [
    ['full_name' => 'Sam Supervisor', 'email' => 'supervisor@inventra.local', 'username' => 'supervisor', 'role' => 'Supervisor'],
    ['full_name' => 'Leo Salesman', 'email' => 'salesman@inventra.local', 'username' => 'salesman', 'role' => 'Salesman'],
    ['full_name' => 'Mina Logistic', 'email' => 'logistic@inventra.local', 'username' => 'logistic', 'role' => 'Logistic Handler'],
];

foreach ($seedUsers as $seed) {
    if ($userModel->usernameOrEmailExists($seed['username'], $seed['email'])) {
        continue;
    }
    $seed['password_hash'] = $hash;
    $seed['must_change_password'] = 1;
    $userModel->create($seed);
}

echo "Demo users seeded.\n";
