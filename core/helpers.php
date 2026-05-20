<?php

declare(strict_types=1);

function loadEnvFile(?string $path = null): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    $root    = dirname(__DIR__);
    $envPath = $path ?? $root . '/.env';

    // On a fresh clone, .env won't exist — auto-copy .env.example so the app
    // works immediately without any manual setup step.
    if (!is_file($envPath) && $path === null) {
        $example = $root . '/.env.example';
        if (is_file($example) && is_writable($root)) {
            @copy($example, $envPath);
        }
        if (!is_file($envPath)) {
            $envPath = $example; // read example directly if copy failed
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
    // Use APP_BASE_PATH from .env if explicitly set (non-empty).
    // Otherwise auto-detect from the URL: strip /public from the end of the
    // script directory so that both XAMPP sub-folder installs and root-domain
    // servers work without any configuration.
    $configured = env('APP_BASE_PATH');
    if ($configured !== null && $configured !== '') {
        $base = rtrim($configured, '/');
    } else {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        // If the script is served from /SomeFolder/public, base is /SomeFolder/public.
        // If served from /public or /, base is '' (root).
        $base = rtrim($scriptDir, '/');
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

function formatCurrencyAmount(float $amount): string
{
    // Assuming a default currency format, e.g., NPR.
    return 'NPR ' . number_format($amount, 2);
}

function buildReportExportHtml(array $dashboard, array $analysis): string
{
    // Extract data for easier access
    $salesSummary = $dashboard['sales_summary'] ?? [];
    $inventorySummary = $dashboard['inventory_summary'] ?? [];
    $lowStockReport = $dashboard['low_stock_report'] ?? [];
    $topProducts = $dashboard['top_products'] ?? [];
    $categoryBreakdown = $dashboard['category_breakdown'] ?? [];
    $periodLabel = $dashboard['period_label'] ?? 'All available data';

    $aiSummary = $analysis['summary'] ?? 'AI insight unavailable.';
    $aiOpportunities = $analysis['opportunities'] ?? [];
    $aiRisks = $analysis['risks'] ?? [];
    $aiRecommendation = $analysis['recommendation'] ?? '';
    $aiModel = $analysis['model'] ?? 'N/A';
    $aiGeneratedAt = $analysis['generated_at'] ?? 'N/A';

    // Start building HTML
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventra Executive Report - ' . e(date('Y-m-d')) . '</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 900px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #1d3989; margin-top: 20px; margin-bottom: 10px; }
        h1 { font-size: 28px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        h2 { font-size: 22px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        h3 { font-size: 18px; }
        .section { margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #eee; }
        .stat-card strong { display: block; font-size: 24px; color: #4059aa; margin-bottom: 5px; }
        .stat-card span { font-size: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        ul { margin: 0; padding-left: 20px; }
        li { margin-bottom: 5px; }
        .ai-insight { background-color: #e6f7ff; border-left: 5px solid #4059aa; padding: 15px; margin-top: 20px; border-radius: 4px; }
        .ai-insight h3 { color: #1d3989; margin-top: 0; }
        .ai-insight ul { list-style-type: disc; }
        .ai-insight-meta { font-size: 12px; color: #777; text-align: right; margin-top: 10px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge.healthy { background-color: #d4edda; color: #155724; }
        .badge.low { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Inventra Executive Report</h1>
        <p><strong>Report Period:</strong> ' . e($periodLabel) . '</p>
        <p><strong>Generated On:</strong> ' . e(date('Y-m-d H:i:s')) . '</p>

        <div class="section">
            <h2>AI Sales Insight</h2>
            <div class="ai-insight">
                <p>' . nl2br(e($aiSummary)) . '</p>
                ';
                if (!empty($aiOpportunities)) {
                    $html .= '<h3>Opportunities</h3><ul>';
                    foreach ($aiOpportunities as $opp) {
                        $html .= '<li>' . e($opp) . '</li>';
                    }
                    $html .= '</ul>';
                }
                if (!empty($aiRisks)) {
                    $html .= '<h3>Risks</h3><ul>';
                    foreach ($aiRisks as $risk) {
                        $html .= '<li>' . e($risk) . '</li>';
                    }
                    $html .= '</ul>';
                }
                if (!empty($aiRecommendation)) {
                    $html .= '<h3>Recommendation</h3><p>' . nl2br(e($aiRecommendation)) . '</p>';
                }
                $html .= '<div class="ai-insight-meta">Powered by ' . e($aiModel) . ' (Generated: ' . e($aiGeneratedAt) . ')</div>
            </div>
        </div>

        <div class="section">
            <h2>Sales Summary</h2>
            <div class="stats-grid">
                <div class="stat-card"><strong>' . e(formatCurrencyAmount((float) ($salesSummary['revenue'] ?? 0))) . '</strong><span>Total Revenue</span></div>
                <div class="stat-card"><strong>' . e((string) ($salesSummary['orders'] ?? 0)) . '</strong><span>Total Orders</span></div>
                <div class="stat-card"><strong>' . e((string) ($salesSummary['units'] ?? 0)) . '</strong><span>Units Sold</span></div>
                <div class="stat-card"><strong>' . e(formatCurrencyAmount((float) ($salesSummary['average_order_value'] ?? 0))) . '</strong><span>Average Order Value</span></div>
            </div>
        </div>

        <div class="section">
            <h2>Inventory Summary</h2>
            <div class="stats-grid">
                <div class="stat-card"><strong>' . e((string) ($inventorySummary['total_skus'] ?? 0)) . '</strong><span>Total SKUs</span></div>
                <div class="stat-card"><strong>' . e((string) ($inventorySummary['low_stock_count'] ?? 0)) . '</strong><span>Low Stock Items</span></div>
                <div class="stat-card"><strong>' . e((string) ($inventorySummary['out_of_stock_count'] ?? 0)) . '</strong><span>Out of Stock Items</span></div>
                <div class="stat-card"><strong>' . e(formatCurrencyAmount((float) ($inventorySummary['inventory_value'] ?? 0))) . '</strong><span>Total Inventory Value</span></div>
            </div>
        </div>

        <div class="section">
            <h2>Low Stock Report</h2>
            ';
            if (!empty($lowStockReport)) {
                $html .= '<table>
                    <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Current Stock</th><th>Min Stock</th><th>Gap</th><th>Severity</th><th>Days Below</th></tr></thead>
                    <tbody>';
                foreach ($lowStockReport as $row) {
                    $html .= '<tr>
                        <td>' . e($row['name']) . '</td>
                        <td>' . e($row['sku']) . '</td>
                        <td>' . e((string) ($row['category_name'] ?? 'N/A')) . '</td>
                        <td>' . e((string) $row['stock_quantity']) . '</td>
                        <td>' . e((string) $row['min_threshold']) . '</td>
                        <td>' . e((string) ($row['gap'] ?? 'N/A')) . '</td>
                        <td>' . e(number_format((float) ($row['severity'] ?? 0), 1)) . '%</td>
                        <td>' . e((string) $row['days_below_threshold']) . ' days</td>
                    </tr>';
                }
                $html .= '</tbody></table>';
            } else {
                $html .= '<p>No low stock items found for the selected period.</p>';
            }
            $html .= '
        </div>

        <div class="section">
            <h2>Top Products by Revenue</h2>
            ';
            if (!empty($topProducts)) {
                $html .= '<table>
                    <thead><tr><th>Product Name</th><th>Units Sold</th><th>Revenue</th></tr></thead>
                    <tbody>';
                foreach ($topProducts as $row) {
                    $html .= '<tr>
                        <td>' . e($row['name']) . '</td>
                        <td>' . e((string) $row['units_sold']) . '</td>
                        <td>' . e(formatCurrencyAmount((float) $row['revenue'])) . '</td>
                    </tr>';
                }
                $html .= '</tbody></table>';
            } else {
                $html .= '<p>No top products found for the selected period.</p>';
            }
            $html .= '
        </div>

        <div class="section">
            <h2>Category Sales Breakdown</h2>
            ';
            if (!empty($categoryBreakdown)) {
                $html .= '<table>
                    <thead><tr><th>Category</th><th>Total Revenue</th></tr></thead>
                    <tbody>';
                foreach ($categoryBreakdown as $row) {
                    $html .= '<tr>
                        <td>' . e($row['name']) . '</td>
                        <td>' . e(formatCurrencyAmount((float) $row['total'])) . '</td>
                    </tr>';
                }
                $html .= '</tbody></table>';
            } else {
                $html .= '<p>No category sales data found for the selected period.</p>';
            }
            $html .= '
        </div>

    </div>
</body>
</html>';

    return $html;
}

function todayDate(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d');
}
