<?php
/**
 * views/public/info.php
 *
 * Public informational pages (Privacy Policy, Terms of Service, Security)
 * Fully responsive, accessible without authentication, supports system dark mode.
 */

$allowedTypes = ['privacy-policy', 'terms-of-service', 'security'];
$type = in_array($_GET['page'] ?? '', $allowedTypes) ? $_GET['page'] : 'privacy-policy';

$title = 'Inventra | Privacy Policy';
$heading = 'Privacy Policy';
if ($type === 'terms-of-service') {
    $title = 'Inventra | Terms of Service';
    $heading = 'Terms of Service';
} elseif ($type === 'security') {
    $title = 'Inventra | Security Information';
    $heading = 'Security Policy';
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= htmlspecialchars($title); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        // Check local storage or system preference for dark theme
        const savedTheme = localStorage.getItem('inventra_theme');
        if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .bg-gradient-brand {
            background: linear-gradient(135deg, #4059aa 0%, #1d3989 100%);
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        /* Ensure dark theme glass panels are highly visible and opaque */
        .dark .glass-panel,
        html.dark .glass-panel {
            background: rgba(22, 30, 49, 0.95) !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        @media (prefers-color-scheme: dark) {
            .glass-panel {
                background: rgba(22, 30, 49, 0.95) !important;
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
        }
    </style>
</head>
<body class="bg-[#fcf8f9] dark:bg-[#10131b] text-[#323235] dark:text-[#e6ebf8] min-h-screen flex flex-col justify-between selection:bg-[#4059aa]/20 transition-colors duration-300">

    <!-- Navbar header -->
    <header class="w-full py-5 px-6 md:px-12 flex justify-between items-center border-b border-gray-200/50 dark:border-white/5 glass-panel sticky top-0 z-50">
        <a href="<?= htmlspecialchars(basePath('index.php')); ?>" class="flex items-center gap-3 group">
            <img src="<?= htmlspecialchars(appRootPath('logo/inventra%20with%20logo.png')); ?>" alt="Inventra Logo" style="height: 38px; width: auto; object-fit: contain;">
        </a>
        <a href="<?= htmlspecialchars(basePath('index.php')); ?>" class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-[#4059aa] dark:text-[#6f8dff] hover:bg-[#4059aa]/10 rounded-xl transition-colors">
            <span class="material-symbols-outlined text-lg">arrow_back</span> Back to App
        </a>
    </header>

    <main class="flex-1 flex items-center justify-center p-6 md:p-12 relative overflow-hidden">
        <!-- Abstract blur bubbles -->
        <div class="absolute top-10 left-10 w-72 h-72 bg-[#4059aa]/10 rounded-full blur-[100px] pointer-events-none"></div>
        <div class="absolute bottom-10 right-10 w-96 h-96 bg-[#a855f7]/10 rounded-full blur-[120px] pointer-events-none"></div>

        <article class="w-full max-w-4xl glass-panel border border-gray-200/60 rounded-3xl p-6 md:p-12 shadow-2xl relative z-10 hover:shadow-primary/5 transition-shadow duration-300">
            
            <nav class="flex gap-4 border-b border-gray-200 dark:border-white/10 pb-4 mb-8 overflow-x-auto">
                <a href="?page=privacy-policy" class="pb-2 px-2 text-sm font-bold transition-all relative <?= $type === 'privacy-policy' ? 'text-[#4059aa] dark:text-[#6f8dff] border-b-2 border-[#4059aa] dark:border-[#6f8dff]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' ?>">Privacy Policy</a>
                <a href="?page=terms-of-service" class="pb-2 px-2 text-sm font-bold transition-all relative <?= $type === 'terms-of-service' ? 'text-[#4059aa] dark:text-[#6f8dff] border-b-2 border-[#4059aa] dark:border-[#6f8dff]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' ?>">Terms of Service</a>
                <a href="?page=security" class="pb-2 px-2 text-sm font-bold transition-all relative <?= $type === 'security' ? 'text-[#4059aa] dark:text-[#6f8dff] border-b-2 border-[#4059aa] dark:border-[#6f8dff]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' ?>">Security</a>
            </nav>

            <header class="mb-8">
                <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight mb-2 bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 dark:from-white dark:via-gray-100 dark:to-white bg-clip-text text-transparent"><?= htmlspecialchars($heading); ?></h1>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Last updated: May 19, 2026</p>
            </header>

            <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-6 leading-relaxed">
                
                <?php if ($type === 'privacy-policy'): ?>
                    <!-- PRIVACY POLICY CONTENT -->
                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">verified_user</span> 1. Introduction
                        </h2>
                        <p>At Inventra, we value your privacy and are committed to safeguarding the data generated within our system. This Privacy Policy outlines what information is collected, processed, and maintained during your store operations.</p>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">database</span> 2. Information Collected
                        </h2>
                        <p>Inventra processes operational inventory and store data strictly for the facilitation of local store management. The following information is securely processed:</p>
                        <ul class="list-disc pl-6 space-y-2">
                            <li><strong>Account Credentials:</strong> Full name, corporate email address, and encrypted passwords.</li>
                            <li><strong>Store Metadata:</strong> Product inventory levels, sales transactions, supplier logs, and transaction totals.</li>
                            <li><strong>Activity Logs:</strong> System actions taken by role (e.g., stock additions or updates) to enable accountability.</li>
                        </ul>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">security</span> 3. Data Protection Mechanisms
                        </h2>
                        <p>All stored passwords are encrypted using production-grade <strong>bcrypt hashing algorithms</strong>. Session cookies are maintained strictly for keeping users logged in, and are fully cleared upon signing out.</p>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">mail</span> 4. Policy Updates & Contact
                        </h2>
                        <p>We may update this policy occasionally as system features evolve. For privacy concerns or account deletion inquiries, please contact your store Administrator or Manager.</p>
                    </section>

                <?php elseif ($type === 'terms-of-service'): ?>
                    <!-- TERMS OF SERVICE CONTENT -->
                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">gavel</span> 1. Agreement of Terms
                        </h2>
                        <p>By using the Inventra platform, you agree to comply with and be bound by the following Terms of Service. If you do not agree, you are prohibited from utilizing this system.</p>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">badge</span> 2. Acceptable Role Usage & Accountability
                        </h2>
                        <p>Each user is assigned specific roles (Manager, Supervisor, Salesman, Logistic Handler). You are fully responsible for all actions conducted under your account credentials. You must not disclose your password or attempt to bypass restricted system endpoints.</p>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">construction</span> 3. Operational Performance
                        </h2>
                        <p>The Inventra platform is provided "as-is" and "as-available". We strive for absolute uptime and error-free database logs but are not liable for incidental system downtime or temporary cURL connection interruptions during third-party AI insight generations.</p>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">cancel</span> 4. Termination
                        </h2>
                        <p>System Administrators reserve the right to suspend or terminate accounts that exhibit suspicious activity or perform unauthorized requests outside of defined role parameters.</p>
                    </section>

                <?php elseif ($type === 'security'): ?>
                    <!-- SECURITY POLICY CONTENT -->
                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">shield_lock</span> 1. Secure by Design
                        </h2>
                        <p>Security is at the core of the Inventra architecture. The application is built following industry-best security principles to guard against common cyber threats.</p>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">lock</span> 2. Critical Protections
                        </h2>
                        <ul class="list-disc pl-6 space-y-2">
                            <li><strong>Anti-CSRF Tokens:</strong> Every destructive transaction (POST/PUT/PATCH) validates a secure Cryptographically Strong Pseudo-Random token, preventing cross-site request hijacking.</li>
                            <li><strong>XSS Protection:</strong> Output encoding handles strict content escaping to completely eliminate Cross-Site Scripting vulnerabilities.</li>
                            <li><strong>Role-Based Access Control (RBAC):</strong> Strict permission mappings are validated both in the navbar sidebar generation and hard-checked in server controllers before backend executions.</li>
                        </ul>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4059aa] dark:text-[#6f8dff]">bug_report</span> 3. Vulnerability Disclosure
                        </h2>
                        <p>If you discover a security vulnerability in the system, please contact our dev team immediately at <strong>security@inventra.local</strong> instead of public disclosure. We resolve reported issues with absolute priority.</p>
                    </section>

                <?php endif; ?>

            </div>

        </article>
    </main>

    <!-- Footer -->
    <footer class="w-full py-6 text-center text-xs font-bold text-gray-500 border-t border-gray-200/50 dark:border-white/5 glass-panel">
        <p>&copy; 2026 Inventra System. All rights reserved.</p>
    </footer>

</body>
</html>
