<?php require __DIR__ . '/../../core/layout/header.php'; ?>

<header class="topbar">
    <div>
        <p class="eyebrow">ADVANCED ANALYTICS / AI</p>
        <h1>AI Sales Insights</h1>
        <p class="lead">Deep analysis of your department store's performance using generative intelligence.</p>
    </div>
</header>

<section class="panel ai-insight-panel">
    <div class="panel-header ai-insight-header">
        <div>
            <h2 class="ai-insight-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ai-insight-icon"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path><path d="M5 3v4"></path><path d="M19 17v4"></path><path d="M3 5h4"></path><path d="M17 19h4"></path></svg>
                Monthly Performance Executive Summary
            </h2>
            <p class="ai-insight-subtitle">AI-generated report based on current month sales vs. historical patterns.</p>
        </div>
        <span class="insight-pill">Gemini Pro 1.5</span>
    </div>
    
    <div class="ai-insight-copy">
        <div class="ai-insight-copy-inner">
            <p><?= nl2br(e($aiInsight)); ?></p>
        </div>
    </div>
</section>

<div class="insight-grid" style="margin-top: 2rem;">
    <section class="panel ai-metrics-panel">
        <div class="panel-header">
            <h3>Analyzed Metrics</h3>
        </div>
        <div class="metric-grid">
            <div class="metric-card">
                <p>Revenue This Month</p>
                <strong>NPR <?= number_format($insightData['summary']['total_revenue'] ?? 0, 2); ?></strong>
            </div>
            <div class="metric-card">
                <p>Orders</p>
                <strong><?= number_format($insightData['summary']['transaction_count'] ?? 0); ?></strong>
            </div>
            <div class="metric-card">
                <p>Top Category</p>
                <strong><?= e($insightData['category_breakdown'][0]['name'] ?? 'N/A'); ?></strong>
            </div>
            <div class="metric-card">
                <p>Growth vs Prev Month</p>
                <?php 
                    $curr = $insightData['summary']['total_revenue'] ?? 0;
                    $prev = $insightData['summary']['prev_month_revenue'] ?? 0;
                    $diff = $prev > 0 ? (($curr - $prev) / $prev) * 100 : 0;
                ?>
                <strong class="metric-growth <?= $diff >= 0 ? 'positive' : 'negative'; ?>">
                    <?= $diff >= 0 ? '+' : ''; ?><?= number_format($diff, 1); ?>%
                </strong>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h3>Top 3 Products</h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insightData['top_products'] ?? [] as $prod): ?>
                    <tr>
                        <td><?= e($prod['name']); ?></td>
                        <td>NPR <?= number_format($prod['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

 </div>
</main>
</div>
<script src="<?= e(assetPath('js/app.js')); ?>"></script>
</body>
</html>
