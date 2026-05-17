<?php

declare(strict_types=1);

class AiSalesInsightService
{
    /**
     * Returns the name of the AI model being used for display in the UI.
     */
    public function getConfiguredModel(): string
    {
        return env('AI_INSIGHTS_MODEL', 'llama-3.3-70b-versatile');
    }

    /**
     * Generates a concise summary for the dashboard card.
     * 
     * @param array $salesData
     * @return string
     */
    public function generateMonthlySalesInsight(array $salesData): string
    {
        $endpoint = env('AI_INSIGHTS_ENDPOINT', '');
        if ($endpoint === '') {
            $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
        }
        $apiKey = env('AI_INSIGHTS_API_KEY', '');
        $model = env('AI_INSIGHTS_MODEL', 'llama-3.3-70b-versatile');

        if ($apiKey === '') {
            throw new RuntimeException('AI insight service is not fully configured. Missing API Key.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for AI insights.');
        }

        $prompt = "You are a business analytics assistant.
        Your task is to analyze monthly sales data and generate a concise business insight.
        CURRENCY RULE: Always use NPR as the currency symbol. Never use $ or any other currency symbol.
        INPUT DATA:
        - Total revenue: NPR " . ($salesData['summary']['total_revenue'] ?? 'N/A') . "
        - Total orders: " . ($salesData['summary']['transaction_count'] ?? 'N/A') . "
        - Previous month revenue: NPR " . ($salesData['summary']['prev_month_revenue'] ?? 'N/A') . "
        - Top selling products: " . implode(', ', array_map(fn($p) => $p['name'], $salesData['top_products'] ?? [])) . "
        - Lowest performing products: " . implode(', ', array_map(fn($p) => $p['name'], $salesData['low_products'] ?? [])) . "
        - Revenue by category: " . implode(', ', array_map(fn($c) => $c['name'] . ": NPR " . $c['total'], $salesData['category_breakdown'] ?? [])) . "

        INSTRUCTIONS:
        - Generate a 2–3 sentence summary.
        - Use plain, clear business language.
        - Always refer to monetary values using NPR (e.g. NPR 10,000). Never use $.
        - Focus only on meaningful trends (growth, decline, anomalies, top/low performers).
        - Compare with previous month when data is available.
        - Highlight 1 key insight that a manager can act on.
        - Do NOT repeat raw numbers unnecessarily.
        - Do NOT explain calculations.
        - Do NOT speculate beyond the provided data.
        OUTPUT FORMAT:
        A short paragraph (2–3 sentences max).";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ];

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            // Allow self-signed or missing certs in non-production environments
            CURLOPT_SSL_VERIFYPEER => env('APP_ENV') === 'production',
            CURLOPT_SSL_VERIFYHOST => env('APP_ENV') === 'production' ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new RuntimeException("AI insight cURL request failed: $error (Endpoint: $endpoint)");
        }

        $decoded = json_decode($response, true);
        if ($statusCode >= 400 || !isset($decoded['choices'][0]['message']['content'])) {
            $preview = mb_substr((string)$response, 0, 100);
            throw new RuntimeException("AI insight service error (HTTP $statusCode). Response: $preview");
        }

        $summary = trim((string) $decoded['choices'][0]['message']['content']);
        if ($summary === '') {
            throw new RuntimeException('AI insight response did not include a summary.');
        }

