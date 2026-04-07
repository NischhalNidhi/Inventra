<?php

declare(strict_types=1);

class UserController
{
    public function __construct(private readonly User $userModel)
    {
    }

    public function validateCreate(array $input): array
    {
        $fullName = trim($input['full_name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $username = strtolower(trim($input['username'] ?? ''));
        $role = trim($input['role'] ?? '');
        $password = (string) ($input['password'] ?? '');

        $errors = [];
        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3-30 chars using lowercase letters, numbers, dot, underscore, hyphen.';
        }
        if (!in_array($role, ['Manager', 'Supervisor', 'Salesman', 'Logistic Handler'], true)) {
            $errors[] = 'Invalid role.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($this->userModel->usernameOrEmailExists($username, $email)) {
            $errors[] = 'Email or username already exists.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'full_name' => $fullName,
                'email' => $email,
                'username' => $username,
                'role' => $role,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            ],
        ];
    }

    public function validateUpdate(int $userId, array $input): array
    {
        $fullName = trim($input['full_name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $role = trim($input['role'] ?? '');
        $errors = [];

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (!in_array($role, ['Manager', 'Supervisor', 'Salesman', 'Logistic Handler'], true)) {
            $errors[] = 'Invalid role.';
        }

        $existing = $this->userModel->findById($userId);
        if (!$existing) {
            $errors[] = 'User not found.';
        } elseif ($this->userModel->usernameOrEmailExists($existing['username'], $email, $userId)) {
            $errors[] = 'Email already exists.';
        }

        return [
            'errors' => $errors,
            'data' => ['full_name' => $fullName, 'email' => $email, 'role' => $role],
        ];
    }
}
