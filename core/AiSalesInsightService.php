<?php

declare(strict_types=1);

class AiSalesInsightService
{
    public function generateMonthlySalesInsight(array $salesData): string
    {
        $endpoint = env('AI_INSIGHTS_ENDPOINT', '');
        if ($endpoint === '') {
            throw new RuntimeException('AI insight service is not configured.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for AI insights.');
        }

        $prompt = "You are a business analytics assistant.
Your task is to analyze monthly sales data and generate a concise business insight.
INPUT DATA:
- Total revenue: " . ($salesData['summary']['total_revenue'] ?? 'N/A') . "
- Total orders: " . ($salesData['summary']['transaction_count'] ?? 'N/A') . "
- Previous month revenue: " . ($salesData['summary']['prev_month_revenue'] ?? 'N/A') . "
- Top selling products: " . implode(', ', array_map(fn($p) => $p['name'], $salesData['top_products'] ?? [])) . "
- Lowest performing products: " . implode(', ', array_map(fn($p) => $p['name'], $salesData['low_products'] ?? [])) . "
- Revenue by category: " . implode(', ', array_map(fn($c) => $c['name'] . ": " . $c['total'], $salesData['category_breakdown'] ?? [])) . "

INSTRUCTIONS:
- Generate a 2–3 sentence summary.
- Use plain, clear business language.
- Focus only on meaningful trends (growth, decline, anomalies, top/low performers).
- Compare with previous month when data is available.
- Highlight 1 key insight that a manager can act on.
- Do NOT repeat raw numbers unnecessarily.
- Do NOT explain calculations.
- Do NOT speculate beyond the provided data.
OUTPUT FORMAT:
A short paragraph (2–3 sentences max).";

        $payload = [
            'report_type' => 'monthly_sales_v2',
            'instructions' => $prompt,
            'sales_data' => $salesData,
        ];

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $apiKey = env('AI_INSIGHTS_API_KEY', '');
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new RuntimeException('AI insight request failed.');
        }

        $decoded = json_decode($response, true);
        if ($statusCode >= 400 || !is_array($decoded)) {
            throw new RuntimeException('AI insight service returned an invalid response.');
        }

        $summary = trim((string) ($decoded['summary'] ?? ''));
        if ($summary === '') {
            throw new RuntimeException('AI insight response did not include a summary.');
        }

        return $this->normalizeSummary($summary);
    }

    private function normalizeSummary(string $summary): string
    {
        $summary = preg_replace('/\s+/', ' ', trim($summary)) ?? trim($summary);
        if (mb_strlen($summary) > 420) {
            $summary = rtrim(mb_substr($summary, 0, 417)) . '...';
        }

        return $summary;
    }
}
