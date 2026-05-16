<?php

declare(strict_types=1);

class AiSalesInsightService
{
    private const DEFAULT_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const DEFAULT_MODEL = 'llama-3.3-70b-versatile';

    public function generateMonthlySalesInsight(array $salesData): string
    {
        $analysis = $this->generateSalesAnalysis($salesData);

        return $analysis['summary'];
    }

    public function generateVisualInsight(string $chartType, array $chartData): array
    {
        $model = env('AI_INSIGHTS_MODEL', self::DEFAULT_MODEL) ?? self::DEFAULT_MODEL;
        $apiKey = env('AI_INSIGHTS_API_KEY', '');
        
        if ($apiKey === '') {
            return ['insight' => 'AI insight unavailable.'];
        }

        $prompt = "Analyze this '$chartType' data and give 1-2 concise sentences of insight. All currency values are in NPR (Nepalese Rupees). Always use NPR prefix, never use $ or USD: " . json_encode($chartData);
        $payload = [
            'model' => $model,
            'temperature' => 0.1,
            'max_tokens' => 150,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a retail analytics assistant. Return only the insight text. All monetary values must use NPR (Nepalese Rupees). Never use $ or USD.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $ch = curl_init(env('AI_INSIGHTS_ENDPOINT', self::DEFAULT_ENDPOINT) ?? self::DEFAULT_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $decoded = json_decode((string)$response, true);
        curl_close($ch);
        return ['insight' => trim((string)($decoded['choices'][0]['message']['content'] ?? 'No visual insight available.'))];
    }

    public function generateSalesAnalysis(array $salesData): array
    {
        $model = env('AI_INSIGHTS_MODEL', self::DEFAULT_MODEL) ?? self::DEFAULT_MODEL;

        $apiKey = env('AI_INSIGHTS_API_KEY', '');
        if ($apiKey === '') {
            return $this->buildFallbackAnalysis(
                'AI insight is unavailable because the Groq API key is not configured.',
                $salesData,
                $model
            );
        }

        if (!function_exists('curl_init')) {
            return $this->buildFallbackAnalysis(
                'AI insight is unavailable because the cURL PHP extension is missing.',
                $salesData,
                $model
            );
        }

        $endpoint = rtrim(env('AI_INSIGHTS_ENDPOINT', self::DEFAULT_ENDPOINT) ?? self::DEFAULT_ENDPOINT, '/');
        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'max_tokens' => 500,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->buildSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $this->buildUserPrompt($salesData),
                ],
            ],
        ];

        $isProduction = env('APP_ENV') === 'production';
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => $isProduction,
            CURLOPT_SSL_VERIFYHOST => $isProduction ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            return $this->buildFallbackAnalysis(
                'AI insight is temporarily unavailable because the Groq request failed.',
                $salesData,
                $model
            );
        }

        $decoded = json_decode((string) $response, true);
        if ($httpCode >= 400) {
            return $this->buildFallbackAnalysis(
                'AI insight is temporarily unavailable because Groq rejected the request.',
                $salesData,
                $model
            );
        }

        $content = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            return $this->buildFallbackAnalysis(
                'AI insight is temporarily unavailable because Groq returned an empty response.',
                $salesData,
                $model
            );
        }

        try {
            $analysis = $this->decodeAnalysisPayload($content);
        } catch (Throwable $exception) {
            return $this->buildFallbackAnalysis(
                'AI insight is temporarily unavailable because the response could not be validated.',
                $salesData,
                $model
            );
        }

        $analysis['model'] = (string) ($decoded['model'] ?? $model);
        $analysis['generated_at'] = (new DateTimeImmutable('now'))->format(DATE_ATOM);
        $analysis['status'] = 'ok';
        $analysis['period'] = $salesData['period'] ?? null;

        return $analysis;
    }

    public function getConfiguredModel(): string
    {
        return env('AI_INSIGHTS_MODEL', self::DEFAULT_MODEL) ?? self::DEFAULT_MODEL;
    }

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'You are an inventory and retail analytics assistant for Inventra.',
            'Analyze the provided monthly sales dataset and return strict JSON.',
            'Do not include markdown fences, commentary, or extra keys.',
            'All monetary values must use NPR (Nepalese Rupees). Never use $ or USD.',
            'The response JSON must contain these keys exactly:',
            'summary: string',
            'opportunities: array of 3 short strings',
            'risks: array of 3 short strings',
            'recommendation: string',
        ]);
    }

    private function buildUserPrompt(array $salesData): string
    {
        $summary = $salesData['summary'] ?? [];
        $topProducts = $salesData['top_products'] ?? [];
        $lowProducts = $salesData['low_products'] ?? [];
        $categories = $salesData['category_breakdown'] ?? [];

        return json_encode([
            'task' => 'Create a concise executive sales analysis for a supermarket inventory system.',
            'rules' => [
                'Keep summary to 2 or 3 sentences.',
                'Use plain business English.',
                'Make opportunities and risks concrete and actionable.',
                'Base the analysis only on the supplied data.',
            ],
            'data' => [
                'summary' => [
                    'total_revenue' => round((float) ($summary['total_revenue'] ?? 0), 2),
                    'transaction_count' => (int) ($summary['transaction_count'] ?? 0),
                    'prev_month_revenue' => round((float) ($summary['prev_month_revenue'] ?? 0), 2),
                ],
                'top_products' => array_map(static function (array $row): array {
                    return [
                        'name' => (string) ($row['name'] ?? ''),
                        'total' => round((float) ($row['total'] ?? 0), 2),
                    ];
                }, $topProducts),
                'low_products' => array_map(static function (array $row): array {
                    return [
                        'name' => (string) ($row['name'] ?? ''),
                        'total' => round((float) ($row['total'] ?? 0), 2),
                    ];
                }, $lowProducts),
                'category_breakdown' => array_map(static function (array $row): array {
                    return [
                        'name' => (string) ($row['name'] ?? ''),
                        'total' => round((float) ($row['total'] ?? 0), 2),
                    ];
                }, $categories),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    private function decodeAnalysisPayload(string $content): array
    {
        $normalized = trim($content);
        $normalized = preg_replace('/^```json\s*|\s*```$/i', '', $normalized) ?? $normalized;
        $decoded = json_decode($normalized, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('AI response was not valid JSON.');
        }

        $summary = $this->cleanText((string) ($decoded['summary'] ?? ''));
        $recommendation = $this->cleanText((string) ($decoded['recommendation'] ?? ''));
        $opportunities = $this->cleanList($decoded['opportunities'] ?? []);
        $risks = $this->cleanList($decoded['risks'] ?? []);

        if ($summary === '' || $recommendation === '') {
            throw new RuntimeException('AI response was missing required fields.');
        }

        return [
            'summary' => $summary,
            'opportunities' => array_slice($opportunities, 0, 3),
            'risks' => array_slice($risks, 0, 3),
            'recommendation' => $recommendation,
        ];
    }

    private function cleanList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $text = $this->cleanText((string) $item);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return $items;
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        return $text;
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
