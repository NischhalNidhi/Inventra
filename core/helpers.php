<?php

declare(strict_types=1);

function loadEnvFile(?string $path = null): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    $envPath = $path ?? dirname(__DIR__) . '/.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

loadEnvFile();

if (session_status() === PHP_SESSION_NONE) {
    $defaultSessionPath = (string) session_save_path();
    if ($defaultSessionPath === '' || !is_dir($defaultSessionPath) || !is_writable($defaultSessionPath)) {
        $fallbackSessionPath = dirname(__DIR__) . '/uploads/.sessions';
        if (!is_dir($fallbackSessionPath)) {
            mkdir($fallbackSessionPath, 0775, true);
        }
        if (is_dir($fallbackSessionPath) && is_writable($fallbackSessionPath)) {
            session_save_path($fallbackSessionPath);
        }
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => ($_ENV['APP_ENV'] ?? '') === 'production',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function basePath(string $path = ''): string
{
    $base = env('APP_BASE_PATH', '/inventory-system/public');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function appRootPath(string $path = ''): string
{
    $root = preg_replace('#/public$#', '', basePath()) ?: '/inventory-system';
    return $path === '' ? $root : $root . '/' . ltrim($path, '/');
}

function appUrl(string $path = ''): string
{
    $baseUrl = rtrim(env('APP_URL', ''), '/');
    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host . basePath();
    }

    return $path === '' ? $baseUrl : $baseUrl . '/' . ltrim($path, '/');
}

function redirectTo(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function persistOldInput(array $data): void
{
    $_SESSION['old'] = $data;
}

function clearOldInput(): void
{
    unset($_SESSION['old']);
}

function selectedIf(string $actual, string $expected): string
{
    return $actual === $expected ? 'selected' : '';
}

function parsePagination(array $input): array
{
    $page = max(1, (int) ($input['p'] ?? 1));
    $limit = max(1, min(100, (int) ($input['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}

function todayDate(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d');
}
