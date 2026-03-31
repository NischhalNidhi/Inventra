<?php

declare(strict_types=1);

class AccessRequestController
{
    public function __construct(
        private readonly AccessRequest $accessRequestModel,
        private readonly User $userModel
    ) {
    }

    public function validateCreate(array $input): array
    {
        $fullName = trim($input['full_name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $desiredRole = trim($input['desired_role'] ?? '');
        $message = trim($input['message'] ?? '');
        $errors = [];

        if ($fullName === '' || mb_strlen($fullName) < 3) {
            $errors[] = 'Full name must be at least 3 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (!in_array($desiredRole, ['Supervisor', 'Salesman', 'Logistic Handler'], true)) {
            $errors[] = 'Please select a valid requested role.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'full_name' => $fullName,
                'email' => $email,
                'desired_role' => $desiredRole,
                'message' => $message !== '' ? $message : null,
            ],
        ];
    }

    public function approveRequest(int $requestId, int $reviewerId, ?string $reviewNote = null): array
    {
        $request = $this->accessRequestModel->findById($requestId);
        if (!$request || $request['status'] !== 'pending') {
            throw new RuntimeException('Access request not found or already processed.');
        }

        $username = $this->generateUsername((string) $request['email']);
        $tempPassword = $this->generateTemporaryPassword();

        $this->userModel->create([
            'full_name' => $request['full_name'],
            'email' => $request['email'],
            'username' => $username,
            'role' => $request['desired_role'],
            'password_hash' => password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            'must_change_password' => 1,
        ]);

        $this->accessRequestModel->approve($requestId, $reviewerId, $reviewNote);

        return [
            'username' => $username,
            'temporary_password' => $tempPassword,
            'email' => $request['email'],
        ];
    }

    private function generateUsername(string $email): string
    {
        $base = strtolower((string) preg_replace('/[^a-z0-9._-]/', '', explode('@', $email)[0] ?? 'user'));
        $base = trim($base, '._-');
        if ($base === '') {
            $base = 'user';
        }
        $username = substr($base, 0, 24);
        $candidate = $username;
        $counter = 1;
        while ($this->userModel->usernameOrEmailExists($candidate, $email)) {
            $suffix = (string) $counter;
            $candidate = substr($username, 0, max(1, 30 - strlen($suffix))) . $suffix;
            $counter++;
        }
        return $candidate;
    }

    private function generateTemporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $result = '';
        for ($i = 0; $i < 10; $i++) {
            $result .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return 'Inv#' . $result;
    }
}
