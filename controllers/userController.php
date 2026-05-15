<?php

declare(strict_types=1);

class UserController
{
    private User $userModel;
    private AuditLog $auditLog;

    public function __construct(User $userModel, AuditLog $auditLog)
    {
        $this->userModel = $userModel;
        $this->auditLog = $auditLog;
    }

    public function handleUpdate(int $userId, array $input, int $actorId): array
    {
        $validated = $this->validateUpdate($userId, $input);
        if ($validated['errors']) {
            return ['success' => false, 'errors' => $validated['errors']];
        }

        $oldUser = $this->userModel->findById($userId);
        $this->userModel->update($userId, $validated['data']);

        if ($oldUser && $oldUser['role'] !== $validated['data']['role']) {
            $this->auditLog->log($actorId, 'changed_role', 'user', $userId, [
                'old_role' => $oldUser['role'],
                'new_role' => $validated['data']['role']
            ]);
        }

        return ['success' => true];
    }

    public function handleDeactivate(int $userId, int $actorId): void
    {
        $this->userModel->deactivate($userId);
        $this->auditLog->log($actorId, 'deactivated', 'user', $userId);
    }

    public function validateCreate(array $input): array
    {
        $fullName = trim($input['full_name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $username = strtolower(trim($input['username'] ?? ''));
        $role = trim($input['role'] ?? '');

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

        return [
            'errors' => $errors,
            'data' => [
                'full_name' => $fullName,
                'email' => $email,
                'username' => $username,
                'role' => $role,
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]),
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
