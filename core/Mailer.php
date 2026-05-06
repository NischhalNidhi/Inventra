<?php

declare(strict_types=1);

/**
 * Mailer — shared mail service for all transactional emails.
 *
 * Supports two delivery modes:
 *   1. MAIL_LOG_PATH set → writes emails to a log file (local dev / XAMPP)
 *   2. Fallback         → uses PHP's mail() with SMTP credentials from .env
 *
 * Environment variables consumed:
 *   MAIL_FROM_ADDRESS  — sender address (e.g. no-reply@inventra.local)
 *   MAIL_FROM_NAME     — sender display name (e.g. Inventra)
 *   MAIL_LOG_PATH      — if set, emails are written here instead of sent
 *   APP_URL            — base URL used to build links in email bodies
 */
class Mailer
{
    /**
     * Send a plain-text email.
     * Used internally and kept for legacy callers.
     */
    public function send(string $to, string $subject, string $body): bool
    {
        $fromAddress = env('MAIL_FROM_ADDRESS', 'no-reply@inventra.local');
        $fromName    = env('MAIL_FROM_NAME', 'Inventra');

        $logPath = env('MAIL_LOG_PATH');
        if ($logPath) {
            return $this->logEmail($to, $subject, $body, $logPath);
        }

        $headers = implode("\r\n", [
            'From: ' . $fromName . ' <' . $fromAddress . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'MIME-Version: 1.0',
        ]);

        return @mail($to, $subject, $body, $headers);
    }

    /**
     * Send the welcome / account-setup email to a newly created staff member.
     *
     * Includes:
     *   - Inventra wordmark in the header
     *   - Greeting using the user's full name
     *   - Role explanation + "account created by the Manager"
     *   - Setup link with 24-hour expiry notice
     *   - Plain-text fallback
     *
     * @param string $to        Recipient email address
     * @param string $fullName  Recipient full name
     * @param string $role      Role string (e.g. 'Supervisor')
     * @param string $setupLink Full URL to the set-password page
     */
    public function sendAccountSetup(string $to, string $fullName, string $role, string $setupLink): bool
    {
        $subject   = 'Welcome to Inventra — Set your password';
        $plainText = $this->buildAccountSetupPlainText($fullName, $role, $setupLink);
        $html      = $this->buildAccountSetupHtml($fullName, $role, $setupLink);

        return $this->sendMultipart($to, $subject, $plainText, $html);
    }

    /**
     * Send the password-reset email.
     *
     * @param string $to        Recipient email address
     * @param string $fullName  Recipient full name
     * @param string $resetLink Full URL to the reset-password page
     */
    public function sendPasswordReset(string $to, string $fullName, string $resetLink): bool
    {
        $subject   = 'Reset your Inventra password';
        $plainText = $this->buildPasswordResetPlainText($fullName, $resetLink);
        $html      = $this->buildPasswordResetHtml($fullName, $resetLink);

        return $this->sendMultipart($to, $subject, $plainText, $html);
    }

    /**
     * Send a low-stock alert email to a staff member.
     *
     * @param string $to        Recipient email address
     * @param string $fullName  Recipient full name
     * @param array  $products  Array of low-stock products, each with name, sku, stock_quantity, min_threshold
     * @param string $dashboardLink  URL to the dashboard
     */
    public function sendLowStockAlert(string $to, string $fullName, array $products, string $dashboardLink): bool
    {
        $count   = count($products);
        $subject = sprintf('Inventra Alert: %d product%s below stock threshold', $count, $count !== 1 ? 's' : '');
        $plainText = $this->buildLowStockPlainText($fullName, $products, $dashboardLink);
        $html      = $this->buildLowStockHtml($fullName, $products, $dashboardLink);

        return $this->sendMultipart($to, $subject, $plainText, $html);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Send a multipart (text + HTML) email.
     */
    private function sendMultipart(string $to, string $subject, string $plainText, string $html): bool
    {
        $fromAddress = env('MAIL_FROM_ADDRESS', 'no-reply@inventra.local');
        $fromName    = env('MAIL_FROM_NAME', 'Inventra');

        $logPath = env('MAIL_LOG_PATH');
        if ($logPath) {
            // Log as plain text for easy local debugging
            return $this->logEmail($to, $subject, $plainText, $logPath);
        }

        $boundary = 'inv_' . bin2hex(random_bytes(12));

        $headers = implode("\r\n", [
            'From: ' . $fromName . ' <' . $fromAddress . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ]);

        $body = implode("\r\n", [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 7bit',
            '',
            $plainText,
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            quoted_printable_encode($html),
            '',
            '--' . $boundary . '--',
        ]);

        return @mail($to, $subject, $body, $headers);
    }

    private function logEmail(string $to, string $subject, string $body, string $logPath): bool
    {
        $absolutePath = $this->resolveLogPath($logPath);
        $directory    = dirname($absolutePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $absolutePath,
            sprintf("[%s]\nTo: %s\nSubject: %s\n%s\n\n", date('c'), $to, $subject, $body),
            FILE_APPEND | LOCK_EX
        );

        return true;
    }

    private function resolveLogPath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        return dirname(__DIR__) . '/' . ltrim($path, '/\\');
    }

