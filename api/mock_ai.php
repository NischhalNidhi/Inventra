<?php
require_once __DIR__ . '/../core/helpers.php';

$apiKey = env('AI_INSIGHTS_API_KEY', '');
if ($apiKey !== '') {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($authHeader, 'Bearer ' . $apiKey)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized Access']);
        exit;
    }
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$salesData = $input['sales_data'] ?? [];

$revenue = $salesData['summary']['total_revenue'] ?? 0;
$prevRevenue = $salesData['summary']['prev_month_revenue'] ?? 0;
$topProduct = $salesData['top_products'][0]['name'] ?? 'key items';

$insight = "Revenue is currently at NPR " . number_format((float)$revenue, 2) . ". ";

if ($prevRevenue > 0) {
    $growth = (($revenue - $prevRevenue) / $prevRevenue) * 100;
    $trend = $growth >= 0 ? "increased by " . number_format($growth, 1) . "%" : "decreased by " . number_format(abs($growth), 1) . "%";
    $insight .= "Performance has $trend compared to last month. ";
}

$insight .= "Strong sales in $topProduct suggest high customer interest. Focus on maintaining stock levels for top performers while evaluating slow-moving inventory.";

echo json_encode(['summary' => $insight]);
