<?php

declare(strict_types=1);

class AuthController
{
    private const ROLE_PERMISSIONS = [
        'Manager' => [
            'dashboard', 'dashboard.alert_graph', 'dashboard.activity',
            'users.view', 'users.create', 'users.edit', 'users.deactivate',
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.archive', 'products.movement',
            'categories.view', 'categories.manage',
            'suppliers.view', 'suppliers.manage',
            'stock.view', 'stock.in', 'stock.out',
            'po.view', 'po.create', 'po.tracking', 'po.receive', 'logistics.delivery_log', 'logistics.reorder',
            'reports.inventory', 'reports.sales.monthly', 'reports.sales.daily', 'reports.low_stock', 'reports.stock_movement', 'reports.export', 'reports.import',
            'sales.record',
        ],
        'Supervisor' => [
            'dashboard', 'dashboard.alert_graph', 'dashboard.activity',
            'products.view', 'products.movement',
            'categories.view',
            'stock.view', 'stock.in', 'stock.out',
            'po.view',
            'logistics.reorder',
            'reports.sales.monthly', 'reports.sales.daily', 'reports.low_stock', 'reports.stock_movement',
        ],
        'Salesman' => [
            'dashboard', 'dashboard.activity',
            'products.view', 'categories.view',
            'stock.view', 'stock.out',
            'reports.sales.daily', 'reports.low_stock',
            'sales.record',
        ],
        'Logistic Handler' => [
            'products.view', 'categories.view',
            'stock.view', 'stock.in',
            'po.view', 'po.create', 'po.tracking', 'po.receive',
            'logistics.delivery_log', 'logistics.reorder',
        ],
    ];

    public function __construct(private readonly User $userModel)
    {
    }

    public function login(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        $errors = [];

        if ($identifier === '') {
            $errors[] = 'Email or username is required.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $user = $this->userModel->findByIdentifier($identifier);

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'errors' => ['Invalid email or password.']];
        }

        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            $_SESSION['password_setup_user_id'] = (int) $user['id'];
            return ['success' => true, 'requires_password_setup' => true];
        }

        $this->setAuthenticatedSession($user);

        return ['success' => true, 'landing_page' => $this->getLandingPageForRole((string) $user['role'])];
    }

    public function hasPendingPasswordSetup(): bool
    {
        return isset($_SESSION['password_setup_user_id']) && (int) $_SESSION['password_setup_user_id'] > 0;
    }

    public function completeFirstLoginPasswordSetup(string $password, string $confirmPassword): array
    {
        $errors = [];
        $user = $this->getPendingPasswordSetupUser();
        if (!$user) {
            return ['success' => false, 'errors' => ['Password setup session expired. Please sign in again.']];
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $this->userModel->markPasswordChanged(
            (int) $user['id'],
            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
        );
        unset($_SESSION['password_setup_user_id']);
        $this->setAuthenticatedSession($user);

        return ['success' => true, 'landing_page' => $this->getLandingPageForRole((string) $user['role'])];
    }

    public function getPendingPasswordSetupUser(): ?array
    {
        if (!$this->hasPendingPasswordSetup()) {
            return null;
        }

        $userId = (int) $_SESSION['password_setup_user_id'];
        $user = $this->userModel->findById($userId);
        if (!$user || !(int) $user['is_active']) {
            unset($_SESSION['password_setup_user_id']);
            return null;
        }

        $authRecord = $this->userModel->findByIdentifier((string) $user['email']);
        if (!$authRecord || (int) ($authRecord['must_change_password'] ?? 0) !== 1) {
            unset($_SESSION['password_setup_user_id']);
            return null;
        }

        return $authRecord;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function requireAuthentication(): void
    {
        if (!isLoggedIn()) {
            setFlash('error', 'Please sign in to continue.');
            redirectTo(basePath('index.php'));
        }
    }

    public function authorize(string $permission): void
    {
        if (!$this->can($permission)) {
            setFlash('error', 'You do not have permission to perform that action.');
            redirectTo(basePath('index.php?page=dashboard'));
        }
    }

    public function can(string $permission): bool
    {
        $role = currentUser()['role'] ?? null;
        $permissions = self::ROLE_PERMISSIONS[$role] ?? [];
        return in_array($permission, $permissions, true);
    }

    public function getLandingPageForRole(string $role): string
    {
        return match ($role) {
            'Salesman' => 'stock',
            'Logistic Handler' => 'purchase-orders',
            default => 'dashboard',
        };
    }

    private function setAuthenticatedSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }
}