        return $this->normalizeSummary($summary);
    }

    /**
     * Generates a structured analysis array for the full AI Insights page.
     * Satisfies the requirement in public/index.php:604.
     */
    public function generateSalesAnalysis(array $salesData): array
    {
        try {
            $endpoint = env('AI_INSIGHTS_ENDPOINT', '');
            if ($endpoint === '') {
                $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
            }
            $apiKey = env('AI_INSIGHTS_API_KEY', '');
            $model = env('AI_INSIGHTS_MODEL', 'llama-3.3-70b-versatile');

            if ($apiKey === '') {
                throw new RuntimeException('AI insight service is not fully configured.');
            }

            $prompt = "Analyze this sales data and return a JSON object.
            CURRENCY RULE: All monetary values must use NPR as the currency symbol. Never use $ or any other symbol.
            DATA: " . json_encode($salesData) . "
            
            REQUIRED JSON FORMAT:
            {
                \"summary\": \"2-3 sentence overview (use NPR for all amounts, never $)\",
                \"opportunities\": [\"3 specific growth ideas\"],
                \"risks\": [\"2 potential business threats\"],
                \"recommendation\": \"1 clear priority action\"
            }
            Return ONLY the raw JSON. Use NPR for all currency values, never $.";  

            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object']
            ];

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_SSL_VERIFYPEER => env('APP_ENV') === 'production',
                CURLOPT_SSL_VERIFYHOST => env('APP_ENV') === 'production' ? 2 : 0,
            ]);

            $response = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($statusCode !== 200) {
                throw new RuntimeException("AI analysis request failed with status $statusCode. Target URL: $endpoint");
            }

            $outer = json_decode((string)$response, true);
            $text = $outer['choices'][0]['message']['content'] ?? '';
            
            // Strip markdown JSON blocks if present
            if (str_starts_with($text, '```json')) {
                $text = trim(str_replace(['```json', '```'], '', $text));
            }
            
            $analysis = json_decode($text, true);

            if (!$analysis) {
                throw new RuntimeException('AI failed to return valid structured data.');
            }

            return [
                'summary' => $this->normalizeSummary($analysis['summary'] ?? ''),
                'opportunities' => $analysis['opportunities'] ?? [],
                'risks' => $analysis['risks'] ?? [],
                'recommendation' => $analysis['recommendation'] ?? '',
                'model' => $this->getConfiguredModel()
            ];
        } catch (Throwable $e) {
            throw $e;
        }
    }

    private function normalizeSummary(string $summary): string
    {
        $summary = preg_replace('/\s+/', ' ', trim($summary)) ?? trim($summary);
        if (mb_strlen($summary) > 1500) {
            $summary = rtrim(mb_substr($summary, 0, 1497)) . '...';
        }

        return $summary;
    }

    private function buildFallbackAnalysis(string $reason, array $salesData, string $model): array
    {
        $summary = $salesData['summary'] ?? [];
        $totalRevenue = round((float) ($summary['total_revenue'] ?? 0), 2);
        $prevRevenue = round((float) ($summary['prev_month_revenue'] ?? 0), 2);
        $transactions = (int) ($summary['transaction_count'] ?? 0);
        $change = percentageChange($totalRevenue, $prevRevenue);
        $topProduct = (string) (($salesData['top_products'][0]['name'] ?? '') ?: 'No leading product yet');
        $topCategory = (string) (($salesData['category_breakdown'][0]['name'] ?? '') ?: 'Unassigned');

        $summaryText = sprintf(
            '%s Revenue for the selected period is %s across %d transactions, with %s%% change versus the prior comparison period.',
            $reason,
            formatCurrencyAmount($totalRevenue),
            $transactions,
            number_format($change, 1)
        );

        return [
            'summary' => $summaryText,
            'opportunities' => [
                'Prioritize demand around ' . $topProduct . ' because it currently leads sales.',
                'Review assortment and promotions in ' . $topCategory . ' to protect category momentum.',
                'Use the charts in this report to validate whether revenue concentration is becoming too narrow.',
            ],
            'risks' => [
                'Insight is running in fallback mode, so qualitative recommendations are limited.',
                'If transaction volume stays low, trend comparisons will be noisy.',
                'Low-performing products may still need operational review even without AI ranking detail.',
            ],
            'recommendation' => 'Use the chart and KPI sections to confirm whether recent revenue growth is broad-based, then revisit Groq configuration for full narrative insight.',
            'model' => $model,
            'generated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            'status' => 'fallback',
            'period' => $salesData['period'] ?? null,
        ];
    }
}
