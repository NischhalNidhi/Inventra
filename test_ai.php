<?php
/**
 * Diagnostic script to test AI API key connectivity.
 */
declare(strict_types=1);

require_once __DIR__ . '/core/dependencies.php';

header('Content-Type: text/plain; charset=utf-8');

echo "--- Inventra AI Connectivity Test ---\n\n";

$endpoint = env('AI_INSIGHTS_ENDPOINT');
$apiKey = env('AI_INSIGHTS_API_KEY');
$env = env('APP_ENV', 'local');

echo "Configuration Check:\n";
echo "Endpoint: " . ($endpoint ?: "MISSING") . "\n";
echo "API Key:  " . ($apiKey ? "PRESENT (" . strlen($apiKey) . " chars)" : "MISSING") . "\n";
echo "App Env:  " . $env . "\n\n";

if (!$endpoint || !$apiKey) {
    echo "❌ Error: AI configuration is incomplete. Check your .env file.\n";
    exit;
}

$service = new AiSalesInsightService();

echo "Testing Dashboard Insight (Plain Text)...\n";
try {
    // Mock data for test
    $testData = [
        'summary' => ['total_revenue' => 1000, 'transaction_count' => 5, 'prev_month_revenue' => 800],
        'top_products' => [['name' => 'Test Product']],
        'low_products' => [],
        'category_breakdown' => [['name' => 'Test Category', 'total' => 1000]]
    ];
    
    $insight = $service->generateMonthlySalesInsight($testData);
    echo "✅ Success! Dashboard Insight:\n\"$insight\"\n\n";
} catch (Throwable $e) {
    echo "❌ Dashboard Insight failed: " . $e->getMessage() . "\n\n";
}

echo "Testing Full Analysis (JSON Mode)...\n";
try {
    $analysis = $service->generateSalesAnalysis($testData);
    echo "✅ Success! Analysis Results:\n";
    echo "Summary: " . $analysis['summary'] . "\n";
    echo "Opportunities: " . implode(', ', $analysis['opportunities']) . "\n";
    echo "Risks: " . implode(', ', $analysis['risks']) . "\n";
    echo "Recommendation: " . $analysis['recommendation'] . "\n\n";
} catch (Throwable $e) {
    echo "❌ Full Analysis failed: " . $e->getMessage() . "\n";
    
    if (str_contains($e->getMessage(), '400') || str_contains($e->getMessage(), '401')) {
        echo "💡 Tip: This often means the API key is invalid or JSON Mode is not supported by the model/endpoint.\n";
    } elseif (str_contains($e->getMessage(), 'cURL error 6') || str_contains($e->getMessage(), 'cURL error 7')) {
        echo "💡 Tip: Connectivity issue. Check if you can reach the endpoint URL in your browser.\n";
    } elseif (str_contains($e->getMessage(), 'SSL')) {
        echo "💡 Tip: SSL issue. Ensure APP_ENV=local in .env if testing on XAMPP.\n";
    }
}

echo "\n--- End of Test ---";