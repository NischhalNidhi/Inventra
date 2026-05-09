<?php
/**
 * Mock AI Insight Endpoint
 * Returns a realistic business insight based on input data.
 */
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
