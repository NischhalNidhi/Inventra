<?php
if (currentUser()['role'] !== 'Manager') {
    http_response_code(403);
    die('Forbidden: Access Restricted to Managers Only.');
}

require __DIR__ . '/../../core/layout/header.php';
?>

<div class="heatmap-page-container" style="padding: 1.5rem 2rem; max-width: 1400px; margin: 0 auto; font-family: 'Inter', sans-serif; color: var(--text);">
    
    <!-- Topbar Header -->
    <header class="topbar" style="margin-bottom: 2rem;">
        <div>
            <p class="eyebrow" style="text-transform: uppercase; font-size: 0.85rem; font-weight: 700; color: var(--primary); letter-spacing: 0.1em; margin-bottom: 0.25rem;">Advanced Analytics</p>
            <h1 style="font-size: 2.25rem; font-weight: 800; background: linear-gradient(135deg, var(--primary) 0%, #a855f7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0;">Geographic Sales Heatmap</h1>
            <p class="lead" style="color: var(--text-muted); font-size: 1rem; margin-top: 0.25rem;">Interactive visualization of product sales distribution across regional divisions with AI intelligence.</p>
        </div>
    </header>

    <!-- Glassmorphic Filter Controls Panel -->
    <section class="panel filters-panel" style="background: var(--surface); border: 1px solid var(--outline-variant); border-radius: 20px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.04); transition: transform 0.3s ease;">
        <form id="heatmap-filters-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; align-items: flex-end;">
            
            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label for="product_filter" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">Filter by Product</label>
                <select id="product_filter" name="product_id" style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--outline-variant); background: var(--surface-low); color: var(--text); outline: none; font-size: 0.9rem; transition: border-color 0.2s ease;">
                    <option value="">All Products</option>
                    <?php foreach ($products as $prod): ?>
                        <option value="<?= e((string)$prod['id']); ?>"><?= e($prod['name']); ?> (<?= e($prod['sku']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label for="from_date_filter" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">Start Date</label>
                <input type="date" id="from_date_filter" name="from_date" style="width: 100%; padding: 0.7rem; border-radius: 12px; border: 1px solid var(--outline-variant); background: var(--surface-low); color: var(--text); outline: none; font-size: 0.9rem; transition: border-color 0.2s ease;">
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label for="to_date_filter" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">End Date</label>
                <input type="date" id="to_date_filter" name="to_date" style="width: 100%; padding: 0.7rem; border-radius: 12px; border: 1px solid var(--outline-variant); background: var(--surface-low); color: var(--text); outline: none; font-size: 0.9rem; transition: border-color 0.2s ease;">
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label for="region_filter" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">Filter by Region</label>
                <select id="region_filter" name="region" style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--outline-variant); background: var(--surface-low); color: var(--text); outline: none; font-size: 0.9rem; transition: border-color 0.2s ease;">
                    <option value="">All Regions</option>
                    <?php foreach ($regions as $reg): ?>
                        <option value="<?= e($reg); ?>"><?= e($reg); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <button type="submit" class="button primary" style="padding: 0.75rem 1.25rem; font-size: 0.9rem; border-radius: 12px; cursor: pointer; transition: background-color 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 0.25rem;">
                    <span class="material-symbols-outlined" style="font-size: 1.1rem; vertical-align: middle;">filter_alt</span>Apply
                </button>
                <button type="button" id="reset-filters-btn" class="button ghost" style="padding: 0.75rem 1.25rem; font-size: 0.9rem; border-radius: 12px; cursor: pointer; transition: background-color 0.2s ease;">
                    <span class="material-symbols-outlined" style="font-size: 1.1rem; vertical-align: middle; margin-right: 0.25rem;">restart_alt</span>Reset
                </button>
            </div>

        </form>
    </section>

    <!-- Top AI Insight Panel -->
    <section class="panel heatmap-ai-panel" style="border-radius: 20px; padding: 1.75rem; margin-bottom: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.08); position: relative; overflow: hidden; min-height: 120px; display: flex; align-items: center;">
        <!-- Backdrop glow effects -->
        <div style="position: absolute; top: -10%; right: -10%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(168,85,247,0.18) 0%, transparent 70%); pointer-events: none;"></div>
        <div style="position: absolute; bottom: -10%; left: -10%; width: 250px; height: 250px; background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, transparent 70%); pointer-events: none;"></div>

        <div style="display: flex; gap: 1.25rem; align-items: flex-start; z-index: 2; width: 100%;">
            <div style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 14px; padding: 0.75rem; display: flex; align-items: center; justify-content: center; height: 50px; width: 50px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ai-insight-icon" style="animation: pulse 2s infinite;"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path></svg>
            </div>
            <div style="flex: 1;">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                    AI Geographic Distribution Analysis
                </h3>
                <div id="ai-insight-container" style="font-size: 0.95rem; line-height: 1.6;">
                    <!-- Loading or Content -->
                    <div id="ai-insight-loading" style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="spinner-mini" style="display: inline-block; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.2); border-top-color: #c084fc; border-radius: 50%; animation: spin 0.8s linear infinite;"></span>
                        Generating real-time executive distribution insights...
                    </div>
                    <div id="ai-insight-content" style="display: none;"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Workspace Layout -->
    <div class="heatmap-grid">
        
        <!-- Interactive Heatmap SVG Card -->
        <section class="panel" style="background: var(--surface); border: 1px solid var(--outline-variant); border-radius: 20px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.02); min-height: 520px; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
            <div class="panel-header heatmap-panel-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-symbols-outlined" style="color: var(--primary);">map</span> Regional Distribution Map
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.15rem;">Hover over regions to inspect individual performance share metrics.</p>
                </div>
                <!-- Mini Color Map Legend -->
                <div class="heatmap-legend" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; font-weight: 600; color: var(--text-muted);">
                    <span>Cool (Low Volume)</span>
                    <div style="width: 100px; height: 8px; background: linear-gradient(90deg, #3b82f6 0%, #ef4444 100%); border-radius: 99px;"></div>
                    <span>Warm (High Volume)</span>
                </div>
            </div>

            <!-- Stylized Interactive Map Container -->
            <div id="heatmap-svg-container" style="flex: 1; display: flex; align-items: center; justify-content: center; background: var(--surface-low); border: 1px solid var(--outline-variant); border-radius: 16px; padding: 2rem; position: relative; overflow: hidden; min-height: 380px;">
                <svg id="interactive-heatmap-map" viewBox="0 0 800 500" width="100%" height="100%" style="max-height: 420px; border-radius: 12px;">
                    <defs>
                        <!-- Glow filters and gradients -->
                        <filter id="glow-effect" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="12" result="blur" />
                            <feComposite in="SourceGraphic" in2="blur" operator="over" />
                        </filter>
                    </defs>

                    <!-- Background abstract nodes to look highly sophisticated -->
                    <g opacity="0.05">
                        <circle cx="100" cy="100" r="80" fill="currentColor"/>
                        <circle cx="700" cy="400" r="120" fill="currentColor"/>
                        <line x1="100" y1="100" x2="700" y2="400" stroke="currentColor" stroke-width="2" stroke-dasharray="8 8"/>
                    </g>

                    <!-- Nepal / Stylized stores regions paths -->
                    <!-- Central Hub -->
                    <path id="region-Central-Hub" class="heatmap-region" data-region="Central Hub" d="M 300,180 L 500,180 L 550,300 L 350,300 Z" 
                          fill="rgba(59,130,246,0.15)" stroke="var(--outline-variant)" stroke-width="2" style="cursor: pointer; transition: all 0.3s ease;"></path>
                    
                    <!-- North Store -->
                    <path id="region-North-Store" class="heatmap-region" data-region="North Store" d="M 250,50 L 550,50 L 500,180 L 300,180 Z" 
                          fill="rgba(59,130,246,0.15)" stroke="var(--outline-variant)" stroke-width="2" style="cursor: pointer; transition: all 0.3s ease;"></path>

                    <!-- East Mall -->
                    <path id="region-East-Mall" class="heatmap-region" data-region="East Mall" d="M 500,180 L 700,100 L 750,320 L 550,300 Z" 
                          fill="rgba(59,130,246,0.15)" stroke="var(--outline-variant)" stroke-width="2" style="cursor: pointer; transition: all 0.3s ease;"></path>

                    <!-- West Plaza -->
                    <path id="region-West-Plaza" class="heatmap-region" data-region="West Plaza" d="M 100,100 L 300,180 L 350,300 L 80,320 Z" 
                          fill="rgba(59,130,246,0.15)" stroke="var(--outline-variant)" stroke-width="2" style="cursor: pointer; transition: all 0.3s ease;"></path>

                    <!-- South Store -->
                    <path id="region-South-Store" class="heatmap-region" data-region="South Store" d="M 350,300 L 550,300 L 500,450 L 300,450 Z" 
                          fill="rgba(59,130,246,0.15)" stroke="var(--outline-variant)" stroke-width="2" style="cursor: pointer; transition: all 0.3s ease;"></path>

                    <!-- Center Markers or Labels inside regions -->
                    <g pointer-events="none" style="font-size: 13px; font-weight: 700; fill: var(--text);">
                        <!-- North -->
                        <text x="400" y="115" text-anchor="middle">North Store</text>
                        <!-- Central -->
                        <text x="425" y="240" text-anchor="middle">Central Hub</text>
                        <!-- East -->
                        <text x="620" y="220" text-anchor="middle">East Mall</text>
                        <!-- West -->
                        <text x="210" y="210" text-anchor="middle">West Plaza</text>
                        <!-- South -->
                        <text x="425" y="375" text-anchor="middle">South Store</text>
                    </g>
                </svg>

                <!-- Loading spinner for Map data -->
                <div id="map-data-loading" style="display: none; position: absolute; inset: 0; background: rgba(0,0,0,0.4); border-radius: 16px; align-items: center; justify-content: center; flex-direction: column; gap: 1rem; color: #fff; z-index: 10;">
                    <span style="display: block; width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.2); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite;"></span>
                    <span>Updating sales heatmap...</span>
                </div>
            </div>
        </section>

        <!-- Sidebar Statistics Panel -->
        <section class="panel" style="background: var(--surface); border: 1px solid var(--outline-variant); border-radius: 20px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
            <div class="panel-header" style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span class="material-symbols-outlined" style="color: var(--primary);">bar_chart</span> Regional Performance
                </h2>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.15rem;">Details on volume, revenue, and relative percentage share.</p>
            </div>

            <div style="flex: 1; display: flex; flex-direction: column; gap: 1.25rem;" id="regional-stats-list">
                <!-- Dynamically generated rows -->
            </div>
            
            <div style="margin-top: 2rem; border-top: 1px solid var(--outline-variant); padding-top: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong style="color: var(--text-muted); font-size: 0.9rem;">Total Sales volume:</strong>
                    <span id="total-sales-volume" style="font-size: 1.25rem; font-weight: 800; color: var(--text);">0 units</span>
                </div>
            </div>
        </section>

    </div>

    <!-- Floating Glassmorphic Tooltip -->
    <div id="heatmap-tooltip" style="position: absolute; display: none; background: rgba(23,32,51,0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 0.75rem 1rem; color: #fff; pointer-events: none; z-index: 999; box-shadow: 0 10px 25px rgba(0,0,0,0.25); min-width: 180px; transition: opacity 0.15s ease;">
        <h4 id="tooltip-region-name" style="margin: 0 0 0.4rem 0; font-size: 0.9rem; font-weight: 700; color: #c084fc;">North Store</h4>
        <div style="display: flex; justify-content: space-between; gap: 1rem; font-size: 0.8rem; margin-bottom: 0.25rem;">
            <span style="color: #94a3b8;">Total Qty:</span>
            <strong id="tooltip-total-quantity" style="color: #fff;">0</strong>
        </div>
        <div style="display: flex; justify-content: space-between; gap: 1rem; font-size: 0.8rem; margin-bottom: 0.25rem;">
            <span style="color: #94a3b8;">Revenue:</span>
            <strong id="tooltip-total-revenue" style="color: #fff;">NPR 0.00</strong>
        </div>
        <div style="display: flex; justify-content: space-between; gap: 1rem; font-size: 0.8rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.35rem; margin-top: 0.35rem;">
            <span style="color: #94a3b8;">Share:</span>
            <strong id="tooltip-percentage-share" style="color: #4ade80;">0%</strong>
        </div>
    </div>

</div>

<style>
    /* CSS for Heatmap SVG Paths */
    .heatmap-region:hover {
        stroke: #fff !important;
        stroke-width: 3px !important;
        filter: drop-shadow(0 4px 12px rgba(168,85,247,0.3)) !important;
    }

    #interactive-heatmap-map text {
        text-shadow: 0 1px 2px rgba(255,255,255,0.8), 0 0 1px rgba(255,255,255,0.9);
        font-weight: 700 !important;
        fill: #111827 !important;
    }

    body.theme-dark #interactive-heatmap-map text {
        text-shadow: 0 1px 3px rgba(0,0,0,0.95), 0 0 2px rgba(0,0,0,1);
        fill: #ffffff !important;
    }

    body.theme-dark .heatmap-region {
        stroke: rgba(255, 255, 255, 0.15) !important;
    }

    /* Keyframes for AI Glow Pulse */
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.1); opacity: 0.8; }
    }

    /* Keyframes for loading spinner */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* AI panel support for light and dark theme */
    .heatmap-ai-panel {
        background: linear-gradient(135deg, rgba(230,240,255,0.9) 0%, rgba(210,225,255,0.9) 100%);
        border: 1px solid rgba(64,89,170,0.12);
        color: #1e293b;
    }
    .heatmap-ai-panel h3 {
        color: #1e3a8a !important;
    }
    .heatmap-ai-panel #ai-insight-container {
        color: #334155 !important;
    }
    .heatmap-ai-panel .ai-insight-icon {
        color: #3b82f6 !important;
    }
    body.theme-dark .heatmap-ai-panel {
        background: linear-gradient(135deg, rgba(23,32,51,0.95) 0%, rgba(15,23,42,0.95) 100%) !important;
        border: 1px solid rgba(255,255,255,0.08) !important;
        color: #f8fafc !important;
    }
    body.theme-dark .heatmap-ai-panel h3 {
        color: #e9d5ff !important;
    }
    body.theme-dark .heatmap-ai-panel #ai-insight-container {
        color: #cbd5e1 !important;
    }
    body.theme-dark .heatmap-ai-panel .ai-insight-icon {
        color: #c084fc !important;
    }

    /* Responsive Grid Layout */
    .heatmap-grid {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 2rem;
        margin-top: 1rem;
    }
    
    @media (max-width: 1024px) {
        .heatmap-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
    }

    @media (max-width: 768px) {
        .heatmap-page-container {
            padding: 1rem !important;
        }
        .topbar h1 {
            font-size: 1.75rem !important;
        }
        .panel {
            padding: 1.25rem !important;
        }
        #heatmap-svg-container {
            padding: 1rem !important;
            min-height: 280px !important;
        }
        #interactive-heatmap-map {
            max-height: 280px !important;
        }
        .heatmap-panel-header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 12px !important;
        }
    }

    @media (max-width: 520px) {
        .heatmap-page-container {
            padding: 0.5rem 0.25rem !important;
        }
        .panel {
            padding: 1.25rem 0.85rem !important;
            border-radius: 14px !important;
        }
        .heatmap-legend {
            flex-wrap: wrap !important;
            gap: 0.5rem !important;
            font-size: 0.72rem !important;
        }
        .heatmap-legend div {
            width: 70px !important;
        }
        #heatmap-filters-form {
            grid-template-columns: 1fr !important;
            gap: 0.85rem !important;
        }
        .heatmap-ai-panel > div {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 1rem !important;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filtersForm = document.getElementById('heatmap-filters-form');
    const productFilter = document.getElementById('product_filter');
    const fromDateFilter = document.getElementById('from_date_filter');
    const toDateFilter = document.getElementById('to_date_filter');
    const regionFilter = document.getElementById('region_filter');
    const resetButton = document.getElementById('reset-filters-btn');

    const mapLoading = document.getElementById('map-data-loading');
    const aiInsightLoading = document.getElementById('ai-insight-loading');
    const aiInsightContent = document.getElementById('ai-insight-content');

    const tooltip = document.getElementById('heatmap-tooltip');
    const tooltipName = document.getElementById('tooltip-region-name');
    const tooltipQty = document.getElementById('tooltip-total-quantity');
    const tooltipRev = document.getElementById('tooltip-total-revenue');
    const tooltipShare = document.getElementById('tooltip-percentage-share');

    let distributionData = [];

    // Reset filters
    resetButton.addEventListener('click', function() {
        filtersForm.reset();
        loadHeatmapData();
    });

    // Prevent default form submission and trigger AJAX reload
    filtersForm.addEventListener('submit', function (e) {
        e.preventDefault();
        loadHeatmapData();
    });

    // Handle form change
    productFilter.addEventListener('change', loadHeatmapData);
    fromDateFilter.addEventListener('change', loadHeatmapData);
    toDateFilter.addEventListener('change', loadHeatmapData);
    regionFilter.addEventListener('change', loadHeatmapData);

    // Initial load
    loadHeatmapData();

    function getHeatmapColor(value, min, max) {
        if (max === min) return 'rgba(59, 130, 246, 0.4)'; // Safe cool default blue-teal
        const ratio = (value - min) / (max - min);
        // Interpolate Hue: 210 (nice cool sky-blue) down to 0 (warm crimson-red)
        const hue = 210 - (ratio * 210);
        return `hsl(${hue}, 85%, 50%)`;
    }

    function loadHeatmapData() {
        mapLoading.style.display = 'flex';
        aiInsightLoading.style.display = 'flex';
        aiInsightContent.style.display = 'none';

        const params = new URLSearchParams({
            type: 'geographic-data',
            product_id: productFilter.value,
            from_date: fromDateFilter.value,
            to_date: toDateFilter.value,
            region: regionFilter.value
        });

        // 1. Fetch geographic data
        fetch('<?= e(appRootPath("api/reports.php")); ?>?' + params.toString())
            .then(res => {
                if (!res.ok) throw new Error('Failed to load data');
                return res.json();
            })
            .then(res => {
                mapLoading.style.display = 'none';
                if (res.success && res.data) {
                    distributionData = res.data;
                    renderHeatmap(distributionData);
                    loadAiInsight();
                } else {
                    renderEmptyState();
                }
            })
            .catch(err => {
                console.error(err);
                mapLoading.style.display = 'none';
                renderEmptyState();
                showErrorInsight();
            });
    }

    function renderHeatmap(data) {
        // Reset all region fills
        document.querySelectorAll('.heatmap-region').forEach(p => {
            p.style.fill = 'rgba(59, 130, 246, 0.08)';
            p.style.stroke = 'var(--outline-variant)';
        });

        if (data.length === 0) {
            document.getElementById('regional-stats-list').innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    <span class="material-symbols-outlined" style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;">sentiment_dissatisfied</span>
                    No sales data found matching selected filters.
                </div>
            `;
            document.getElementById('total-sales-volume').textContent = '0 units';
            return;
        }

        const quantities = data.map(d => d.total_quantity);
        const maxQty = Math.max(...quantities);
        const minQty = Math.min(...quantities);

        let totalQtySum = 0;
        let statsHtml = '';

        data.forEach(d => {
            totalQtySum += d.total_quantity;

            // Target SVG element path by ID
            const safeId = 'region-' + d.region.replace(/\s+/g, '-');
            const path = document.getElementById(safeId);
            
            const color = getHeatmapColor(d.total_quantity, minQty, maxQty);
            if (path) {
                path.style.fill = color;
                path.style.stroke = 'rgba(255,255,255,0.6)';
            }

            // Create sidebar card row
            const percentWidth = d.percentage_share;
            statsHtml += `
                <div style="background: var(--surface-low); border: 1px solid var(--outline-variant); padding: 1rem; border-radius: 12px; display: flex; flex-direction: column; gap: 0.5rem; transition: transform 0.2s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700; color: var(--text);">${d.region}</span>
                        <span style="font-size: 0.8rem; font-weight: 600; background: ${color}; color: #fff; padding: 0.2rem 0.5rem; border-radius: 8px;">${d.percentage_share}% share</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); flex-wrap: wrap; gap: 6px;">
                        <span>Units Sold: <strong>${d.total_quantity}</strong></span>
                        <span>Revenue: <strong>NPR ${parseFloat(d.total_revenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                    <div style="width: 100%; height: 6px; background: var(--outline-variant); border-radius: 99px; overflow: hidden; margin-top: 0.25rem;">
                        <div style="width: ${percentWidth}%; height: 100%; background: ${color}; border-radius: 99px;"></div>
                    </div>
                </div>
            `;
        });

        document.getElementById('regional-stats-list').innerHTML = statsHtml;
        document.getElementById('total-sales-volume').textContent = totalQtySum.toLocaleString() + ' units';

        // Add interactive hover states for tooltip
        document.querySelectorAll('.heatmap-region').forEach(p => {
            const regionName = p.getAttribute('data-region');
            const row = data.find(d => d.region === regionName);

            p.onmouseenter = function (e) {
                if (row) {
                    tooltipName.textContent = row.region;
                    tooltipQty.textContent = row.total_quantity.toLocaleString();
                    tooltipRev.textContent = 'NPR ' + parseFloat(row.total_revenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    tooltipShare.textContent = row.percentage_share + '%';
                    
                    const color = getHeatmapColor(row.total_quantity, minQty, maxQty);
                    tooltipShare.style.color = color;

                    tooltip.style.display = 'block';
                } else {
                    tooltipName.textContent = regionName;
                    tooltipQty.textContent = '0';
                    tooltipRev.textContent = 'NPR 0.00';
                    tooltipShare.textContent = '0%';
                    tooltipShare.style.color = 'var(--text-muted)';
                    tooltip.style.display = 'block';
                }
            };

            p.onmousemove = function (e) {
                tooltip.style.left = (e.pageX + 15) + 'px';
                tooltip.style.top = (e.pageY + 15) + 'px';
            };

            p.onmouseleave = function () {
                tooltip.style.display = 'none';
            };
        });
    }

    function renderEmptyState() {
        document.getElementById('regional-stats-list').innerHTML = `
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <span class="material-symbols-outlined" style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;">sentiment_dissatisfied</span>
                No regional data available.
            </div>
        `;
        document.getElementById('total-sales-volume').textContent = '0 units';
    }

    function loadAiInsight() {
        const params = new URLSearchParams({
            type: 'geographic-insight',
            product_id: productFilter.value,
            from_date: fromDateFilter.value,
            to_date: toDateFilter.value,
            region: regionFilter.value
        });

        // 2. Fetch AI Insight
        fetch('<?= e(appRootPath("api/reports.php")); ?>?' + params.toString())
            .then(res => {
                if (!res.ok) throw new Error('API failed');
                return res.json();
            })
            .then(res => {
                aiInsightLoading.style.display = 'none';
                if (res.success && res.insight) {
                    aiInsightContent.textContent = res.insight;
                    aiInsightContent.style.display = 'block';
                } else {
                    showErrorInsight();
                }
            })
            .catch(err => {
                console.error(err);
                showErrorInsight();
            });
    }

    function showErrorInsight() {
        aiInsightLoading.style.display = 'none';
        aiInsightContent.innerHTML = '<span style="color: var(--error); font-weight: 600;">Insight unavailable</span>';
        aiInsightContent.style.display = 'block';
    }
});
</script>

<?php require __DIR__ . '/../../core/layout/navbar.php'; ?>
<script src="<?= e(basePath('js/app.js')); ?>"></script>
<?php // Footer script block ends
echo '</main></div></body></html>'; 
?>
