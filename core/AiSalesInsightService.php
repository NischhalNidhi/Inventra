<?php

declare(strict_types=1);

/**
 * AiSalesInsightService
 *
 * Sends monthly sales data to the Google Gemini API and returns
 * a short, human-readable business insight paragraph.
 *
 * Required .env variables:
 *   AI_INSIGHTS_API_KEY  — your Google AI Studio API key
 *
 * Optional .env variables:
 *   AI_INSIGHTS_ENDPOINT — override the Gemini model URL
 *                          (defaults to gemini-2.0-flash)
 */
class AiSalesInsightService
{
    // Default Gemini model endpoint (no API key here — key is added as ?key= below)
    private const DEFAULT_ENDPOINT =
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    /**
     * Ask Gemini to analyze monthly sales data and return a 2–3 sentence insight.
     *
     * @param  array  $salesData  Data from Report::getCurrentMonthSalesInsightData()
     * @return string             The AI-generated insight paragraph
     * @throws RuntimeException   If the API call fails or returns no content
     */
    public function generateMonthlySalesInsight(array $salesData): string
    {
        $apiKey = env('AI_INSIGHTS_API_KEY', '');
        if ($apiKey === '') {
            throw new RuntimeException('AI_INSIGHTS_API_KEY is not set in .env.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL PHP extension is required for AI insights.');
        }

        // Build the Gemini endpoint URL (API key goes as a query parameter)
        $baseEndpoint = rtrim(env('AI_INSIGHTS_ENDPOINT', self::DEFAULT_ENDPOINT), '/');
        // Remove any existing ?key= param the user might have accidentally included
        $baseEndpoint = preg_replace('/[?&]key=[^&]*/', '', $baseEndpoint) ?? $baseEndpoint;
        $url = $baseEndpoint . '?key=' . urlencode($apiKey);

        // Build the prompt using the sales data
        $prompt = $this->buildPrompt($salesData);

        // Gemini API request format
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.4,   // slightly creative but mostly factual
                'maxOutputTokens' => 200,   // ~2–3 sentences
            ],
        ];

        $isProduction = env('APP_ENV') === 'production';

        // Make the HTTP request
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            // On XAMPP Windows, the cURL CA certificate bundle is often not
            // configured, causing SSL verification to fail for googleapis.com.
            // It is safe to disable this on a local development machine.
            CURLOPT_SSL_VERIFYPEER => $isProduction,
            CURLOPT_SSL_VERIFYHOST => $isProduction ? 2 : 0,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            throw new RuntimeException('Gemini API request failed: ' . $curlError);
        }

        $decoded = json_decode((string) $response, true);

        // Handle API-level errors (e.g. wrong key, quota exceeded)
        if ($httpCode >= 400) {
            $apiMessage = $decoded['error']['message'] ?? 'Unknown error';
            throw new RuntimeException("Gemini API error ({$httpCode}): {$apiMessage}");
        }

        // Extract the text from the Gemini response structure:
        // candidates[0].content.parts[0].text
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        return $this->cleanText($text);
    }

    /**
     * Build the prompt string from structured sales data.
     */
    private function buildPrompt(array $salesData): string
    {
        $summary  = $salesData['summary']  ?? [];
        $topProds = array_map(fn($p) => $p['name'], $salesData['top_products']  ?? []);
        $lowProds = array_map(fn($p) => $p['name'], $salesData['low_products']  ?? []);
        $cats     = array_map(fn($c) => $c['name'] . ': ' . $c['total'], $salesData['category_breakdown'] ?? []);

        return
            "You are a business analytics assistant for an inventory management system.\n"
            . "Analyze the following monthly sales data and write a 2–3 sentence business insight.\n\n"
            . "DATA:\n"
            . "- Total revenue this month : " . ($summary['total_revenue']       ?? 'N/A') . "\n"
            . "- Total orders this month  : " . ($summary['transaction_count']   ?? 'N/A') . "\n"
            . "- Previous month revenue   : " . ($summary['prev_month_revenue']  ?? 'N/A') . "\n"
            . "- Top selling products     : " . (implode(', ', $topProds) ?: 'N/A') . "\n"
            . "- Low performing products  : " . (implode(', ', $lowProds) ?: 'N/A') . "\n"
            . "- Revenue by category      : " . (implode(', ', $cats)     ?: 'N/A') . "\n\n"
            . "RULES:\n"
            . "- Write exactly 2–3 sentences in plain business English.\n"
            . "- Highlight the most important trend (growth, decline, top/low performers).\n"
            . "- End with one actionable recommendation for the manager.\n"
            . "- Do NOT repeat raw numbers unless they add clear value.\n"
            . "- Do NOT add bullet points, headers, or markdown formatting.\n";
    }

    /**
     * Normalize whitespace and trim the response to a safe length.
     */
    private function cleanText(string $text): string
    {
        // Collapse multiple spaces/newlines into a single space
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        // Cap at ~420 characters (enough for 3 sentences)
        if (mb_strlen($text) > 420) {
            $text = rtrim(mb_substr($text, 0, 417)) . '...';
        }

        return $text;
    }
}
