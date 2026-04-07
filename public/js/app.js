document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const basePath = body.dataset.basePath || '';
    const csrfToken = body.dataset.csrfToken || '';
    const storedTheme = window.localStorage.getItem('inventra_theme') || 'light';
    body.classList.toggle('theme-dark', storedTheme === 'dark');

    const searchInput = document.querySelector('#live-search');
    const categoryFilter = document.querySelector('#category-filter');
    const stockFilter = document.querySelector('#stock-filter');
    const tableBody = document.querySelector('#product-table-body');

    const buildActionButtons = (product) => {
        const actions = [];

        if (product.can_edit) {
            actions.push(`<a class="button small ghost" href="${product.edit_url}">Edit</a>`);
        }

        if (product.can_archive) {
            actions.push(`
                <form method="post" action="${basePath}/index.php?page=products">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="action" value="archive_product">
                    <input type="hidden" name="product_id" value="${product.id}">
                    <button class="button small ghost" type="submit">Archive</button>
                </form>
            `);
        }

        if (product.can_delete) {
            actions.push(`
                <form method="post" action="${basePath}/index.php?page=products" onsubmit="return confirm('Delete this product?');">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="${product.id}">
                    <button class="button small danger-outline" type="submit">Delete</button>
                </form>
            `);
        }

        return actions.join('');
    };

    const renderProducts = (products) => {
        if (!tableBody) {
            return;
        }

        if (!products.length) {
<<<<<<< HEAD
            tableBody.innerHTML = '<tr><td colspan="5">No products match the current filters.</td></tr>';
=======
            tableBody.innerHTML = '<tr><td colspan="6">No products match the current filters.</td></tr>';
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508
            return;
        }

        tableBody.innerHTML = products.map((product) => `
            <tr data-product-id="${product.id}">
                <td>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:44px;height:44px;border-radius:10px;background:var(--surface-mid);display:grid;place-items:center;">
                            <span class="material-symbols-outlined" style="color:${product.status_class === 'low' ? 'var(--error)' : 'var(--primary)'};">
                                ${product.status_class === 'low' ? 'precision_manufacturing' : 'architecture'}
                            </span>
                        </div>
                        <div>
                            <div style="font-weight:700;">${escapeHtml(product.name)}</div>
                            <div style="font-size:0.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;">
                                ${escapeHtml(product.category)} / ${escapeHtml(product.supplier)}
                            </div>
                        </div>
                    </div>
                </td>
                <td style="font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;font-size:0.8rem;">${escapeHtml(product.sku)}</td>
<<<<<<< HEAD
=======
                <td style="font-weight:700;">${formatCurrency(product.price_npr)}</td>
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508
                <td style="font-weight:800;">${product.quantity}</td>
                <td><span class="badge ${product.status_class}">${product.status_class === 'low' ? 'Low Stock Level' : 'Stable Stock'}</span></td>
                <td class="action-group">${buildActionButtons(product)}</td>
            </tr>
        `).join('');
    };

    const fetchProducts = debounce(async () => {
        if (!searchInput || !tableBody) {
            return;
        }

        const params = new URLSearchParams({
            keyword: searchInput.value.trim(),
            category: categoryFilter ? categoryFilter.value : '',
            stock_level: stockFilter ? stockFilter.value : '',
        });

        try {
            const response = await fetch(`${basePath}/../api/products.php?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            renderProducts(payload.products || []);
        } catch (_error) {
            // Keep server-rendered table as fallback.
        }
    }, 250);

    [searchInput, categoryFilter, stockFilter].forEach((element) => {
        if (element) {
            element.addEventListener('input', fetchProducts);
            element.addEventListener('change', fetchProducts);
        }
    });

    document.querySelectorAll('.stepper-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.stepperTarget;
            const step = parseInt(button.dataset.step || '0', 10);
            const input = document.getElementById(targetId);

            if (!input) {
                return;
            }

            const currentValue = parseInt(input.value || '0', 10);
            input.value = Math.max(0, currentValue + step);
        });
    });

    const stockForm = document.querySelector('.ajax-stock-form');
    const stockFeedback = document.querySelector('#stock-feedback');
    const profileMenu = document.querySelector('[data-profile-menu]');
    const profileTrigger = document.querySelector('[data-profile-trigger]');
    const notificationsMenu = document.querySelector('[data-notifications-menu]');
    const notificationsTrigger = document.querySelector('[data-notifications-trigger]');
    const settingsMenu = document.querySelector('[data-settings-menu]');
    const settingsTrigger = document.querySelector('[data-settings-trigger]');
    const themeToggle = document.querySelector('[data-theme-toggle]');
    const clearNotificationsButton = document.querySelector('[data-clear-notifications]');
    const notifList = document.querySelector('[data-notif-list]');

    if (stockForm) {
        stockForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(stockForm);
            try {
                const response = await fetch(`${basePath}/../api/stock.php`, {
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
            if (!profileMenu.contains(event.target) && !notificationsMenu?.contains(event.target) && !settingsMenu?.contains(event.target)) {
                closeAllMenus();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllMenus();
            }
        });
    }
});

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
<<<<<<< HEAD
=======

function formatCurrency(value) {
    const amount = Number.parseFloat(value ?? '0');
    return `NPR ${Number.isFinite(amount) ? amount.toFixed(2) : '0.00'}`;
}
>>>>>>> c693994f150c6bf96d00faef170e39b16d550508
