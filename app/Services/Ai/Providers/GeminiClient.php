<?php

namespace App\Services\Ai\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin Gemini generateContent HTTP adapter (server-side only).
 *
 * Official Generative Language REST contract for gemini-3.5-flash:
 * POST /v1beta/models/{model}:generateContent
 * Header: x-goog-api-key
 * generationConfig.responseMimeType + responseJsonSchema for structured JSON.
 */
class GeminiClient
{
    public function isConfigured(): bool
    {
        return filled($this->apiKey())
            && (string) config('ai.gemini.model') === 'gemini-3.5-flash';
    }

    public function model(): string
    {
        return (string) config('ai.gemini.model', 'gemini-3.5-flash');
    }

    public function apiVersion(): string
    {
        return (string) config('ai.gemini.api_version', 'v1beta');
    }

    /**
     * Endpoint path without secrets (for safe operational logging).
     */
    public function endpointPath(): string
    {
        return sprintf('/%s/models/%s:generateContent', $this->apiVersion(), $this->model());
    }

    private function apiKey(): string
    {
        return trim((string) config('ai.gemini.api_key'));
    }

    /**
     * @param  array<string, mixed>  $responseJsonSchema
     * @return array<string, mixed>
     */
    public function generateJson(
        string $systemInstruction,
        string $userText,
        array $responseJsonSchema,
        float $temperature = 0.2,
        string $logChannel = 'ai.gemini',
    ): array {
        if (! $this->isConfigured()) {
            throw new GeminiProviderException('Gemini is not configured.', 'not_configured');
        }

        $model = $this->model();
        $version = $this->apiVersion();
        $base = rtrim((string) config('ai.gemini.base_url'), '/');
        // Guard against accidental full endpoint pasted into GEMINI_BASE_URL.
        $base = preg_replace('#/v1(?:beta)?(?:/models.*)?$#', '', $base) ?: $base;
        $url = sprintf('%s/%s/models/%s:generateContent', $base, $version, $model);
        $endpointPath = $this->endpointPath();
        $started = microtime(true);
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userText],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $responseJsonSchema,
            ],
        ];

        return $this->sendWithRetries(
            $url,
            $payload,
            (int) config('ai.timeout_seconds', 8),
            $logChannel,
            $endpointPath,
            $model,
            $started,
        );
    }

    /**
     * Multimodal generateContent with an inline image + JSON schema response.
     *
     * @param  array<string, mixed>  $responseJsonSchema
     * @return array<string, mixed>
     */
    public function generateJsonWithImage(
        string $systemInstruction,
        string $userText,
        string $imageBinary,
        string $mimeType,
        array $responseJsonSchema,
        float $temperature = 0.0,
        string $logChannel = 'ai.gemini',
    ): array {
        if (! $this->isConfigured()) {
            throw new GeminiProviderException('Gemini is not configured.', 'not_configured');
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mimeType, $allowedMimes, true)) {
            throw new GeminiProviderException('Unsupported image MIME type for Gemini vision.', 'bad_request');
        }

        $model = $this->model();
        $version = $this->apiVersion();
        $base = rtrim((string) config('ai.gemini.base_url'), '/');
        $base = preg_replace('#/v1(?:beta)?(?:/models.*)?$#', '', $base) ?: $base;
        $url = sprintf('%s/%s/models/%s:generateContent', $base, $version, $model);
        $endpointPath = $this->endpointPath();
        $started = microtime(true);
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userText],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => base64_encode($imageBinary),
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $responseJsonSchema,
            ],
        ];

        $timeout = max(8, (int) config('ai.timeout_seconds', 8) * 2);

        return $this->sendWithRetries(
            $url,
            $payload,
            $timeout,
            $logChannel,
            $endpointPath,
            $model,
            $started,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendWithRetries(
        string $url,
        array $payload,
        int $timeout,
        string $logChannel,
        string $endpointPath,
        string $model,
        float $started,
    ): array {
        $attempts = 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout($timeout)
                    ->connectTimeout(5)
                    ->withHeaders([
                        'x-goog-api-key' => $this->apiKey(),
                    ])
                    ->post($url, $payload);

                return $this->decodeJsonResponse(
                    $response,
                    $logChannel,
                    $endpointPath,
                    $model,
                    $started,
                    $attempt,
                );
            } catch (ConnectionException $e) {
                $lastException = new GeminiProviderException(
                    'Gemini provider timed out.',
                    'timeout',
                    null,
                    0,
                    $e,
                );
                Log::warning($logChannel.'.timeout', [
                    'provider' => 'gemini',
                    'category' => 'timeout',
                    'model' => $model,
                    'endpoint' => $endpointPath,
                    'attempt' => $attempt,
                    'timing_ms' => (int) round((microtime(true) - $started) * 1000),
                    'message' => class_basename($e),
                ]);

                if ($attempt >= $attempts) {
                    throw $lastException;
                }

                $this->sleepBeforeRetry($attempt, null);
            } catch (GeminiProviderException $e) {
                $lastException = $e;

                if (! $e->isRetryable() || $attempt >= $attempts) {
                    throw $e;
                }

                $this->sleepBeforeRetry($attempt, $e->retryAfterSeconds);
            } catch (Throwable $e) {
                Log::warning($logChannel.'.failure', [
                    'provider' => 'gemini',
                    'category' => 'transport_failure',
                    'model' => $model,
                    'endpoint' => $endpointPath,
                    'attempt' => $attempt,
                    'timing_ms' => (int) round((microtime(true) - $started) * 1000),
                    'message' => class_basename($e),
                ]);

                throw new GeminiProviderException('Gemini provider failed.', 'transport_failure', null, 0, $e);
            }
        }

        throw $lastException ?? new GeminiProviderException('Gemini provider failed.', 'transport_failure');
    }

    private function sleepBeforeRetry(int $attempt, ?int $retryAfterSeconds): void
    {
        if (app()->runningUnitTests()) {
            // Keep retry semantics without burning wall-clock time in Pest.
            return;
        }

        if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            // Cap Retry-After so a single request cannot block the worker for minutes.
            usleep(min($retryAfterSeconds, 5) * 1_000_000);

            return;
        }

        usleep(750_000 * $attempt);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(
        Response $response,
        string $logChannel,
        string $endpointPath,
        string $model,
        float $started,
        int $attempt = 1,
    ): array {
        $status = $response->status();
        $timingMs = (int) round((microtime(true) - $started) * 1000);
        $providerStatus = (string) data_get($response->json(), 'error.status', '');
        $providerMessage = $this->sanitizeProviderMessage((string) data_get($response->json(), 'error.message', ''));
        $category = $this->failureCategory($status, $providerMessage);
        $retryAfter = $this->parseRetryAfter($response);

        if ($status === 429) {
            Log::warning($logChannel.'.rate_limited', [
                'status' => $status,
                'category' => $category,
                'provider_status' => $providerStatus !== '' ? $providerStatus : null,
                'model' => $model,
                'endpoint' => $endpointPath,
                'attempt' => $attempt,
                'timing_ms' => $timingMs,
                'retry_after' => $retryAfter,
            ]);

            throw new GeminiProviderException('Gemini rate limited.', 'rate_limited', $retryAfter);
        }

        if ($response->serverError()) {
            Log::warning($logChannel.'.server_error', [
                'status' => $status,
                'category' => $category,
                'provider_status' => $providerStatus !== '' ? $providerStatus : null,
                'provider_message' => $providerMessage !== '' ? mb_substr($providerMessage, 0, 180) : null,
                'model' => $model,
                'endpoint' => $endpointPath,
                'attempt' => $attempt,
                'timing_ms' => $timingMs,
            ]);

            if ($category === 'high_demand') {
                throw new GeminiProviderException('Gemini high demand.', 'high_demand', $retryAfter);
            }

            throw new GeminiProviderException('Gemini server error.', 'server_error', $retryAfter);
        }

        if (! $response->successful()) {
            Log::warning($logChannel.'.http_error', [
                'status' => $status,
                'category' => $category,
                'provider_status' => $providerStatus !== '' ? $providerStatus : null,
                'provider_message' => $providerMessage !== '' ? mb_substr($providerMessage, 0, 180) : null,
                'model' => $model,
                'endpoint' => $endpointPath,
                'attempt' => $attempt,
                'timing_ms' => $timingMs,
            ]);

            throw new GeminiProviderException('Gemini returned an error status.', $category);
        }

        $content = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($content) || $content === '') {
            Log::warning($logChannel.'.empty_content', [
                'status' => $status,
                'category' => 'empty_content',
                'model' => $model,
                'endpoint' => $endpointPath,
                'attempt' => $attempt,
                'timing_ms' => $timingMs,
            ]);

            throw new GeminiProviderException('Gemini returned empty content.', 'empty_content');
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning($logChannel.'.parse_error', [
                'status' => $status,
                'category' => 'parse_error',
                'model' => $model,
                'endpoint' => $endpointPath,
                'attempt' => $attempt,
                'timing_ms' => $timingMs,
                'message' => class_basename($e),
            ]);

            throw new GeminiProviderException('Gemini returned unexpected JSON shape.', 'parse_error', null, 0, $e);
        }

        if (! is_array($decoded)) {
            throw new GeminiProviderException('Gemini returned unexpected JSON shape.', 'parse_error');
        }

        Log::info($logChannel.'.success', [
            'status' => $status,
            'category' => 'ok',
            'model' => $model,
            'endpoint' => $endpointPath,
            'attempt' => $attempt,
            'timing_ms' => $timingMs,
        ]);

        return $decoded;
    }

    private function parseRetryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');
        if ($header === null || $header === '') {
            return null;
        }

        if (is_numeric($header)) {
            return max(0, (int) $header);
        }

        $timestamp = strtotime($header);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }

    private function failureCategory(int $status, string $providerMessage = ''): string
    {
        if ($status === 503 && str_contains(strtolower($providerMessage), 'high demand')) {
            return 'high_demand';
        }

        return match (true) {
            $status === 400 => 'bad_request',
            $status === 401 => 'unauthorized',
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 429 => 'rate_limited',
            $status >= 500 && $status < 600 => 'server_error',
            default => 'http_error',
        };
    }

    private function sanitizeProviderMessage(string $message): string
    {
        $message = preg_replace('/AIza[0-9A-Za-z_-]{10,}/', '[REDACTED_KEY]', $message) ?? $message;
        $message = preg_replace('/AQ\.[0-9A-Za-z_-]{10,}/', '[REDACTED_KEY]', $message) ?? $message;

        return trim($message);
    }
}
