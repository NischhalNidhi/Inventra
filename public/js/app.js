document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const basePath = body.dataset.basePath || '';
    const appRoot = body.dataset.appRootPath || (basePath.endsWith('/public') ? basePath.slice(0, -7) : '');
    const apiBase = `${appRoot}/api`;
    const csrfToken = body.dataset.csrfToken || '';
    const storedTheme = window.localStorage.getItem('inventra_theme') || 'light';
    body.classList.toggle('theme-dark', storedTheme === 'dark');

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
        if (!tableBody) { return; }

        if (!products.length) {
            tableBody.innerHTML = '<tr><td colspan="6">No products match the current filters.</td></tr>';
            return;
        }

        tableBody.innerHTML = products.map((product) => `
            <tr data-product-id="${product.id}">
                <td>
                    <div style="display:flex;align-items:center;gap:14px;">
                        <button type="button" class="media-thumb-button" data-image-trigger data-image-src="${escapeHtml(product.image_url)}" data-image-title="${escapeHtml(product.name)}">
                            <img src="${escapeHtml(product.image_url)}" alt="${escapeHtml(product.name)}" class="media-thumb media-thumb-product">
                        </button>
                        <div>
                            <div style="font-weight:700;">${escapeHtml(product.name)}</div>
                            <div style="font-size:0.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;">
                                ${escapeHtml(product.category)} / ${escapeHtml(product.supplier)}
                            </div>
                        </div>
                    </div>
                </td>
                <td style="font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;font-size:0.8rem;">${escapeHtml(product.sku)}</td>
                <td style="font-weight:800;">${product.quantity}</td>
                <td style="font-weight:700;">${formatCurrency(product.unit_price)}</td>
                <td><span class="badge ${product.status_class}">${product.status_class === 'low' ? 'Low Stock' : 'In Stock'}</span></td>
                <td class="action-group">${buildActionButtons(product)}</td>
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
    // AI SALES INSIGHT CARD
    // ---------------------------------------------------------------
    document.querySelectorAll('[data-sales-insight-card]').forEach((salesInsightCard) => {
        const salesInsightStatus = salesInsightCard.querySelector('[data-sales-insight-status]');
        const salesInsightCopy = salesInsightCard.querySelector('[data-sales-insight-copy]');

        if (!salesInsightStatus || !salesInsightCopy) { return; }

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
    });

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
    // IMAGE LIGHTBOX
    // ---------------------------------------------------------------
    const lightbox = document.querySelector('[data-image-lightbox]');
    const lightboxImage = document.querySelector('[data-lightbox-image]');
    const lightboxCaption = document.querySelector('[data-lightbox-caption]');
    const lightboxCloseTargets = document.querySelectorAll('[data-lightbox-close]');

    const closeLightbox = () => {
        if (!lightbox || !lightboxImage) { return; }
        lightbox.hidden = true;
        lightboxImage.setAttribute('src', '');
        lightboxImage.setAttribute('alt', '');
        if (lightboxCaption) {
            lightboxCaption.textContent = '';
        }
        body.classList.remove('lightbox-open');
    };

    const openLightbox = (src, title) => {
        if (!lightbox || !lightboxImage) { return; }
        lightbox.hidden = false;
        lightboxImage.setAttribute('src', src);
        lightboxImage.setAttribute('alt', title || 'Preview image');
        if (lightboxCaption) {
            lightboxCaption.textContent = title || '';
        }
        body.classList.add('lightbox-open');
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-image-trigger]');
        if (!trigger) { return; }

        event.preventDefault();
        openLightbox(trigger.dataset.imageSrc || '', trigger.dataset.imageTitle || '');
    });

    lightboxCloseTargets.forEach((target) => {
        target.addEventListener('click', closeLightbox);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && lightbox && !lightbox.hidden) {
            closeLightbox();
        }
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

    // Close all menus - this is now called independently
    const closeAllMenus = () => {
        profileMenu?.classList.remove('open');
        notificationsMenu?.classList.remove('open');
        settingsMenu?.classList.remove('open');
        profileTrigger?.setAttribute('aria-expanded', 'false');
        notificationsTrigger?.setAttribute('aria-expanded', 'false');
        settingsTrigger?.setAttribute('aria-expanded', 'false');
    };

    // Profile menu
    if (profileMenu && profileTrigger) {
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
    }

    // Notifications menu - INDEPENDENT of profile menu
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

    // Settings menu - INDEPENDENT of profile menu
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

    // Theme toggle - INDEPENDENT of any menu
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = body.classList.contains('theme-dark') ? 'light' : 'dark';
            body.classList.toggle('theme-dark', nextTheme === 'dark');
            window.localStorage.setItem('inventra_theme', nextTheme);
        });
    }

    // Clear notifications button - INDEPENDENT
    if (clearNotificationsButton && notifList) {
        clearNotificationsButton.addEventListener('click', () => {
            notifList.innerHTML = '<li><span class="material-symbols-outlined">notifications_off</span><div><strong>No new notifications</strong><small>All notifications have been cleared.</small></div></li>';
        });
    }

    // Close menus when clicking outside - apply to all menus
    document.addEventListener('click', (event) => {
        if (
            !profileMenu?.contains(event.target) &&
            !notificationsMenu?.contains(event.target) &&
            !settingsMenu?.contains(event.target)
        ) {
            closeAllMenus();
        }
    });

    // Close menus on Escape key
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { closeAllMenus(); }
    });

    // ---------------------------------------------------------------
    // LOW STOCK ALERT GRAPH — Canvas bar chart
    // ---------------------------------------------------------------
    const graphContainer = document.getElementById('low-stock-graph-container');
    const canvas = document.getElementById('low-stock-canvas');
    const emptyState = document.getElementById('graph-empty-state');

    if (graphContainer && canvas) {
        const ctx = canvas.getContext('2d');
        let allProducts = [];
        let currentFilter = 'low';
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

        const getFilteredData = (filter) => {
            if (filter === 'low') {
                return allProducts.filter(p => parseInt(p.stock_quantity) <= parseInt(p.min_threshold));
            }
            return [...allProducts];
        };

        const drawChart = (data, progress) => {
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
    // AI PRODUCT DISTRIBUTION HEAT MAP
    // ---------------------------------------------------------------
    const heatmap = document.querySelector('[data-product-distribution-heatmap]');
    if (heatmap) {
        const grid = heatmap.querySelector('[data-heatmap-grid]');
        const emptyState = heatmap.querySelector('[data-heatmap-empty]');
        let rows = [];

        try {
            rows = JSON.parse(heatmap.dataset.heatmapRows || '[]');
        } catch (_error) {
            rows = [];
        }

        const categories = [...new Set(rows.map((row) => row.category_name || 'Unassigned'))];
        const statuses = [
            { key: 'healthy', label: 'Healthy' },
            { key: 'low', label: 'Low Stock' },
            { key: 'out', label: 'Out Of Stock' },
        ];

        const getStatusKey = (row) => {
            const quantity = Number.parseInt(row.stock_quantity || '0', 10);
            const threshold = Number.parseInt(row.min_threshold || '0', 10);
            if (quantity <= 0) { return 'out'; }
            if (quantity <= threshold) { return 'low'; }
            return 'healthy';
        };

        if (!rows.length || !grid) {
            if (emptyState) {
                emptyState.hidden = false;
            }
        } else {
            if (emptyState) {
                emptyState.hidden = true;
            }

            const matrix = {};
            let maxCount = 0;

            categories.forEach((category) => {
                matrix[category] = {};
                statuses.forEach((status) => {
                    matrix[category][status.key] = {
                        count: 0,
                        units: 0,
                        value: 0,
                    };
                });
            });

            rows.forEach((row) => {
                const category = row.category_name || 'Unassigned';
                const statusKey = getStatusKey(row);
                const quantity = Number.parseInt(row.stock_quantity || '0', 10);
                const price = Number.parseFloat(row.unit_price || '0');
                const cell = matrix[category][statusKey];
                cell.count += 1;
                cell.units += quantity;
                cell.value += quantity * price;
                maxCount = Math.max(maxCount, cell.count);
            });

            const cells = [];
            cells.push('<div class="heatmap-corner">Category / Status</div>');
            // statuses.forEach((status) => {
            //     cells.push(`<div class="heatmap-header">${escapeHtml(status.label)}</div>`);
            // });

            categories.forEach((category) => {
                cells.push('<div class="heatmap-row-label">' + escapeHtml(category) + '</div>');
                statuses.forEach((status) => {
                    const cell = matrix[category][status.key];
                    const intensity = maxCount > 0 ? (cell.count / maxCount) : 0;
                    const alpha = 0.12 + (intensity * 0.78);
                    const colorMap = {
                        healthy: 'rgba(46, 138, 98, ' + alpha + ')',
                        low: 'rgba(203, 77, 93, ' + alpha + ')',
                        out: 'rgba(122, 127, 143, ' + alpha + ')',
                    };
                    const title = category + ' | ' + status.label + '\nProducts: ' + cell.count + '\nUnits: ' + cell.units + '\nInventory value: NPR ' + cell.value.toFixed(2);
                    cells.push(`
                        <button type="button" class="heatmap-cell" title="${escapeHtml(title)}" style="background:${colorMap[status.key]}">
                            <strong>${cell.count}</strong>
                            <small>${cell.units} units</small>
                        </button>
                    `);
                });
            });

            grid.innerHTML = cells.join('');
            grid.style.gridTemplateColumns = `minmax(180px, 1.3fr) repeat(${statuses.length}, minmax(120px, 1fr))`;
        }
    }
    // ---------------------------------------------------------------
    // SALES REPORT FILTERS
    // ---------------------------------------------------------------
    document.querySelectorAll('.sales-filter-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const type = form.dataset.salesFilter; // 'monthly' or 'daily'
            const fromDateInput = form.querySelector('input[name="from_date"]');
            const toDateInput = form.querySelector('input[name="to_date"]');
            const fromDate = fromDateInput ? fromDateInput.value : '';
            const toDate = toDateInput ? toDateInput.value : '';

            if (fromDate && toDate && new Date(fromDate) > new Date(toDate)) {
                alert('End date must be after start date.');
                return;
            }

            const table = document.querySelector(`[data-sales-table="${type}"]`);
            if (!table) {
                return;
            }

            const tbody = table.querySelector('tbody');
            const detailedTable = document.querySelector('[data-sales-table="daily-detailed"]');
            const detailedTbody = detailedTable ? detailedTable.querySelector('tbody') : null;

            tbody.innerHTML = '<tr><td colspan="2">Loading...</td></tr>';
            if (detailedTbody) detailedTbody.innerHTML = '<tr><td colspan="9">Loading...</td></tr>';

            try {
                const params = new URLSearchParams();
                params.append('type', `sales-${type}`);
                if (fromDate) params.append('from_date', fromDate);
                if (toDate) params.append('to_date', toDate);

                const response = await fetch(`${apiBase}/reports.php?${params.toString()}`);
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || 'Failed to load sales data.');
                }

                const data = await response.json();
                
                const summaryRows = type === 'monthly' ? data.rows : data.summary;
                
                if (summaryRows && summaryRows.length > 0) {
                    tbody.innerHTML = summaryRows.map((row) => {
                        if (type === 'monthly') {
                            return `<tr>
                                <td>${escapeHtml(row.month)}</td>
                                <td><strong>${escapeHtml(row.transactions)}</strong></td>
                                <td>${escapeHtml(row.units_sold)}</td>
                                <td>${formatCurrency(row.total)}</td>
                            </tr>`;
                        }
                        return `<tr>
                            <td>${escapeHtml(row.sale_date)}</td>
                            <td><strong>${escapeHtml(row.transactions)}</strong></td>
                            <td>${escapeHtml(row.units_sold)}</td>
                            <td>${formatCurrency(row.total)}</td>
                        </tr>`;
                    }).join('');

                    if (type === 'daily' && detailedTbody && data.detailed) {
                        detailedTbody.innerHTML = data.detailed.map(row => `
                            <tr>
                                <td><small>${escapeHtml(row.sale_date)}</small></td>
                                <td><code>${escapeHtml(row.invoice_id)}</code></td>
                                <td><strong>${escapeHtml(row.product_name)}</strong></td>
                                <td>${escapeHtml(row.category_name)}</td>
                                <td>${escapeHtml(row.quantity)}</td>
                                <td>${Number(row.unit_price).toFixed(2)}</td>
                                <td><strong>${formatCurrency(row.total)}</strong></td>
                                <td><small>${escapeHtml(row.payment_method)}</small></td>
                                <td><small>${escapeHtml(row.region)}</small></td>
                            </tr>
                        `).join('');
                    }
                } else {
                    tbody.innerHTML = '<tr><td colspan="2">No data found for the selected date range.</td></tr>';
                }

                const exportButton = document.querySelector(`a[href*="export-${type}-csv"]`);
                if (exportButton) {
                    const exportParams = new URLSearchParams();
                    exportParams.append('type', `export-${type}-csv`);
                    if (fromDate) exportParams.append('from_date', fromDate);
                    if (toDate) exportParams.append('to_date', toDate);
                    exportButton.href = `${appRoot}/api/reports.php?${exportParams.toString()}`;
                }
            } catch (error) {
                tbody.innerHTML = '<tr><td colspan="2">Error loading data. Please try again.</td></tr>';
                console.error('Sales filter error:', error);
            }
        });
    });
});
