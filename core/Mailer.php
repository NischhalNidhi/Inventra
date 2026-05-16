<?php

declare(strict_types=1);

class Mailer
{
    public function send(string $to, string $subject, string $body): bool
    {
        return $this->sendMessage($to, $subject, $body, null);
    }

    public function sendAccountSetup(string $to, string $fullName, string $role, string $setupLink): bool
    {
        $subject = 'Welcome to Inventra - Set your password';
        $plainText = $this->buildAccountSetupPlainText($fullName, $role, $setupLink);
        $html = $this->buildAccountSetupHtml($fullName, $role, $setupLink);

        return $this->sendMessage($to, $subject, $plainText, $html);
    }

    public function sendPasswordReset(string $to, string $fullName, string $resetLink): bool
    {
        $subject = 'Reset your Inventra password';
        $plainText = $this->buildPasswordResetPlainText($fullName, $resetLink);
        $html = $this->buildPasswordResetHtml($fullName, $resetLink);

        return $this->sendMessage($to, $subject, $plainText, $html);
    }

    private function sendMessage(string $to, string $subject, string $plainText, ?string $html): bool
    {
        $mode = strtolower((string) env('MAIL_MAILER', ''));
        $logPath = env('MAIL_LOG_PATH');
        if ($mode === 'log' || ($mode === '' && $logPath)) {
            return $this->logEmail($to, $subject, $plainText, (string) ($logPath ?: 'uploads/mail.log'));
        }

        if ($mode === 'smtp' || $mode === '') {
            return $this->sendViaSmtp($to, $subject, $plainText, $html);
        }

        throw new RuntimeException('Unsupported MAIL_MAILER value: ' . $mode);
    }

    private function sendViaSmtp(string $to, string $subject, string $plainText, ?string $html): bool
    {
        $host = (string) env('MAIL_HOST', '');
        $port = (int) env('MAIL_PORT', '587');
        $username = (string) env('MAIL_USERNAME', '');
        $password = (string) env('MAIL_PASSWORD', '');
        $encryption = strtolower((string) env('MAIL_ENCRYPTION', 'tls'));
        $timeout = (int) env('MAIL_TIMEOUT', '15');
        $authRequired = strtolower((string) env('MAIL_AUTH', 'true')) !== 'false';
        $fromAddress = (string) env('MAIL_FROM_ADDRESS', 'no-reply@inventra.local');
        $fromName = (string) env('MAIL_FROM_NAME', 'Inventra');

        if ($host === '') {
            throw new RuntimeException('MAIL_HOST is required when using SMTP mail.');
        }

        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr . ' (' . $errno . ')');
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->expectSmtpCode($socket, [220]);
            $hostname = gethostname() ?: 'localhost';
            $this->smtpCommand($socket, 'EHLO ' . $hostname, [250]);

