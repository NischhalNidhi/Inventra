<?php

declare(strict_types=1);

class AuthController
{
    private const ROLE_PERMISSIONS = [
        'Manager' => [
            'dashboard',
            'dashboard.alert_graph',
            'dashboard.activity',
            'users.view',
            'users.create',
            'users.edit',
            'users.deactivate',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'products.archive',
            'products.movement',
            'categories.view',
            'categories.manage',
            'suppliers.view',
            'suppliers.manage',
            'stock.view',
            'stock.in',
            'stock.out',
            'po.view',
            'po.create',
            'po.tracking',
            'po.receive',
            'logistics.delivery_log',
            'logistics.reorder',
            'reports.inventory',
            'reports.sales.monthly',
            'reports.sales.daily',
            'reports.low_stock',
            'reports.stock_movement',
            'reports.export',
            'reports.import',
            'reports.sales.insight',
            'sales.record',
        ],
        'Supervisor' => [
            'dashboard',
            'dashboard.alert_graph',
            'dashboard.activity',
            'products.view',
            'products.movement',
            'categories.view',
            'stock.view',
            'stock.in',
            'stock.out',
            'po.view',
            'logistics.reorder',
            'reports.sales.monthly',
            'reports.sales.daily',
            'reports.low_stock',
            'reports.stock_movement',
        ],
        'Salesman' => [
            'dashboard',
            'dashboard.activity',
            'products.view',
            'categories.view',
            'stock.view',
            'stock.out',
            'reports.sales.daily',
            'reports.low_stock',
            'sales.record',
        ],
        'Logistic Handler' => [
            'products.view',
            'categories.view',
            'stock.view',
            'stock.in',
            'po.view',
            'po.create',
            'po.tracking',
            'po.receive',
            'logistics.delivery_log',
            'logistics.reorder',
        ],
    ];

    private User $userModel;
    private Mailer $mailer;
    private PDO $pdo;

    public function __construct(User $userModel, Mailer $mailer, PDO $pdo)
    {
        $this->userModel = $userModel;
        $this->mailer = $mailer;
        $this->pdo = $pdo;
    }

    public function login(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
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

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $this->checkRateLimit($ip, $identifier);

        $user = $this->userModel->findByIdentifier($identifier);

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            $this->recordAttempt($ip, $identifier);
            return ['success' => false, 'errors' => ['Invalid email or password.']];
        }

        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            $_SESSION['password_setup_user_id'] = (int) $user['id'];
            return ['success' => true, 'requires_password_setup' => true];
        }

        $this->setAuthenticatedSession($user);

        return ['success' => true, 'landing_page' => $this->getLandingPageForRole((string) $user['role'])];
    }

    public function sendAccountSetupEmail(array $user): array
    {
        $token = $this->createPasswordToken((int) $user['id'], 'account_setup', 24 * 60 * 60);
        $link = appUrl('index.php?mode=set-password&token=' . urlencode($token));
        $sent = $this->mailer->sendAccountSetup(
            (string) $user['email'],
            (string) $user['full_name'],
            (string) $user['role'],
            $link
        );

        if (!$sent) {
            return ['success' => false, 'errors' => ['Account was created, but the welcome email could not be sent. Check mail configuration.']];
        }

        return ['success' => true];
    }

    public function requestPasswordReset(string $email): array
    {
        $email = strtolower(trim($email));
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $this->checkRateLimit($ip, $email);
        $this->recordAttempt($ip, $email);

        $user = $this->userModel->findActiveByIdentifier($email);
        if ($user) {
            $token = $this->createPasswordToken((int) $user['id'], 'password_reset', 60 * 60);
            $link = appUrl('index.php?mode=reset-password&token=' . urlencode($token));
            $this->mailer->sendPasswordReset(
                (string) $user['email'],
                (string) $user['full_name'],
                $link
            );
        }

        return [
            'success' => true,
            'message' => "If this email is registered, you'll receive a reset link shortly.",
        ];
    }

    public function getTokenState(string $token, string $purpose): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['valid' => false, 'expired' => false, 'user' => null];
        }

        $record = $this->userModel->findPasswordToken(hash('sha256', $token), $purpose);
        if (!$record) {
            return ['valid' => false, 'expired' => false, 'user' => null];
        }

        if (strtotime((string) $record['expires_at']) < time()) {
            $this->userModel->deletePasswordTokenByHash((string) $record['token_hash']);
            return ['valid' => false, 'expired' => true, 'user' => $record];
        }

        return ['valid' => true, 'expired' => false, 'user' => $record];
    }

    public function completePasswordTokenSetup(string $token, string $purpose, string $password, string $confirmPassword): array
    {
        $state = $this->getTokenState($token, $purpose);
        if (!$state['valid']) {
            return ['success' => false, 'errors' => [$state['expired'] ? 'This link has expired. Please request a new one.' : 'This link is invalid or has already been used.']];
        }

        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $record = $state['user'];
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        if ($purpose === 'account_setup') {
            $this->userModel->activateWithPassword((int) $record['user_id'], $passwordHash);
        } else {
            $this->userModel->markPasswordChanged((int) $record['user_id'], $passwordHash);
        }

        $this->userModel->deletePasswordTokenByHash((string) $record['token_hash']);

        return ['success' => true];
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
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Strict'
            ]);
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
            'Salesman' => 'products',
            'Logistic Handler' => 'purchase-orders',
            default => 'dashboard',
        };
    }

    private function createPasswordToken(int $userId, string $purpose, int $ttlSeconds): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $this->userModel->createPasswordToken($userId, $purpose, hash('sha256', $token), $expiresAt);

        return $token;
    }

    private function setAuthenticatedSession(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }

    private function checkRateLimit(string $ip, string $identifier = ''): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts 
             WHERE (ip = :ip OR identifier = :identifier) AND attempted_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
        );
        $stmt->execute(['ip' => $ip]);

        if ((int) $stmt->fetchColumn() >= 10) {
            throw new RuntimeException('Too many attempts. Please try again in 5 minutes.');
        }
    }

    private function recordAttempt(string $ip, string $identifier = ''): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (ip, identifier, attempted_at) VALUES (:ip, :identifier, NOW())'
        );
        $stmt->execute(['ip' => $ip, 'identifier' => $identifier]);
    }
}
