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

        $payload = [
            'report_type' => 'monthly_sales',
            'instructions' => 'Write a concise 2-3 sentence sales insight summary in natural language.',
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
