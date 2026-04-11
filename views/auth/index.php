<?php
/**
 * views/auth/index.php
 *
 * Handles all auth modes: login | forgot-password | set-password | reset-password
 *
 * Variables injected by public/index.php:
 *   $authMode        string  'login' | 'forgot-password' | 'set-password' | 'reset-password'
 *   $errors          array   list of error strings to display
 *   $authToken       string  raw token from URL (for set/reset flows)
 *   $tokenState      array   ['valid'=>bool, 'expired'=>bool, 'user'=>array|null]
 *   $passwordSetupUser array|null  user needing first-login password setup
 */

$title = 'Inventra — Sign In';
$isForgotPassword  = $authMode === 'forgot-password';
$isSetPassword     = $authMode === 'set-password';
$isResetPassword   = $authMode === 'reset-password';
$isLogin           = $authMode === 'login';
$authToken         = $authToken ?? '';
$tokenState        = $tokenState ?? ['valid' => false, 'expired' => false, 'user' => null];
$passwordSetupUser = $passwordSetupUser ?? null;
$flash             = getFlash();

// Used to decide whether to show the left panel
$showLeftPanel = $isLogin;

if ($isSetPassword) {
    $title = 'Inventra — Set Your Password';
} elseif ($isResetPassword) {
    $title = 'Inventra — Reset Your Password';
} elseif ($isForgotPassword) {
    $title = 'Inventra — Forgot Password';
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= e($title); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface-tint": "#4059aa",
                        "secondary-dim": "#465468",
                        "surface-bright": "#fcf8f9",
                        "on-surface-variant": "#5f5f61",
                        "on-primary-fixed-variant": "#3d57a7",
                        "on-primary": "#f8f7ff",
                        "tertiary-container": "#dae2fd",
                        "surface-container-low": "#f6f3f4",
                        "on-secondary-fixed": "#324053",
                        "on-primary-container": "#334c9d",
                        "surface-container": "#f0edef",
                        "secondary-fixed": "#d5e3fc",
                        "outline": "#7b7a7d",
                        "primary-fixed": "#dce1ff",
                        "error": "#9e3f4e",
                        "on-tertiary-container": "#4a5167",
                        "tertiary": "#575f75",
                        "surface-container-highest": "#e4e2e5",
                        "on-tertiary-fixed-variant": "#535b71",
                        "on-tertiary-fixed": "#373f54",
                        "on-secondary-fixed-variant": "#4e5c71",
                        "on-tertiary": "#f9f8ff",
                        "secondary": "#526074",
                        "primary-container": "#dce1ff",
                        "surface-dim": "#dcd9dd",
                        "tertiary-fixed-dim": "#ccd4ee",
                        "outline-variant": "#b3b1b4",
                        "on-error-container": "#782232",
                        "on-background": "#323235",
                        "primary-fixed-dim": "#c9d3ff",
                        "tertiary-fixed": "#dae2fd",
                        "primary-dim": "#334d9d",
                        "inverse-on-surface": "#9f9c9d",
                        "tertiary-dim": "#4b5369",
                        "surface-container-lowest": "#ffffff",
                        "surface-variant": "#e4e2e5",
                        "on-error": "#fff7f7",
                        "error-container": "#ff8b9a",
                        "background": "#fcf8f9",
                        "surface-container-high": "#eae7ea",
                        "on-primary-fixed": "#1d3989",
                        "error-dim": "#4f0116",
                        "inverse-surface": "#0e0e0f",
                        "secondary-fixed-dim": "#c7d5ed",
                        "secondary-container": "#d5e3fc",
                        "primary": "#4059aa",
                        "inverse-primary": "#8fa7fe",
                        "surface": "#fcf8f9",
                        "on-secondary-container": "#455367",
                        "on-surface": "#323235",
                        "on-secondary": "#f8f8ff"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "fontFamily": {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    }
                }
            }
        }
    </script>
    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .bg-gradient-brand {
            background: linear-gradient(135deg, #4059aa 0%, #1d3989 100%);
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        input.field-error {
            box-shadow: 0 0 0 2px #9e3f4e !important;
            background-color: #ff8b9a1a !important;
        }
    </style>
</head>
<body class="bg-background font-body text-on-background selection:bg-primary-fixed selection:text-on-primary-fixed">
<main class="min-h-screen flex flex-col md:flex-row overflow-hidden">
    
    <?php if ($showLeftPanel): ?>
    <!-- Left Side (60%): Branding & Visuals -->
    <section class="hidden md:flex md:w-3/5 bg-gradient-brand relative overflow-hidden flex-col justify-between p-16">
        <!-- Background Decoration -->
        <div class="absolute top-[-10%] right-[-10%] w-96 h-96 bg-primary-container/20 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-[-5%] left-[-5%] w-64 h-64 bg-tertiary-container/10 rounded-full blur-[80px]"></div>
        
        <!-- Brand Header -->
        <div class="relative z-10">
            <div class="mb-12">
                <img src="<?= e(appRootPath('logo/inventra%20with%20logo.png')); ?>" alt="Inventra" class="h-14 rounded-xl">
            </div>
            <div class="max-w-xl">
                <h1 class="text-white text-5xl font-bold leading-tight mb-6 tracking-tight">Run your store without the chaos</h1>
                <p class="text-primary-fixed text-lg font-medium opacity-90 mb-10">Track stock, manage products, and stay in control in real time with our precision-engineered platform.</p>
                <ul class="space-y-6">
                    <li class="flex items-center gap-4 text-white font-medium">
                        <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary-fixed text-lg">check</span>
                        </span>
                        Real-time stock updates
                    </li>
                    <li class="flex items-center gap-4 text-white font-medium">
                        <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary-fixed text-lg">notifications_active</span>
                        </span>
                        Smart restocking alerts
                    </li>
                    <li class="flex items-center gap-4 text-white font-medium">
                        <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary-fixed text-lg">group</span>
                        </span>
                        Multi-role access
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Floating UI Cards -->
        <div class="relative h-64 mt-12">
            <!-- Mini Dashboard Chart -->
            <div class="absolute left-0 bottom-0 w-72 glass-panel p-5 rounded-2xl shadow-2xl border border-white/20 transform -rotate-3 hover:rotate-0 transition-transform duration-500">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-sm font-bold text-slate-800">Inventory Velocity</span>
                    <span class="text-xs font-bold text-primary">+12%</span>
                </div>
                <div class="flex items-end gap-1.5 h-16">
                    <div class="flex-1 bg-primary/20 rounded-t-sm h-1/2"></div>
                    <div class="flex-1 bg-primary/40 rounded-t-sm h-3/4"></div>
                    <div class="flex-1 bg-primary/30 rounded-t-sm h-2/3"></div>
                    <div class="flex-1 bg-primary rounded-t-sm h-full"></div>
                    <div class="flex-1 bg-primary/60 rounded-t-sm h-4/5"></div>
                </div>
            </div>
            
            <!-- Product Card -->
            <div class="absolute right-20 top-[-40px] w-64 bg-surface-container-lowest p-4 rounded-2xl shadow-2xl border border-white/40 transform rotate-3 hover:rotate-0 transition-transform duration-500 z-20">
                <div class="w-full h-32 rounded-lg bg-surface-container mb-3 overflow-hidden flex items-center justify-center">
                    <img alt="Department Store Image" class="w-full h-full object-cover" src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&q=80&w=400"/>
                </div>
                <h4 class="text-sm font-bold text-on-surface mb-1">Organic Fuji Apples</h4>
                <div class="flex justify-between items-center">
                    <span class="text-xs font-medium text-on-surface-variant">SKU: 104-APL</span>
                    <span class="text-xs font-bold text-on-primary-container bg-primary-container px-2 py-0.5 rounded-full">42 in stock</span>
                </div>
            </div>
            
            <!-- Low Stock Alert Badge -->
            <div class="absolute left-48 bottom-24 bg-white p-3 rounded-xl shadow-xl border border-error/10 flex items-center gap-3 z-30 animate-bounce">
                <div class="w-3 h-3 rounded-full bg-error"></div>
                <span class="text-xs font-bold text-error">Low Stock: Gear Hubs</span>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Right Side (40%): Login Form -->
    <section class="flex-1 flex flex-col items-center justify-center p-8 md:p-16 bg-surface">
        <div class="w-full max-w-md">
            
            <!-- Mobile Logo -->
            <div class="md:hidden mb-10">
                <img src="<?= e(appRootPath('logo/inventra%20with%20logo.png')); ?>" alt="Inventra" class="h-12 rounded-xl">
            </div>

            <?php if ($flash): ?>
                <?php $isFlashError = ($flash['type'] ?? '') === 'error'; ?>
                <div class="mb-6 <?= $isFlashError ? 'bg-error-container text-on-error-container' : 'bg-primary-container text-on-primary-container'; ?> p-4 rounded-xl flex items-start gap-3" role="alert">
                    <span class="material-symbols-outlined text-xl mt-0.5 w-5 h-5 flex-shrink-0"><?= $isFlashError ? 'error' : 'check_circle'; ?></span>
                    <p class="text-sm font-medium"><?= e((string) ($flash['message'] ?? '')); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($isLogin): ?>
            <!-- MODE: LOGIN -->
            <div class="mb-10">
                <h2 class="text-3xl font-extrabold text-on-surface tracking-tight mb-2">Sign In</h2>
                <p class="text-on-surface-variant font-medium">Access your inventory dashboard to manage assets.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div id="auth-error-banner" class="mb-6 bg-error-container text-on-error-container p-4 rounded-xl flex items-start gap-3" role="alert">
                    <span class="material-symbols-outlined text-xl mt-0.5 w-5 h-5 flex-shrink-0">error</span>
                    <p class="text-sm font-medium">Invalid email or password.</p>
                </div>
            <?php else: ?>
                <div id="auth-error-banner" class="mb-6 bg-error-container text-on-error-container p-4 rounded-xl items-start gap-3 hidden" role="alert">
                    <span class="material-symbols-outlined text-xl mt-0.5 w-5 h-5 flex-shrink-0">error</span>
                    <p class="text-sm font-medium"></p>
                </div>
            <?php endif; ?>

            <form id="login-form" class="space-y-6" method="post" action="<?= e(basePath('index.php')); ?>" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                <input type="hidden" name="action" value="login">

                <div>
                    <label class="block text-sm font-bold text-on-surface mb-2" for="login-email">Email Address</label>
                    <input class="w-full px-4 py-3 bg-surface-container-highest border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-primary-container text-on-surface transition-all placeholder:text-outline-variant outline-none" id="login-email" name="identifier" placeholder="name@company.com" type="email" required autocomplete="email">
                    <span id="email-error" class="text-sm text-error font-medium block mt-1 empty:hidden"></span>
                </div>

                <div>
                    <div class="flex justify-between mb-2">
                        <label class="block text-sm font-bold text-on-surface" for="login-password">Password</label>
                        <a class="text-sm font-semibold text-primary hover:text-on-primary-fixed-variant transition-colors" href="<?= e(basePath('index.php?mode=forgot-password')); ?>">Forgot Password?</a>
                    </div>
                    <div class="relative">
                        <input class="w-full px-4 py-3 bg-surface-container-highest border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-primary-container text-on-surface transition-all placeholder:text-outline-variant outline-none" id="login-password" name="password" placeholder="••••••••" type="password" required autocomplete="current-password" data-password-input>
                        <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant hover:text-on-surface" type="button" data-password-toggle aria-label="Show password">
                            <span class="material-symbols-outlined text-xl">visibility</span>
                        </button>
                    </div>
                    <span id="password-error" class="text-sm text-error font-medium block mt-1 empty:hidden"></span>
                </div>

                <div class="flex items-center">
                    <input class="w-5 h-5 rounded border-surface-container-highest text-primary focus:ring-primary/20 transition-all cursor-pointer" id="remember" name="remember_me" value="1" type="checkbox">
                    <label class="ml-3 text-sm font-medium text-on-surface-variant cursor-pointer select-none" for="remember">Keep me signed in for 30 days</label>
                </div>

                <button class="w-full py-4 bg-primary text-on-primary font-bold rounded-xl shadow-lg shadow-primary/20 hover:bg-primary-dim active:scale-[0.98] transition-all flex items-center justify-center gap-2 group" type="submit" id="login-submit">
                    Sign In
                    <span class="material-symbols-outlined text-xl group-[.loading]:hidden" id="login-btn-icon">login</span>
                    <svg class="animate-spin h-5 w-5 text-white group-[.loading]:block hidden" id="login-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </form>



            <?php elseif ($isForgotPassword): ?>
            <!-- MODE: FORGOT PASSWORD -->
            <div class="mb-10">
                <h2 class="text-3xl font-extrabold text-on-surface tracking-tight mb-2">Forgot Password</h2>
                <p class="text-on-surface-variant font-medium">Enter your email address to receive a reset link.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 bg-error-container text-on-error-container p-4 rounded-xl flex items-start gap-3" role="alert">
                    <span class="material-symbols-outlined text-xl mt-0.5 w-5 h-5 flex-shrink-0">error</span>
                    <p class="text-sm font-medium"><?= e($errors[0]); ?></p>
                </div>
            <?php endif; ?>

            <form class="space-y-6" method="post" action="<?= e(basePath('index.php?mode=forgot-password')); ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                <input type="hidden" name="action" value="request_password_reset">

                <div>
                    <label class="block text-sm font-bold text-on-surface mb-2" for="forgot-email">Email Address</label>
                    <input class="w-full px-4 py-3 bg-surface-container-highest border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-primary-container text-on-surface transition-all placeholder:text-outline-variant outline-none" id="forgot-email" name="email" placeholder="name@company.com" type="email" required autocomplete="email">
                </div>

                <button class="w-full py-4 bg-primary text-on-primary font-bold rounded-xl shadow-lg shadow-primary/20 hover:bg-primary-dim active:scale-[0.98] transition-all flex items-center justify-center gap-2" type="submit">
                    Send Reset Link
                    <span class="material-symbols-outlined text-xl">send</span>
                </button>
            </form>

            <div class="mt-8 text-center">
                <a class="font-bold text-primary hover:underline underline-offset-4 decoration-2" href="<?= e(basePath('index.php')); ?>">&larr; Back to Sign In</a>
            </div>

            <?php elseif ($isSetPassword): ?>
            <!-- MODE: SET PASSWORD -->
            <div class="mb-10">
                <h2 class="text-3xl font-extrabold text-on-surface tracking-tight mb-2">Set Your Password</h2>
                <?php if ($authToken !== '' && $tokenState['valid']): ?>
                <p class="text-on-surface-variant font-medium">Create your password from the secure welcome link sent to your email.</p>
                <?php elseif ($passwordSetupUser): ?>
                <p class="text-on-surface-variant font-medium">First-time sign-in detected. Set your account password to continue.</p>
                <?php else: ?>
                <p class="text-on-surface-variant font-medium">Set your account password to get started.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 bg-error-container text-on-error-container p-4 rounded-xl flex items-start gap-3" role="alert">
                    <span class="material-symbols-outlined text-xl mt-0.5 w-5 h-5 flex-shrink-0">error</span>
                    <p class="text-sm font-medium"><?= e($errors[0]); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($authToken !== '' && $tokenState['valid']): ?>
                <form class="space-y-6" method="post" action="<?= e(basePath('index.php?mode=set-password')); ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                    <input type="hidden" name="action" value="set_password_with_token">
                    <input type="hidden" name="token" value="<?= e($authToken); ?>">

                    <div>
                        <label class="block text-sm font-bold text-on-surface mb-2" for="set-account">Account</label>
                        <input class="w-full px-4 py-3 bg-surface-container-highest opacity-70 cursor-not-allowed border-none rounded-xl text-on-surface" id="set-account" type="text" value="<?= e((string)($tokenState['user']['email'] ?? '')); ?>" readonly>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-on-surface mb-2" for="set-password">New Password</label>
                        <div class="relative">
                            <input class="w-full px-4 py-3 bg-surface-container-highest border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-primary-container text-on-surface transition-all outline-none" id="set-password" name="password" placeholder="••••••••" type="password" required data-password-input>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant hover:text-on-surface" type="button" data-password-toggle aria-label="Show password">
                                <span class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-on-surface mb-2" for="set-confirm">Confirm Password</label>
                        <div class="relative">
                            <input class="w-full px-4 py-3 bg-surface-container-highest border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-primary-container text-on-surface transition-all outline-none" id="set-confirm" name="password_confirm" placeholder="••••••••" type="password" required data-password-input>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant hover:text-on-surface" type="button" data-password-toggle aria-label="Show password">
                                <span class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>

                    <button class="w-full py-4 bg-primary text-on-primary font-bold rounded-xl shadow-lg shadow-primary/20 hover:bg-primary-dim active:scale-[0.98] transition-all flex items-center justify-center gap-2" type="submit">
                        Set Password
                        <span class="material-symbols-outlined text-xl">lock_reset</span>
                    </button>
                </form>
            <?php elseif ($authToken !== '' && !$tokenState['valid']): ?>
                <div class="bg-error-container/20 border border-error/20 p-6 rounded-xl" role="alert">
                    <strong class="block text-on-surface text-lg mb-2">This link has expired or is invalid.</strong>
                    <p class="text-on-surface-variant font-medium">Please contact your Manager to resend the invitation.</p>
                </div>
            <?php elseif ($passwordSetupUser): ?>
                <form class="space-y-6" method="post" action="<?= e(basePath('index.php?mode=set-password')); ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                    <input type="hidden" name="action" value="set_password_first_login">

                    <div>
                        <label class="block text-sm font-bold text-on-surface mb-2" for="first-account">Account</label>
                        <input class="w-full px-4 py-3 bg-surface-container-highest opacity-70 cursor-not-allowed border-none rounded-xl text-on-surface" id="first-account" type="text" value="<?= e((string)($passwordSetupUser['email'] ?? '')); ?>" readonly>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-on-surface mb-2" for="first-password">New Password</label>
                        <div class="relative">
                            <input class="w-full px-4 py-3 bg-surface-container-highest border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-primary-container text-on-surface transition-all outline-none" id="first-password" name="password" placeholder="••••••••" type="password" required data-password-input>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant hover:text-on-surface" type="button" data-password-toggle aria-label="Show password">
                                <span class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-on-surface mb-2" for="first-confirm">Confirm Password</label>
                        <div class="relative">
                            <input class="w-full px-4 py-3 bg-surface-container-highest border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-primary-container text-on-surface transition-all outline-none" id="first-confirm" name="password_confirm" placeholder="••••••••" type="password" required data-password-input>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant hover:text-on-surface" type="button" data-password-toggle aria-label="Show password">
                                <span class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>

                    <button class="w-full py-4 bg-primary text-on-primary font-bold rounded-xl shadow-lg shadow-primary/20 hover:bg-primary-dim active:scale-[0.98] transition-all flex items-center justify-center gap-2" type="submit">
                        Save Password
                        <span class="material-symbols-outlined text-xl">lock_reset</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="bg-error-container/20 border border-error/20 p-6 rounded-xl" role="alert">
                    <strong class="block text-on-surface text-lg mb-2">This link has expired or is invalid.</strong>
                    <p class="text-on-surface-variant font-medium">Please contact your Manager to resend the invitation.</p>
                </div>
            <?php endif; ?>

            <div class="mt-8 text-center">
                <a class="font-bold text-primary hover:underline underline-offset-4 decoration-2" href="<?= e(basePath('index.php')); ?>">&larr; Back to Sign In</a>
            </div>

            <?php elseif ($isResetPassword): ?>
            <!-- MODE: RESET PASSWORD -->
            <div class="mb-10">
                <h2 class="text-3xl font-extrabold text-on-surface tracking-tight mb-2">Reset Your Password</h2>
                <?php if ($tokenState['valid']): ?>
                <p class="text-on-surface-variant font-medium">Enter a new password from your secure reset link.</p>
                <?php else: ?>
                <p class="text-on-surface-variant font-medium">Reset your Inventra account password.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 bg-error-container text-on-error-container p-4 rounded-xl flex items-start gap-3" role="alert">
                    <span class="material-symbols-outlined text-xl mt-0.5 w-5 h-5 flex-shrink-0">error</span>
                    <p class="text-sm font-medium"><?= e($errors[0]); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($tokenState['valid']): ?>
                <form class="space-y-6" method="post" action="<?= e(basePath('index.php?mode=reset-password')); ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                    <input type="hidden" name="action" value="reset_password_with_token">
                    <input type="hidden" name="token" value="<?= e($authToken); ?>">

                    <div>
                        <label class="block text-sm font-bold text-on-surface mb-2" for="reset-password">New Password</label>
                        <div class="relative">
                            <input class="w-full px-4 py-3 bg-surface-container-highest border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-primary-container text-on-surface transition-all outline-none" id="reset-password" name="password" placeholder="••••••••" type="password" required data-password-input>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant hover:text-on-surface" type="button" data-password-toggle aria-label="Show password">
                                <span class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-on-surface mb-2" for="reset-confirm">Confirm Password</label>
                        <div class="relative">
                            <input class="w-full px-4 py-3 bg-surface-container-highest border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-primary-container text-on-surface transition-all outline-none" id="reset-confirm" name="password_confirm" placeholder="••••••••" type="password" required data-password-input>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant hover:text-on-surface" type="button" data-password-toggle aria-label="Show password">
                                <span class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>

                    <button class="w-full py-4 bg-primary text-on-primary font-bold rounded-xl shadow-lg shadow-primary/20 hover:bg-primary-dim active:scale-[0.98] transition-all flex items-center justify-center gap-2" type="submit">
                        Reset Password
                        <span class="material-symbols-outlined text-xl">lock_reset</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="bg-error-container/20 border border-error/20 p-6 rounded-xl" role="alert">
                    <strong class="block text-on-surface text-lg mb-2"><?= $tokenState['expired'] ? 'This reset link has expired.' : 'This reset link is invalid or has already been used.'; ?></strong>
                    <p class="text-on-surface-variant font-medium">Request a new reset link and try again.</p>
                </div>
                <div class="mt-6 text-center">
                    <a class="font-bold text-primary hover:underline underline-offset-4 decoration-2" href="<?= e(basePath('index.php?mode=forgot-password')); ?>">Request a new reset link</a>
                </div>
            <?php endif; ?>

            <div class="mt-8 text-center">
                <a class="font-bold text-primary hover:underline underline-offset-4 decoration-2" href="<?= e(basePath('index.php')); ?>">&larr; Back to Sign In</a>
            </div>

            <?php endif; ?>

            <!-- Footer Links -->
            <footer class="mt-12 flex flex-wrap justify-center gap-6 text-xs font-bold text-outline-variant">
                <a class="hover:text-on-surface transition-colors" href="#">Privacy Policy</a>
                <a class="hover:text-on-surface transition-colors" href="#">Terms of Service</a>
                <a class="hover:text-on-surface transition-colors" href="#">Security</a>
            </footer>

        </div>
    </section>
</main>

<script src="<?= e(basePath('js/app.js')) . '?v=' . time(); ?>"></script>
<script>
    // Minimal script to make the password toggle work, since the class names have changed and app.js might depend on old ones.
    document.querySelectorAll('[data-password-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const container = button.closest('.relative');
            if(container) {
                const input = container.querySelector('[data-password-input]');
                if(input) {
                    const icon = button.querySelector('.material-symbols-outlined');
                    if(input.type === 'password') {
                        input.type = 'text';
                        icon.textContent = 'visibility_off';
                    } else {
                        input.type = 'password';
                        icon.textContent = 'visibility';
                    }
                }
            }
        });
    });
</script>
</body>
</html>
