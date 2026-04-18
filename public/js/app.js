document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const basePath = body.dataset.basePath || '';
    const appRoot = basePath.endsWith('/public') ? basePath.slice(0, -7) : '';
    const apiBase = `${appRoot}/api`;
    const csrfToken = body.dataset.csrfToken || '';
    const storedTheme = window.localStorage.getItem('inventra_theme') || 'light';
    body.classList.toggle('theme-dark', storedTheme === 'dark');

    // ---------------------------------------------------------------
    // LOGIN FORM — §5 client-side validation + loading spinner + AJAX
    // ---------------------------------------------------------------
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        const emailInput    = document.getElementById('login-email');
        const passwordInput = document.getElementById('login-password');
        const submitBtn     = document.getElementById('login-submit');
        const btnIcon       = document.getElementById('login-btn-icon');
        const spinner       = document.getElementById('login-spinner');
        const errorBanner   = document.getElementById('auth-error-banner');
        const emailError    = document.getElementById('email-error');
        const passwordError = document.getElementById('password-error');

        /** Hide the error banner and clear inline errors */
        const clearErrors = () => {
            if (errorBanner)   { errorBanner.classList.add('hidden'); }
            if (emailError)    { emailError.classList.remove('visible'); emailError.textContent = ''; }
            if (passwordError) { passwordError.classList.remove('visible'); passwordError.textContent = ''; }
            emailInput?.classList.remove('field-error');
            passwordInput?.classList.remove('field-error');
        };

        /** Show inline error below a field */
        const showFieldError = (input, errorEl, message) => {
            input.classList.add('field-error');
            errorEl.textContent = message;
            errorEl.classList.add('visible');
            input.focus();
        };

        /** Show the generic error banner (§4.6) — always generic message */
        const showBanner = (message) => {
            if (!errorBanner) { return; }
            const msgEl = errorBanner.querySelector('p');
            if (msgEl) { msgEl.textContent = message; }
            errorBanner.classList.remove('hidden');
        };

        /** Enter loading state — §4.5 */
        const setLoading = (loading) => {
            if (!submitBtn) { return; }
            submitBtn.setAttribute('aria-busy', loading ? 'true' : 'false');
            submitBtn.classList.toggle('loading', loading);
            if (btnIcon) { btnIcon.style.display = loading ? 'none' : ''; }
            if (spinner) { spinner.style.display  = loading ? ''     : 'none'; }
        };

        // Clear errors the moment user starts typing in either field (§4.6)
        [emailInput, passwordInput].forEach((el) => {
            if (el) {
                el.addEventListener('input',   clearErrors);
                el.addEventListener('keydown', clearErrors);
            }
        });

        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();

            const email    = (emailInput?.value    || '').trim();
            const password = (passwordInput?.value || '').trim();

            // Client-side pre-flight validation (§5)
            let hasError = false;
            if (!email) {
                showFieldError(emailInput, emailError, 'Email is required.');
                hasError = true;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFieldError(emailInput, emailError, 'Enter a valid email address.');
                hasError = true;
            }
            if (!password) {
                if (!hasError) {
                    showFieldError(passwordInput, passwordError, 'Password is required.');
                } else {
                    passwordInput.classList.add('field-error');
                    passwordError.textContent = 'Password is required.';
                    passwordError.classList.add('visible');
                }
                hasError = true;
            }
            if (hasError) { return; }

            setLoading(true);

            try {
                const formData = new FormData(loginForm);
                const response = await fetch(loginForm.getAttribute('action'), {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const payload = await response.json().catch(() => ({}));

                if (response.ok && payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }

                // Handle error statuses (§5 Server-Side Response Handling)
                if (response.status === 401) {
                    showBanner('Invalid email or password.');
                } else if (response.status === 429) {
                    showBanner('Too many attempts. Please wait a moment.');
                } else {
                    showBanner('Something went wrong. Please try again.');
                }
            } catch (_err) {
                showBanner('Something went wrong. Please try again.');
            } finally {
                setLoading(false);
            }
        });
    }

    // ---------------------------------------------------------------
    // PASSWORD VISIBILITY TOGGLE — shared across all auth forms
    // Works with both .auth-password-input wrappers (new) and any
    // wrapper containing [data-password-input] (legacy).
    // ---------------------------------------------------------------
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const wrapper = button.closest('.auth-password-input');
        const input   = wrapper?.querySelector('[data-password-input]');
        const icon    = button.querySelector('.material-symbols-outlined');

        if (!input || !icon) { return; }

        button.addEventListener('click', () => {
            const isHidden = input.getAttribute('type') === 'password';
            input.setAttribute('type', isHidden ? 'text' : 'password');
            icon.textContent = isHidden ? 'visibility_off' : 'visibility';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });

    // ---------------------------------------------------------------
    // PRODUCT TABLE LIVE SEARCH
    // ---------------------------------------------------------------
    const searchInput    = document.querySelector('#live-search');
    const categoryFilter = document.querySelector('#category-filter');
    const stockFilter    = document.querySelector('#stock-filter');
    const archivedFilter = document.querySelector('select[name="archived"]');
    const tableBody      = document.querySelector('#product-table-body');

    const buildActionButtons = (product) => {
        const actions = [];

        if (product.can_edit) {
            actions.push(`
                <a class="btn-action btn-edit" href="${product.edit_url}" title="Edit product">
                    <span class="material-symbols-outlined">edit</span>
                    <span class="btn-label">Edit</span>
                </a>
            `);
        }

        if (product.can_archive) {
            actions.push(`
                <form method="post" action="${basePath}/index.php?page=products">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="action" value="archive_product">
                    <input type="hidden" name="product_id" value="${product.id}">
                    <button class="btn-action btn-archive" type="submit" title="Archive product">
                        <span class="material-symbols-outlined">archive</span>
                        <span class="btn-label">Archive</span>
                    </button>
                </form>
            `);
        }

        if (product.can_delete) {
            actions.push(`
                <form method="post" action="${basePath}/index.php?page=products" onsubmit="return confirm('Permanently delete this product?');">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="${product.id}">
                    <button class="btn-action btn-delete" type="submit" title="Delete product">
                        <span class="material-symbols-outlined">delete</span>
                        <span class="btn-label">Delete</span>
                    </button>
                </form>
            `);
        }

        return actions.join('');
    };

    const renderProducts = (products) => {
        if (!tableBody) { return; }

        if (!products.length) {
            tableBody.innerHTML = '<tr><td colspan="6">No products match the current filters.</td></tr>';
            return;
        }

        tableBody.innerHTML = products.map((product) => `
            <tr class="product-row" data-product-id="${product.id}">
                <td>
                    <div class="product-cell-main">
                        <div class="product-cell-image">
                            ${product.image_name
                                ? `<img src="${basePath}/uploads/products/${escapeHtml(product.image_name)}" alt="${escapeHtml(product.name)}" width="48" height="48">`
                                : '<span class="material-symbols-outlined product-placeholder-icon icon-muted">inventory_2</span>'
                            }
                        </div>
                        <div class="product-cell-info">
                            <div class="product-cell-name">${escapeHtml(product.name)}</div>
                            <div class="product-cell-meta">
                                ${escapeHtml(product.category)} / ${escapeHtml(product.supplier)}
                            </div>
                        </div>
                    </div>
                </td>
                <td class="td-sku">${escapeHtml(product.sku)}</td>
                <td class="td-qty">${product.quantity}</td>
                <td class="td-price">${formatCurrency(product.unit_price)}</td>
                <td>
                    <span class="badge ${product.status_class}">
                        <span class="material-symbols-outlined badge-icon">${product.status_class === 'low' ? 'warning' : 'check_circle'}</span>
                        ${product.status_class === 'low' ? 'Low Stock' : 'In Stock'}
                    </span>
                </td>
                <td class="td-actions"><div class="action-group">${buildActionButtons(product)}</div></td>
            </tr>
        `).join('');
    };

    const fetchProducts = debounce(async () => {
        if (!searchInput || !tableBody) { return; }

        const params = new URLSearchParams({
            keyword:     searchInput.value.trim(),
            category:    categoryFilter ? categoryFilter.value : '',
            stock_level: stockFilter    ? stockFilter.value    : '',
            archived:    archivedFilter ? archivedFilter.value : '',
        });

        try {
            const response = await fetch(`${apiBase}/products.php?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) { return; }

            const payload = await response.json();
            renderProducts(payload.products || []);
        } catch (_error) {
            // Keep server-rendered table as fallback.
        }
    }, 250);

    [searchInput, categoryFilter, stockFilter, archivedFilter].forEach((element) => {
        if (element) {
            element.addEventListener('input',  fetchProducts);
            element.addEventListener('change', fetchProducts);
        }
    });

    // ---------------------------------------------------------------
    // STOCK FORM (AJAX)
    // ---------------------------------------------------------------
    const stockForm     = document.querySelector('.ajax-stock-form');
    const stockFeedback = document.querySelector('#stock-feedback');

    if (stockForm) {
        stockForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(stockForm);
            try {
                const response = await fetch(`${apiBase}/stock.php`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const payload = await response.json();
                stockFeedback.textContent = payload.message || payload.error || 'Unable to process request.';
                stockFeedback.style.color = response.ok ? '#2e8a62' : '#cb4d5d';

                if (response.ok) {
                    window.setTimeout(() => window.location.reload(), 700);
                }
            } catch (_error) {
                stockFeedback.textContent = 'Unable to process request.';
                stockFeedback.style.color = '#cb4d5d';
            }
        });
    }

    // ---------------------------------------------------------------
    // STEPPER BUTTONS
    // ---------------------------------------------------------------
    document.querySelectorAll('.stepper-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.stepperTarget;
            const step     = parseInt(button.dataset.step || '0', 10);
            const input    = document.getElementById(targetId);

            if (!input) { return; }

            const currentValue = parseInt(input.value || '0', 10);
            input.value = Math.max(0, currentValue + step);
        });
    });

    // ---------------------------------------------------------------
    // PROFILE / NOTIFICATIONS / SETTINGS MENUS + THEME TOGGLE
    // ---------------------------------------------------------------
    const profileMenu          = document.querySelector('[data-profile-menu]');
    const profileTrigger       = document.querySelector('[data-profile-trigger]');
    const notificationsMenu    = document.querySelector('[data-notifications-menu]');
    const notificationsTrigger = document.querySelector('[data-notifications-trigger]');
    const settingsMenu         = document.querySelector('[data-settings-menu]');
    const settingsTrigger      = document.querySelector('[data-settings-trigger]');
    const themeToggle          = document.querySelector('[data-theme-toggle]');
    const clearNotificationsButton = document.querySelector('[data-clear-notifications]');
    const notifList            = document.querySelector('[data-notif-list]');

    if (profileMenu && profileTrigger) {
        const closeAllMenus = () => {
            profileMenu.classList.remove('open');
            notificationsMenu?.classList.remove('open');
            settingsMenu?.classList.remove('open');
            profileTrigger.setAttribute('aria-expanded', 'false');
            notificationsTrigger?.setAttribute('aria-expanded', 'false');
            settingsTrigger?.setAttribute('aria-expanded', 'false');
        };

        const closeProfileMenu = () => {
            profileMenu.classList.remove('open');
            profileTrigger.setAttribute('aria-expanded', 'false');
        };

        profileTrigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const nextOpen = !profileMenu.classList.contains('open');
            if (nextOpen) {
                closeAllMenus();
                profileMenu.classList.add('open');
                profileTrigger.setAttribute('aria-expanded', 'true');
            } else {
                closeProfileMenu();
            }
        });

        if (notificationsMenu && notificationsTrigger) {
            notificationsTrigger.addEventListener('click', (event) => {
                event.stopPropagation();
                const nextOpen = !notificationsMenu.classList.contains('open');
                closeAllMenus();
                if (nextOpen) {
                    notificationsMenu.classList.add('open');
                    notificationsTrigger.setAttribute('aria-expanded', 'true');
                }
            });
        }

        if (settingsMenu && settingsTrigger) {
            settingsTrigger.addEventListener('click', (event) => {
                event.stopPropagation();
                const nextOpen = !settingsMenu.classList.contains('open');
                closeAllMenus();
                if (nextOpen) {
                    settingsMenu.classList.add('open');
                    settingsTrigger.setAttribute('aria-expanded', 'true');
                }
            });
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const nextTheme = body.classList.contains('theme-dark') ? 'light' : 'dark';
                body.classList.toggle('theme-dark', nextTheme === 'dark');
                window.localStorage.setItem('inventra_theme', nextTheme);
            });
        }

        if (clearNotificationsButton && notifList) {
            clearNotificationsButton.addEventListener('click', () => {
                notifList.innerHTML = '<li><span class="material-symbols-outlined">notifications_off</span><div><strong>No new notifications</strong><small>All notifications have been cleared.</small></div></li>';
            });
        }

        document.addEventListener('click', (event) => {
            if (
                !profileMenu.contains(event.target) &&
                !notificationsMenu?.contains(event.target) &&
                !settingsMenu?.contains(event.target)
            ) {
                closeAllMenus();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { closeAllMenus(); }
        });
    }
});

// ---------------------------------------------------------------
// UTILITIES
// ---------------------------------------------------------------

function debounce(callback, wait) {
    let timeoutId;
    return (...args) => {
        window.clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => callback(...args), wait);
    };
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function formatCurrency(value) {
    const amount = Number.parseFloat(value ?? '0');
    return `NPR ${Number.isFinite(amount) ? amount.toFixed(2) : '0.00'}`;
}
