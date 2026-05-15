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
    if (!is_file($envPath)) {
        if ($path === null) {
            $envPath = dirname(__DIR__) . '/.env.example';
        }
    }
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

        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }
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
    $base = env('APP_BASE_PATH');
    if ($base === null) {
        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = rtrim($base, '/');
    }
    return $path === '' ? ($base === '' ? '/' : $base) : $base . '/' . ltrim($path, '/');
}

function appRootPath(string $path = ''): string
{
    $root = (string) preg_replace('#/public$#', '', basePath());
    $root = $root === '' ? '/' : $root;
    return $path === '' ? $root : rtrim($root, '/') . '/' . ltrim($path, '/');
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

function placeholderImageUrl(string $label, string $theme = 'product'): string
{
    $safeLabel = trim($label) !== '' ? trim($label) : ($theme === 'supplier' ? 'Supplier' : 'Product');
    $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $safeLabel) ?: 'IMG', 0, 2));

    [$start, $end, $accent] = $theme === 'supplier'
        ? ['#1f3a8a', '#0f766e', '#dbeafe']
        : ['#4338ca', '#7c3aed', '#ede9fe'];

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 480">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$start}"/>
      <stop offset="100%" stop-color="{$end}"/>
    </linearGradient>
  </defs>
  <rect width="640" height="480" fill="url(#g)"/>
  <circle cx="520" cy="100" r="84" fill="rgba(255,255,255,0.12)"/>
  <circle cx="120" cy="390" r="96" fill="rgba(255,255,255,0.10)"/>
  <rect x="64" y="64" rx="28" ry="28" width="512" height="352" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.18)"/>
  <text x="320" y="230" text-anchor="middle" fill="{$accent}" font-family="Inter, Arial, sans-serif" font-size="132" font-weight="800">{$initials}</text>
  <text x="320" y="310" text-anchor="middle" fill="rgba(255,255,255,0.9)" font-family="Inter, Arial, sans-serif" font-size="34" font-weight="600">{$safeLabel}</text>
</svg>
SVG;

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function mediaUrl(?string $relativeUploadPath, string $label, string $theme = 'product'): string
{
    $normalized = trim((string) $relativeUploadPath, '/');
    if ($normalized !== '') {
        return basePath('uploads/' . $normalized);
    }

    return placeholderImageUrl($label, $theme);
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

/**
 * Generate and trigger a CSV file download to the client.
 *
 * @param string $filename Base filename (without extension).
 * @param array  $headers  Column header names.
 * @param array  $rows     Data rows; each row should be an associative array with keys matching headers.
 */
function downloadCsv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    
    // Write BOM for UTF-8 to support special characters in Excel.
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Write header row.
    fputcsv($output, $headers);
    
    // Write data rows.
    foreach ($rows as $row) {
        if (is_array($row)) {
            // Handle both associative and numeric array rows.
            $values = array_values($row);
        } else {
            $values = [$row];
        }
        fputcsv($output, $values);
    }
    
    fclose($output);
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
