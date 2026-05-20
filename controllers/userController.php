<?php

declare(strict_types=1);

class UserController
{
    private User $userModel;

    public function __construct(User $userModel)
    {
        $this->userModel = $userModel;
    }

    public function validateCreate(array $input): array
    {
        $fullName = trim($input['full_name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $username = strtolower(trim($input['username'] ?? ''));
        $role = trim($input['role'] ?? '');
        $password = trim($input['password'] ?? '');

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
        if ($this->userModel->usernameOrEmailExists($username, $email)) {
            $errors[] = 'Email or username already exists.';
        }
        
        if ($password !== '' && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'full_name' => $fullName,
                'email' => $email,
                'username' => $username,
                'role' => $role,
                'password_hash' => $password !== '' 
                    ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
                    : password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]),
                'manual_password' => $password !== '',
                'must_change_password' => $password !== '' ? 1 : 0
            ],
        ];
    }

    public function validateUpdate(int $userId, array $input): array
    {
        $fullName = trim($input['full_name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $username = strtolower(trim($input['username'] ?? ''));
        $role = trim($input['role'] ?? '');
        $password = trim($input['password'] ?? '');
        $passwordConfirm = trim($input['password_confirm'] ?? '');
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
        if ($this->userModel->usernameOrEmailExists($username, $email, $userId)) {
            $errors[] = 'Email or username already exists for another user.';
        }

        $passwordHash = null;
        $mustChangePassword = 0;
        if ($password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }
            if ($password !== $passwordConfirm) {
                $errors[] = 'New password confirmation does not match.';
            }
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
                $errors[] = 'New password must include uppercase, lowercase, a number, and a special character.';
            }
            if (empty($errors)) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $mustChangePassword = 0;
            }
        }

        return [
            'errors' => $errors,
            'data' => [
                'full_name' => $fullName,
                'email' => $email,
                'username' => $username,
                'role' => $role,
                'password_hash' => $passwordHash,
                'must_change_password' => $mustChangePassword,
            ],
        ];
    }
}