    // -------------------------------------------------------------------------
    // Email body builders
    // -------------------------------------------------------------------------

    private function buildAccountSetupPlainText(string $fullName, string $role, string $setupLink): string
    {
        return <<<TEXT
        Hello {$fullName},

        Welcome to Inventra! Your staff account has been created by the Manager with the role: {$role}.

        To get started, you need to set your password. Click or copy the link below:

        {$setupLink}

        IMPORTANT: This link expires in 24 hours. After that, ask your Manager to resend the invitation.

        If you were not expecting this email, please ignore it — no action is required.

        —
        The Inventra Team
        TEXT;
    }

    private function buildPasswordResetPlainText(string $fullName, string $resetLink): string
    {
        return <<<TEXT
        Hello {$fullName},

        You requested a password reset for your Inventra account. Click or copy the link below:

        {$resetLink}

        IMPORTANT: This link expires in 1 hour. If you did not request a reset, you can safely ignore this email.

        —
        The Inventra Team
        TEXT;
    }

    private function buildAccountSetupHtml(string $fullName, string $role, string $setupLink): string
    {
        $safeFullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeRole     = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        $safeLink     = htmlspecialchars($setupLink, ENT_QUOTES, 'UTF-8');

        return $this->wrapEmailHtml("Welcome to Inventra", <<<HTML
            <h1 style="margin:0 0 8px;font-size:24px;font-weight:900;color:#323235;letter-spacing:-0.03em;">
                Hello, {$safeFullName}!
            </h1>
            <p style="margin:0 0 20px;color:#5f5f61;font-size:15px;line-height:1.6;">
                Your Inventra staff account has been created by the Manager. Your assigned role is
                <strong style="color:#4059aa;">{$safeRole}</strong>.
            </p>
            <p style="margin:0 0 28px;color:#5f5f61;font-size:15px;line-height:1.6;">
                To complete your account setup, click the button below to set your password.
                This is a one-time secure link.
            </p>
            <a href="{$safeLink}"
               style="display:inline-block;background:#4059aa;color:#f8f7ff;text-decoration:none;
                      padding:14px 28px;border-radius:10px;font-weight:700;font-size:15px;
                      letter-spacing:-0.01em;margin-bottom:24px;">
                Set My Password →
            </a>
            <p style="margin:0 0 8px;color:#7b7a7d;font-size:13px;">
                Or copy this link into your browser:
            </p>
            <p style="margin:0 0 24px;word-break:break-all;">
                <a href="{$safeLink}" style="color:#4059aa;font-size:13px;">{$safeLink}</a>
            </p>
            <div style="padding:16px;background:#fff3cd;border-radius:8px;border-left:4px solid #b45309;margin-bottom:0;">
                <p style="margin:0;font-size:13px;color:#92400e;font-weight:600;">
                    ⏰ This link expires in <strong>24 hours</strong>.
                    After expiry, contact your Manager to resend the invitation.
                </p>
            </div>
        HTML);
    }

    private function buildPasswordResetHtml(string $fullName, string $resetLink): string
    {
        $safeFullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeLink     = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        return $this->wrapEmailHtml("Reset Your Password", <<<HTML
            <h1 style="margin:0 0 8px;font-size:24px;font-weight:900;color:#323235;letter-spacing:-0.03em;">
                Hello, {$safeFullName}!
            </h1>
            <p style="margin:0 0 20px;color:#5f5f61;font-size:15px;line-height:1.6;">
                We received a request to reset the password for your Inventra account.
            </p>
            <a href="{$safeLink}"
               style="display:inline-block;background:#4059aa;color:#f8f7ff;text-decoration:none;
                      padding:14px 28px;border-radius:10px;font-weight:700;font-size:15px;
                      letter-spacing:-0.01em;margin-bottom:24px;">
                Reset My Password →
            </a>
            <p style="margin:0 0 8px;color:#7b7a7d;font-size:13px;">
                Or copy this link into your browser:
            </p>
            <p style="margin:0 0 24px;word-break:break-all;">
                <a href="{$safeLink}" style="color:#4059aa;font-size:13px;">{$safeLink}</a>
            </p>
            <div style="padding:16px;background:#fff3cd;border-radius:8px;border-left:4px solid #b45309;margin-bottom:0;">
                <p style="margin:0;font-size:13px;color:#92400e;font-weight:600;">
                    ⏰ This link expires in <strong>1 hour</strong>.
                    If you did not request a reset, you can safely ignore this email.
                </p>
            </div>
        HTML);
    }

    private function buildLowStockPlainText(string $fullName, array $products, string $dashboardLink): string
    {
        $lines = ["Hello {$fullName},", '', 'The following products are at or below their minimum stock threshold:', ''];
        foreach ($products as $p) {
            $lines[] = sprintf(
                '  • %s (%s) — Stock: %d / Min: %d',
                $p['name'], $p['sku'], (int) $p['stock_quantity'], (int) $p['min_threshold']
            );
        }
        $lines[] = '';
        $lines[] = 'Review and reorder from the dashboard:';
        $lines[] = $dashboardLink;
        $lines[] = '';
        $lines[] = '—';
        $lines[] = 'The Inventra Team';
        return implode("\n", $lines);
    }

