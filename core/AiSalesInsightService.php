<?php

declare(strict_types=1);

class AiSalesInsightService
{
    private const DEFAULT_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const DEFAULT_MODEL = 'llama-3.3-70b-versatile';

    public function generateMonthlySalesInsight(array $salesData): array
    {
        return $this->generateSalesAnalysis($salesData);
    }

    public function generateSalesAnalysis(array $salesData): array
    {
        $apiKey = env('AI_INSIGHTS_API_KEY', '');
        if ($apiKey === '') {
            throw new RuntimeException('AI_INSIGHTS_API_KEY is not set in .env.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL PHP extension is required for AI insights.');
        }

        $model = env('AI_INSIGHTS_MODEL', self::DEFAULT_MODEL) ?? self::DEFAULT_MODEL;
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
            throw new RuntimeException('Groq API request failed: ' . $curlError);
        }

        $decoded = json_decode((string) $response, true);
        if ($httpCode >= 400) {
            $apiMessage = $decoded['error']['message'] ?? 'Unknown error';
            throw new RuntimeException("Groq API error ({$httpCode}): {$apiMessage}");
        }

        $content = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            throw new RuntimeException('Groq returned an empty response.');
        }

        $analysis = $this->decodeAnalysisPayload($content);
        $analysis['model'] = (string) ($decoded['model'] ?? $model);
        $analysis['generated_at'] = (new DateTimeImmutable('now'))->format(DATE_ATOM);

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

    /**
     * Generates a geographic/regional business insight.
     *
     * @param array $distributionData
     * @return string
     */
    public function generateGeographicInsight(array $distributionData): string
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

        $dataStr = '';
        foreach ($distributionData as $row) {
            $region = isset($row['region']) ? (string)$row['region'] : 'Unknown';
            $qty = isset($row['total_quantity']) ? (int)$row['total_quantity'] : 0;
            $rev = isset($row['total_revenue']) ? number_format((float)$row['total_revenue'], 2) : '0.00';
            $share = isset($row['percentage_share']) ? (float)$row['percentage_share'] : 0;
            $dataStr .= "- Region: {$region}, Quantity: {$qty}, Revenue: NPR {$rev} ({$share}% share)\n";
        }

        $prompt = "You are a business analytics assistant.\nYour task is to analyze geographic distribution sales data and generate a concise business insight.\nCURRENCY RULE: Always use NPR as the currency symbol. Never use $ or any other currency symbol.\nINPUT DATA:\n{$dataStr}\nINSTRUCTIONS:\n- Generate a 2–3 sentence summary highlighting the top and underperforming regions.\n- Use plain, clear business language.\n- Always refer to monetary values using NPR (e.g. NPR 10,000). Never use $.\n- Focus only on meaningful trends (growth, decline, anomalies, top/low performers).\n- Highlight 1 key insight that a manager can act on.\n- Do NOT repeat raw numbers unnecessarily.\n- Do NOT explain calculations.\n- Do NOT speculate beyond the provided data.\nOUTPUT FORMAT:\nA short paragraph (2–3 sentences max).";

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
            CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 0,
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

        $decoded = json_decode((string)$response, true);
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
}
