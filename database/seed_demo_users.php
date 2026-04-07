<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';

$pdo = getDatabaseConnection();
$userModel = new User($pdo);
$hash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);

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
    $userModel->create($seed);
}

echo "Demo users seeded.\n";
