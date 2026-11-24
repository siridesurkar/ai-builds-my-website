<?php

namespace App\Services;

use App\Exceptions\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Gemini REST API.
 * Every failure mode (no key, timeout, 429, 5xx, safety block, invalid JSON) becomes
 * AiUnavailableException so callers have exactly one thing to handle.
 */
class GeminiClient
{
    /** Ask for a JSON object back. $parts = Gemini "parts" (text and/or inline_data images). */
    public function generateJson(string $system, array $parts, float $temperature = 0.2): array
    {
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => [
                'temperature' => $temperature,
                'responseMimeType' => 'application/json',
            ],
        ];

        for ($try = 0; $try < 2; $try++) {
            $text = $this->text($this->call($payload));
            $decoded = json_decode($this->stripFences($text), true);
            if (is_array($decoded)) {
                return $decoded;
            }
            Log::warning('Gemini returned invalid JSON', ['try' => $try]);
        }
        throw new AiUnavailableException('The AI returned an unreadable answer.');
    }

    /** Google-Search-grounded call. Returns ['text' => string, 'sources' => [['title','uri'], ...]]. */
    public function groundedSearch(string $prompt): array
    {
        $json = $this->call([
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'tools' => [['google_search' => (object) []]],
            'generationConfig' => ['temperature' => 0.1],
        ]);

        $sources = [];
        foreach (data_get($json, 'candidates.0.groundingMetadata.groundingChunks', []) as $chunk) {
            if (! empty($chunk['web']['uri'])) {
                $sources[] = ['title' => $chunk['web']['title'] ?? '', 'uri' => $chunk['web']['uri']];
            }
        }
        return ['text' => $this->text($json), 'sources' => $sources];
    }

    private function call(array $payload): array
    {
        $key = config('gemini.api_key');
        if (! $key) {
            throw new AiUnavailableException('GEMINI_API_KEY is not configured.');
        }

        $url = rtrim(config('gemini.base_url'), '/') . '/models/' . config('gemini.model') . ':generateContent';
        $attempts = max(1, config('gemini.retries'));
        $last = 'unknown error';

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $res = Http::timeout(config('gemini.timeout'))
                    ->withHeaders(['x-goog-api-key' => $key])
                    ->acceptJson()
                    ->post($url, $payload);

                if ($res->successful()) {
                    return $res->json();
                }
                $last = 'HTTP ' . $res->status();
                // Retry only for rate limits / provider errors. A 400/403 (bad key, bad request) will not fix itself.
                if (! in_array($res->status(), [408, 429, 500, 502, 503, 504], true)) {
                    throw new AiUnavailableException("The AI service rejected the request ({$last}).");
                }
            } catch (ConnectionException $e) {
                $last = 'connection problem';
            }

            if ($i < $attempts) {
                usleep((int) (500000 * (2 ** ($i - 1)))); // 0.5s, 1s, 2s...
            }
        }

        Log::warning('Gemini unavailable', ['reason' => $last]);
        throw new AiUnavailableException("The AI service is not responding right now ({$last}).");
    }

    private function text(array $json): string
    {
        $text = '';
        foreach (data_get($json, 'candidates.0.content.parts', []) as $part) {
            $text .= $part['text'] ?? '';
        }
        if (trim($text) === '') {
            $reason = data_get($json, 'candidates.0.finishReason') ?? data_get($json, 'promptFeedback.blockReason') ?? 'empty';
            throw new AiUnavailableException("The AI returned no answer ({$reason}).");
        }
        return $text;
    }

    private function stripFences(string $text): string
    {
        return trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
    }
}