            if ($encryption === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to enable TLS for SMTP connection.');
                }
                $this->smtpCommand($socket, 'EHLO ' . $hostname, [250]);
            }

            if ($authRequired) {
                if ($username === '' || $password === '') {
                    throw new RuntimeException('MAIL_USERNAME and MAIL_PASSWORD are required for SMTP authentication.');
                }
                $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
                $this->smtpCommand($socket, base64_encode($username), [334]);
                $this->smtpCommand($socket, base64_encode($password), [235]);
            }

            $this->smtpCommand($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);

            $message = $this->buildMimeMessage($to, $subject, $plainText, $html, $fromAddress, $fromName);
            fwrite($socket, $this->dotStuff($message) . "\r\n.\r\n");
            $this->expectSmtpCode($socket, [250]);
            $this->smtpCommand($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }

        return true;
    }

    private function smtpCommand($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");

        return $this->expectSmtpCode($socket, $expectedCodes);
    }

    private function expectSmtpCode($socket, array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('SMTP server returned an empty response.');
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }

        return $response;
    }

    private function buildMimeMessage(string $to, string $subject, string $plainText, ?string $html, string $fromAddress, string $fromName): string
    {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $this->formatAddress($fromAddress, $fromName),
            'To: ' . $this->formatAddress($to, $to),
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
        ];

        if ($html === null) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';

            return implode("\r\n", $headers) . "\r\n\r\n" . $plainText;
        }

        $boundary = 'inv_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
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
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $body);
    }

    private function formatAddress(string $email, string $name): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }

    private function dotStuff(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = str_replace("\n.", "\n..", $message);

        return str_replace("\n", "\r\n", $message);
    }

    private function logEmail(string $to, string $subject, string $body, string $logPath): bool
    {
        $absolutePath = $this->resolveLogPath($logPath);
        $directory = dirname($absolutePath);

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

    private function buildAccountSetupPlainText(string $fullName, string $role, string $setupLink): string
    {
        return <<<TEXT
Hello {$fullName},

Welcome to Inventra. Your staff account has been created by the Manager with the role: {$role}.

To get started, set your password using the secure link below:

{$setupLink}

IMPORTANT: This link expires in 24 hours. After that, ask your Manager to resend the invitation.

If you were not expecting this email, please ignore it. No action is required.

-
The Inventra Team
TEXT;
    }

    private function buildPasswordResetPlainText(string $fullName, string $resetLink): string
    {
        return <<<TEXT
Hello {$fullName},

You requested a password reset for your Inventra account. Use the secure link below:

{$resetLink}

IMPORTANT: This link expires in 1 hour. If you did not request a reset, you can safely ignore this email.

-
The Inventra Team
TEXT;
    }

    private function buildAccountSetupHtml(string $fullName, string $role, string $setupLink): string
    {
        $safeFullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($setupLink, ENT_QUOTES, 'UTF-8');

        return $this->wrapEmailHtml('Welcome to Inventra', <<<HTML
            <h1 style="margin:0 0 8px;font-size:24px;font-weight:900;color:#323235;letter-spacing:-0.03em;">
                Hello, {$safeFullName}!
            </h1>
            <p style="margin:0 0 20px;color:#5f5f61;font-size:15px;line-height:1.6;">
                Your Inventra staff account has been created by the Manager. Your assigned role is
                <strong style="color:#4059aa;">{$safeRole}</strong>.
            </p>
            <p style="margin:0 0 28px;color:#5f5f61;font-size:15px;line-height:1.6;">
                To complete your account setup, click the button below to set your password.
            </p>
            <a href="{$safeLink}"
               style="display:inline-block;background:#4059aa;color:#f8f7ff;text-decoration:none;
                      padding:14px 28px;border-radius:10px;font-weight:700;font-size:15px;
                      letter-spacing:-0.01em;margin-bottom:24px;">
                Set My Password
            </a>
            <p style="margin:0 0 8px;color:#7b7a7d;font-size:13px;">Or copy this link into your browser:</p>
            <p style="margin:0 0 24px;word-break:break-all;">
                <a href="{$safeLink}" style="color:#4059aa;font-size:13px;">{$safeLink}</a>
            </p>
            <div style="padding:16px;background:#fff3cd;border-radius:8px;border-left:4px solid #b45309;margin-bottom:0;">
                <p style="margin:0;font-size:13px;color:#92400e;font-weight:600;">
                    This link expires in <strong>24 hours</strong>. After expiry, contact your Manager to resend the invitation.
                </p>
            </div>
        HTML);
    }

    private function buildPasswordResetHtml(string $fullName, string $resetLink): string
    {
        $safeFullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        return $this->wrapEmailHtml('Reset Your Password', <<<HTML
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
                Reset My Password
            </a>
            <p style="margin:0 0 8px;color:#7b7a7d;font-size:13px;">Or copy this link into your browser:</p>
            <p style="margin:0 0 24px;word-break:break-all;">
                <a href="{$safeLink}" style="color:#4059aa;font-size:13px;">{$safeLink}</a>
            </p>
            <div style="padding:16px;background:#fff3cd;border-radius:8px;border-left:4px solid #b45309;margin-bottom:0;">
                <p style="margin:0;font-size:13px;color:#92400e;font-weight:600;">
                    This link expires in <strong>1 hour</strong>. If you did not request a reset, you can safely ignore this email.
                </p>
            </div>
        HTML);
    }

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
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f4f8;padding:40px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#4059aa 0%,#1d3989 100%);padding:28px 40px;text-align:left;">
                            <span style="font-size:22px;font-weight:900;color:#ffffff;letter-spacing:-0.04em;">Inventra</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px 40px 32px;">{$content}</td>
                    </tr>
                    <tr>
                        <td style="padding:24px 40px;border-top:1px solid #e4e2e5;background:#fafafa;text-align:center;">
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
