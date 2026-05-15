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

        const getStockIcon = (cls) => {
            if (cls === 'out') return 'error';
            if (cls === 'low') return 'warning';
            return 'check_circle';
        };
        const getStockLabel = (cls) => {
            if (cls === 'out') return 'Out of Stock';
            if (cls === 'low') return 'Low Stock';
            return 'In Stock';
        };
        const getStockPct = (qty, min) => {
            if (min > 0) return Math.min(Math.round((qty / min) * 100), 100);
            return qty > 0 ? 100 : 0;
        };

        tableBody.innerHTML = products.map((product) => {
            const pct = getStockPct(product.quantity, product.min_stock);
            return `
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
                <td class="td-qty">
                    <div class="stock-qty-cell ${product.status_class}">
                        <span class="stock-qty-value">${product.quantity}</span>
                        <div class="stock-qty-bar">
                            <div class="stock-qty-fill ${product.status_class}" style="width: ${pct}%"></div>
                        </div>
                    </div>
                </td>
                <td class="td-price">${formatCurrency(product.unit_price)}</td>
                <td>
                    <span class="badge ${product.status_class}">
                        <span class="material-symbols-outlined badge-icon">${getStockIcon(product.status_class)}</span>
                        ${getStockLabel(product.status_class)}
                    </span>
                </td>
                <td class="td-actions"><div class="action-group">${buildActionButtons(product)}</div></td>
            </tr>
        `}).join('');
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
    // AI SALES INSIGHT CARD
    // ---------------------------------------------------------------
    const salesInsightCard = document.querySelector('[data-sales-insight-card]');
    const salesInsightStatus = document.querySelector('[data-sales-insight-status]');
    const salesInsightCopy = document.querySelector('[data-sales-insight-copy]');

    if (salesInsightCard && salesInsightStatus && salesInsightCopy) {
        const setSalesInsightState = (message, isLoading = false, isSummary = false) => {
            salesInsightStatus.textContent = message;
            salesInsightStatus.classList.toggle('is-loading', isLoading);
            salesInsightStatus.hidden = isSummary;
            salesInsightCopy.hidden = !isSummary;
            if (isSummary) {
                salesInsightCopy.textContent = message;
            }
        };

        setSalesInsightState('Generating insight...', true, false);

        fetch(salesInsightCard.dataset.endpoint, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.summary) {
                    throw new Error(payload.error || 'Insight unavailable');
                }

                setSalesInsightState(payload.summary, false, true);
            })
            .catch(() => {
                setSalesInsightState('Insight unavailable', false, false);
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
                notifList.innerHTML = '<li data-empty-notif><span class="material-symbols-outlined">notifications_off</span><div><strong>No new notifications</strong><small>All caught up!</small></div></li>';
                const dotBadge = document.querySelector('[data-unread-badge]');
                if (dotBadge) dotBadge.style.display = 'none';

                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                fetch(`${apiBase}/notifications.php?action=mark_all_read`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).catch(console.error);
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

    // ---------------------------------------------------------------
    // LOW STOCK ALERT GRAPH — Canvas bar chart
    // ---------------------------------------------------------------
    const graphContainer = document.getElementById('low-stock-graph-container');
    const canvas = document.getElementById('low-stock-canvas');
    const emptyState = document.getElementById('graph-empty-state');

    // Hoisted so auto-refresh can update graph data
    let allProducts = [];
    let currentFilter = 'low';
    let getFilteredData = () => [];
    let drawChart = () => {};

    if (graphContainer && canvas) {
        const ctx = canvas.getContext('2d');
        let barRects = []; // store bar positions for hover detection
        let animationProgress = 0;
        let animationFrame = null;

        // Tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'graph-tooltip';
        document.body.appendChild(tooltip);

        try {
            allProducts = JSON.parse(graphContainer.dataset.alertGraph || '[]');
        } catch (_e) {
            allProducts = [];
        }

        const isDark = () => body.classList.contains('theme-dark');

        getFilteredData = (filter) => {
            if (filter === 'low') {
                return allProducts.filter(p => parseInt(p.stock_quantity) <= parseInt(p.min_threshold));
            }
            return [...allProducts];
        };

        drawChart = (data, progress) => {
            const dpr = window.devicePixelRatio || 1;
            const containerWidth = graphContainer.clientWidth;
            const barHeight = 28;
            const barGap = 10;
            const labelWidth = 140;
            const valueWidth = 60;
            const paddingTop = 20;
            const paddingBottom = 20;
            const paddingRight = 20;
            const chartHeight = paddingTop + data.length * (barHeight + barGap) + paddingBottom;
            const minChartHeight = 320;
            const finalHeight = Math.max(chartHeight, minChartHeight);

            canvas.width = containerWidth * dpr;
            canvas.height = finalHeight * dpr;
            canvas.style.width = containerWidth + 'px';
            canvas.style.height = finalHeight + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            // Clear
            ctx.clearRect(0, 0, containerWidth, finalHeight);

            if (data.length === 0) {
                canvas.style.display = 'none';
                if (emptyState) emptyState.style.display = '';
                return;
            }
            canvas.style.display = '';
            if (emptyState) emptyState.style.display = 'none';

            const dark = isDark();
            const textColor = dark ? '#a9b4cc' : '#5f5f61';
            const gridColor = dark ? 'rgba(75, 89, 118, 0.3)' : 'rgba(203, 213, 225, 0.5)';
            const barAreaStart = labelWidth;
            const barAreaWidth = containerWidth - labelWidth - valueWidth - paddingRight;

            // Find max value for scaling
            const maxVal = Math.max(...data.map(p => Math.max(parseInt(p.stock_quantity), parseInt(p.min_threshold))), 1);

            // Draw grid lines
            const gridSteps = 5;
            ctx.textAlign = 'center';
            ctx.font = '10px Inter, sans-serif';
            ctx.fillStyle = textColor;
            for (let i = 0; i <= gridSteps; i++) {
                const x = barAreaStart + (barAreaWidth / gridSteps) * i;
                ctx.beginPath();
                ctx.strokeStyle = gridColor;
                ctx.lineWidth = 1;
                ctx.setLineDash([]);
                ctx.moveTo(x, paddingTop - 5);
                ctx.lineTo(x, paddingTop + data.length * (barHeight + barGap));
                ctx.stroke();

                const val = Math.round((maxVal / gridSteps) * i);
                ctx.fillText(val.toString(), x, paddingTop - 8);
            }

            barRects = [];

            data.forEach((product, index) => {
                const stock = parseInt(product.stock_quantity);
                const threshold = parseInt(product.min_threshold);
                const isLow = stock <= threshold;
                const y = paddingTop + index * (barHeight + barGap);

                // Product name (truncated)
                ctx.textAlign = 'right';
                ctx.font = '600 11px Inter, sans-serif';
                ctx.fillStyle = isLow ? (dark ? '#f58aa0' : '#9e3f4e') : textColor;
                let displayName = product.name;
                if (displayName.length > 18) displayName = displayName.substring(0, 17) + '…';
                ctx.fillText(displayName, labelWidth - 12, y + barHeight / 2 + 4);

                // Stock bar (animated)
                const stockWidth = Math.max((stock / maxVal) * barAreaWidth * progress, 0);
                const stockGrad = ctx.createLinearGradient(barAreaStart, 0, barAreaStart + stockWidth, 0);
                if (isLow) {
                    stockGrad.addColorStop(0, dark ? '#c45061' : '#e8596e');
                    stockGrad.addColorStop(1, dark ? '#933140' : '#c44058');
                } else {
                    stockGrad.addColorStop(0, dark ? '#6f8dff' : '#5a7aee');
                    stockGrad.addColorStop(1, dark ? '#4059aa' : '#4059aa');
                }
                ctx.fillStyle = stockGrad;
                ctx.beginPath();
                ctx.roundRect(barAreaStart, y + 2, stockWidth, barHeight - 4, 4);
                ctx.fill();

                // Threshold marker line
                const thresholdX = barAreaStart + (threshold / maxVal) * barAreaWidth;
                ctx.beginPath();
                ctx.strokeStyle = dark ? '#ff8b9a' : '#c45061';
                ctx.lineWidth = 2;
                ctx.setLineDash([4, 3]);
                ctx.moveTo(thresholdX, y);
                ctx.lineTo(thresholdX, y + barHeight);
                ctx.stroke();
                ctx.setLineDash([]);

                // Small diamond at threshold
                ctx.fillStyle = dark ? '#ff8b9a' : '#c45061';
                ctx.beginPath();
                ctx.moveTo(thresholdX, y - 2);
                ctx.lineTo(thresholdX + 4, y + 3);
                ctx.lineTo(thresholdX, y + 8);
                ctx.lineTo(thresholdX - 4, y + 3);
                ctx.closePath();
                ctx.fill();

                // Value label
                ctx.textAlign = 'left';
                ctx.font = '700 11px Inter, sans-serif';
                ctx.fillStyle = isLow ? (dark ? '#f58aa0' : '#9e3f4e') : (dark ? '#6f8dff' : '#4059aa');
                ctx.fillText(stock.toString(), barAreaStart + stockWidth + 8, y + barHeight / 2 + 4);

                // Store rect for hover
                barRects.push({
                    x: barAreaStart,
                    y: y,
                    width: barAreaWidth,
                    height: barHeight,
                    product: product,
                    stock: stock,
                    threshold: threshold,
                    isLow: isLow,
                });
            });
        };

        const animateChart = (data) => {
            if (animationFrame) cancelAnimationFrame(animationFrame);
            animationProgress = 0;
            const startTime = performance.now();
            const duration = 600;

            const step = (now) => {
                const elapsed = now - startTime;
                animationProgress = Math.min(elapsed / duration, 1);
                // Ease out cubic
                const eased = 1 - Math.pow(1 - animationProgress, 3);
                drawChart(data, eased);
                if (animationProgress < 1) {
                    animationFrame = requestAnimationFrame(step);
                }
            };
            animationFrame = requestAnimationFrame(step);
        };

        // Initial draw
        const initialData = getFilteredData(currentFilter);
        animateChart(initialData);

        // Filter buttons
        document.querySelectorAll('[data-graph-filter]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-graph-filter]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentFilter = btn.dataset.graphFilter;
                const data = getFilteredData(currentFilter);
                animateChart(data);
            });
        });

        // Hover tooltip
        canvas.addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            let hoveredBar = null;
            for (const bar of barRects) {
                if (x >= bar.x && x <= bar.x + bar.width && y >= bar.y && y <= bar.y + bar.height) {
                    hoveredBar = bar;
                    break;
                }
            }

            if (hoveredBar) {
                canvas.style.cursor = 'pointer';
                const deficit = hoveredBar.threshold - hoveredBar.stock;
                const pct = hoveredBar.threshold > 0
                    ? Math.round((hoveredBar.stock / hoveredBar.threshold) * 100)
                    : 100;

                tooltip.innerHTML = `
                    <strong>${escapeHtml(hoveredBar.product.name)}</strong>
                    <div class="tt-row">
                        <span><span class="tt-dot" style="background:#5a7aee;"></span>Stock</span>
                        <span>${hoveredBar.stock}</span>
                    </div>
                    <div class="tt-row">
                        <span><span class="tt-dot" style="background:#c45061;"></span>Threshold</span>
                        <span>${hoveredBar.threshold}</span>
                    </div>
                    ${hoveredBar.isLow
                        ? `<div class="tt-row" style="color:#ff8b9a;margin-top:2px;">
                               <span>Deficit</span>
                               <span>−${deficit} (${pct}%)</span>
                           </div>`
                        : `<div class="tt-row" style="color:#6fd9a5;margin-top:2px;">
                               <span>Status</span>
                               <span>Healthy (${pct}%)</span>
                           </div>`
                    }
                `;

                tooltip.classList.add('visible');
                let tooltipX = e.clientX + 14;
                let tooltipY = e.clientY - 10;
                // Keep tooltip in viewport
                const ttRect = tooltip.getBoundingClientRect();
                if (tooltipX + ttRect.width > window.innerWidth - 10) {
                    tooltipX = e.clientX - ttRect.width - 14;
                }
                tooltip.style.left = tooltipX + 'px';
                tooltip.style.top = tooltipY + 'px';
            } else {
                canvas.style.cursor = 'default';
                tooltip.classList.remove('visible');
            }
        });

        canvas.addEventListener('mouseleave', () => {
            tooltip.classList.remove('visible');
            canvas.style.cursor = 'default';
        });

        // Responsive redraw
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                const data = getFilteredData(currentFilter);
                drawChart(data, 1);
            }, 150);
        });
    }

    // ---------------------------------------------------------------
    // DASHBOARD AUTO-REFRESH — Poll every 2 seconds
    // ---------------------------------------------------------------
    const dashboardStats   = document.getElementById('dashboard-stats');
    const liveIndicator    = document.getElementById('live-indicator');

    if (dashboardStats) {
        const statEls = {
            totalProducts:  document.getElementById('stat-total-products'),
            totalValue:     document.getElementById('stat-total-value'),
            healthPct:      document.getElementById('stat-health-pct'),
            criticalCount:  document.getElementById('stat-critical-count'),
            pendingPo:      document.getElementById('stat-pending-po'),
            totalSuppliers: document.getElementById('stat-total-suppliers'),
            outOfStock:     document.getElementById('stat-out-of-stock'),
        };
        const featuredBody = document.getElementById('featured-products-body');
        const alertsBody   = document.getElementById('alerts-table-body');
        const activityBody = document.getElementById('activity-table-body');

        /** Flash an element when its value changes */
        const flashIfChanged = (el, newText) => {
            if (!el) return;
            const oldText = el.textContent.trim();
            if (oldText !== newText.trim()) {
                el.textContent = newText;
                el.classList.remove('stat-flash');
                void el.offsetWidth; // trigger reflow
                el.classList.add('stat-flash');
            }
        };

        /** Determine stock status class */
        const stockClass = (qty, threshold) => {
            if (qty === 0) return 'out';
            if (qty <= threshold) return 'low';
            return 'healthy';
        };
        const stockBadgeText = (cls) => {
            if (cls === 'out') return 'OUT OF STOCK';
            if (cls === 'low') return 'LOW STOCK';
            return 'IN STOCK';
        };

        const refreshDashboard = async () => {
            try {
                const response = await fetch(`${apiBase}/dashboard.php`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    if (liveIndicator) liveIndicator.classList.add('stale');
                    return;
                }
                const data = await response.json();
                if (liveIndicator) liveIndicator.classList.remove('stale');

                // --- Update stat cards ---
                if (data.stats) {
                    const s = data.stats;
                    flashIfChanged(statEls.totalProducts,  String(s.total_products ?? 0));
                    flashIfChanged(statEls.totalValue,     'NPR ' + Number(s.total_value ?? 0).toLocaleString());
                    flashIfChanged(statEls.healthPct,      (s.health_percentage ?? 0) + '%');
                    flashIfChanged(statEls.criticalCount,  String(s.critical_count ?? 0));
                    flashIfChanged(statEls.pendingPo,      String(s.pending_po ?? 0));
                    flashIfChanged(statEls.totalSuppliers, String(s.total_suppliers ?? 0));
                    flashIfChanged(statEls.outOfStock,     String(s.out_of_stock ?? 0));
                }

                // --- Update featured products table ---
                if (featuredBody && data.featured_products) {
                    const rows = data.featured_products.map((p) => {
                        const qty = parseInt(p.stock_quantity);
                        const thr = parseInt(p.min_threshold);
                        const cls = stockClass(qty, thr);
                        return `<tr>
                            <td>${escapeHtml(p.name)}</td>
                            <td>${escapeHtml(p.sku)}</td>
                            <td>${escapeHtml(p.category_name || 'Unassigned')}</td>
                            <td><strong class="stock-inline ${cls}">${qty}</strong></td>
                            <td><span class="badge ${cls}">${stockBadgeText(cls)}</span></td>
                        </tr>`;
                    });
                    featuredBody.innerHTML = rows.join('');
                }

                // --- Update low stock watchlist ---
                if (alertsBody && data.alerts) {
                    const activeAlerts = data.alerts.filter(a => parseInt(a.stock_quantity) > 0);
                    if (activeAlerts.length === 0) {
                        alertsBody.innerHTML = '<tr><td colspan="4">No low-stock items right now.</td></tr>';
                    } else {
                        alertsBody.innerHTML = activeAlerts.map((a) => `<tr>
                            <td>${escapeHtml(a.name)}</td>
                            <td>${escapeHtml(a.sku)}</td>
                            <td><strong class="stock-inline low">${escapeHtml(String(a.stock_quantity))}</strong></td>
                            <td>${escapeHtml(String(a.min_threshold))}</td>
                        </tr>`).join('');
                    }
                }

                // --- Update recent activity table ---
                if (activityBody && data.recent_activity) {
                    if (data.recent_activity.length === 0) {
                        activityBody.innerHTML = '<tr><td colspan="4">No recent stock activity yet.</td></tr>';
                    } else {
                        activityBody.innerHTML = data.recent_activity.map((ev) => `<tr>
                            <td>${escapeHtml(ev.product_name)}</td>
                            <td>${escapeHtml((ev.movement_type || '').toUpperCase())}</td>
                            <td>${escapeHtml(String(ev.quantity))}</td>
                            <td>${escapeHtml(ev.full_name)}</td>
                        </tr>`).join('');
                    }
                }

                // --- Update alert graph ---
                if (data.alert_graph && typeof allProducts !== 'undefined') {
                    allProducts = data.alert_graph;
                    const graphData = getFilteredData(currentFilter);
                    drawChart(graphData, 1);
                }
            } catch (_err) {
                if (liveIndicator) liveIndicator.classList.add('stale');
            }
        };

        // Poll every 2 seconds
        setInterval(refreshDashboard, 2000);
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