    private function buildLowStockHtml(string $fullName, array $products, string $dashboardLink): string
    {
        $safeFullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeLink     = htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8');
        $count        = count($products);

        $rows = '';
        foreach ($products as $p) {
            $safeName = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
            $safeSku  = htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8');
            $stock    = (int) $p['stock_quantity'];
            $threshold = (int) $p['min_threshold'];
            $isZero   = $stock === 0;
            $statusColor = $isZero ? '#dc3545' : '#e8596e';
            $statusLabel = $isZero ? 'OUT OF STOCK' : 'LOW STOCK';

            $rows .= <<<ROW
                <tr>
                    <td style="padding:10px 12px;border-bottom:1px solid #e4e2e5;font-size:13px;">{$safeName}</td>
                    <td style="padding:10px 12px;border-bottom:1px solid #e4e2e5;font-size:13px;font-family:monospace;color:#5f5f61;">{$safeSku}</td>
                    <td style="padding:10px 12px;border-bottom:1px solid #e4e2e5;font-size:13px;text-align:center;font-weight:700;color:{$statusColor};">{$stock}</td>
                    <td style="padding:10px 12px;border-bottom:1px solid #e4e2e5;font-size:13px;text-align:center;">{$threshold}</td>
                    <td style="padding:10px 12px;border-bottom:1px solid #e4e2e5;font-size:11px;text-align:center;">
                        <span style="background:{$statusColor};color:#fff;padding:3px 8px;border-radius:999px;font-weight:700;letter-spacing:0.05em;">{$statusLabel}</span>
                    </td>
                </tr>
            ROW;
        }

        return $this->wrapEmailHtml("Low Stock Alert — {$count} products", <<<HTML
            <h1 style="margin:0 0 8px;font-size:24px;font-weight:900;color:#323235;letter-spacing:-0.03em;">
                ⚠️ Low Stock Alert
            </h1>
            <p style="margin:0 0 20px;color:#5f5f61;font-size:15px;line-height:1.6;">
                Hello {$safeFullName}, <strong>{$count} product(s)</strong> in your inventory
                are at or below their minimum stock threshold and may need replenishment.
            </p>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:24px;border:1px solid #e4e2e5;border-radius:8px;">
                <thead>
                    <tr style="background:#f4f4f8;">
                        <th style="padding:10px 12px;text-align:left;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e4e2e5;">Product</th>
                        <th style="padding:10px 12px;text-align:left;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e4e2e5;">SKU</th>
                        <th style="padding:10px 12px;text-align:center;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e4e2e5;">Stock</th>
                        <th style="padding:10px 12px;text-align:center;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e4e2e5;">Min</th>
                        <th style="padding:10px 12px;text-align:center;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e4e2e5;">Status</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
            <a href="{$safeLink}"
               style="display:inline-block;background:#4059aa;color:#f8f7ff;text-decoration:none;
                      padding:14px 28px;border-radius:10px;font-weight:700;font-size:15px;
                      letter-spacing:-0.01em;margin-bottom:24px;">
                View Dashboard →
            </a>
        HTML);
    }

    /**
     * Wrap email content in the shared Inventra branded HTML shell.
     */
    private function wrapEmailHtml(string $previewText, string $content): string
    {
        $safePreview = htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title>{$safePreview}</title>
        </head>
        <body style="margin:0;padding:0;background:#f4f4f8;font-family:'Inter',Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                   style="background:#f4f4f8;padding:40px 16px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" role="presentation"
                               style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;
                                      overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">

                            <!-- Header / Wordmark -->
                            <tr>
                                <td style="background:linear-gradient(135deg,#4059aa 0%,#1d3989 100%);
                                           padding:28px 40px;text-align:left;">
                                    <table cellpadding="0" cellspacing="0" role="presentation">
                                        <tr>
                                            <td style="background:rgba(255,255,255,0.15);border-radius:10px;
                                                       padding:8px 10px;margin-right:12px;vertical-align:middle;">
                                                <span style="font-size:20px;color:#fff;font-weight:900;">⬡</span>
                                            </td>
                                            <td style="padding-left:12px;vertical-align:middle;">
                                                <span style="font-size:22px;font-weight:900;color:#ffffff;letter-spacing:-0.04em;">
                                                    Inventra
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Body content -->
                            <tr>
                                <td style="padding:40px 40px 32px;">
                                    {$content}
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="padding:24px 40px;border-top:1px solid #e4e2e5;
                                           background:#fafafa;text-align:center;">
                                    <p style="margin:0;font-size:12px;color:#b3b1b4;line-height:1.5;">
                                        This email was sent by Inventra Inventory Management System.<br>
                                        If you have any questions, contact your Manager.
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
