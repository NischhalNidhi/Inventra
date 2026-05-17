<?php
/**
 * Mock AI Insight Endpoint
 * Returns a realistic business insight based on input data.
 */
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$salesData = $input['sales_data'] ?? [];

$prompt = $input['contents'][0]['parts'][0]['text'] ?? '';
$isAnalysisRequest = str_contains($prompt, 'REQUIRED JSON FORMAT');

// Extract data from prompt regex for mock realism
preg_match('/Total revenue: ([\d.]+)/', $prompt, $revMatch);
preg_match('/Previous month revenue: ([\d.]+)/', $prompt, $prevMatch);
preg_match('/Top selling products: ([^\\n]+)/', $prompt, $prodMatch);

$revenue = (float) ($revMatch[1] ?? 50000);
$prevRevenue = (float) ($prevMatch[1] ?? 45000);
$topProduct = explode(',', $prodMatch[1] ?? 'Key items')[0];

$insight = "Revenue is currently at NPR " . number_format($revenue, 2) . ". ";

if ($prevRevenue > 0) {
    $growth = (($revenue - $prevRevenue) / $prevRevenue) * 100;
    $trend = $growth >= 0 ? "increased by " . number_format($growth, 1) . "%" : "decreased by " . number_format(abs($growth), 1) . "%";
    $insight .= "Performance has $trend compared to last month. ";
}

$insight .= "Strong sales in $topProduct suggest high customer interest. Focus on maintaining stock levels for top performers while evaluating slow-moving inventory.";

if ($isAnalysisRequest) {
    // Return the structured JSON format expected by generateSalesAnalysis
    $analysis = [
        'summary' => $insight,
        'opportunities' => [
            'Increase stock for top performing products',
            'Launch a weekend promotion for slow categories',
            'Expand loyalty program to frequent customers'
        ],
        'risks' => [
            'Supply chain delays for imported goods',
            'Increased competition in the local region'
        ],
        'recommendation' => 'Prioritize restocking high-margin items before the month ends.'
    ];
    $responseText = json_encode($analysis);
} else {
    $responseText = $insight;
}

// Mimic Google Gemini response structure
echo json_encode([
    'candidates' => [
        [
            'content' => [
                'parts' => [
                    ['text' => $responseText]
                ]
            ]
        ]
    ]
]);
