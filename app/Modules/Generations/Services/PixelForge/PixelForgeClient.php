<?php

namespace Modules\Generations\Services\PixelForge;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Modules\Generations\Contracts\ImageGenerationProvider;

use Illuminate\Support\Facades\Http;
use Modules\Generations\Contracts\GenerationWebhookVerifier;
use Modules\Generations\Models\Generation;
use UnexpectedValueException;

class PixelForgeClient implements ImageGenerationProvider, GenerationWebhookVerifier
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $webhookSecret,
        private readonly PixelForgePayloadValidator $payloadValidator,
    ) {}

    /** @return array<string, mixed> */
    public function pricing(): array
    {
        $cacheKey = 'pixelforge:pricing:' . hash('sha256', $this->baseUrl);

        return Cache::remember($cacheKey, now()->addMinutes(5), function (): array {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get(rtrim($this->baseUrl, '/') . '/pricing')
                ->throw();

            $pricing = $response->json();

            if (! is_array($pricing)) {
                throw new UnexpectedValueException(
                    'Invalid Pixelforge pricing JSON.',
                );
            }

            $this->payloadValidator->validatePricing($pricing);

            return $pricing;
        });
    }

    public function retrieve(string $providerGenerationId): Response
    {
        return Http::acceptJson()
            ->withToken($this->apiKey)
            ->connectTimeout(5)
            ->timeout(15)
            ->get(
                rtrim($this->baseUrl, '/')
                    . '/generations/'
                    . rawurlencode($providerGenerationId),
            );
    }

    public function verifyWebhookSignature(
        string $payload,
        string $header,
    ): bool {
        if ($this->webhookSecret === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(
                explode('=', trim($part), 2),
                2,
                '',
            );

            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if (
            ! ctype_digit($timestamp)
            || ! preg_match('/\A[0-9a-f]{64}\z/', $signature)
            || abs(time() - (int) $timestamp) > 300
        ) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $timestamp . '.' . $payload,
            $this->webhookSecret,
        );

        return hash_equals($expected, $signature);
    }

    public function isTerminal(string $status): bool
    {
        return in_array($status, ['succeeded', 'failed'], true);
    }

    public function submit(Generation $generation): Response
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->apiKey)
            ->withHeaders([
                'Idempotency-Key' => $generation->idempotency_key,
            ])
            ->connectTimeout(5)
            ->timeout(15)
            ->post(rtrim($this->baseUrl, '/') . '/generations', [
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $generation->reference_images ?? [],
                'metadata' => [
                    'local_generation_id' => (string) $generation->getKey(),
                ],
            ]);
    }

    /**
     * @return array{
     *     outcome: 'accepted'|'retry'|'failed',
     *     retry_after_seconds: int,
     *     message: string
     * }
     */
    public function classifyResponse(
        Response $response,
        int $expectedStatus,
    ): array {
        if ($response->status() === $expectedStatus) {
            return [
                'outcome' => 'accepted',
                'retry_after_seconds' => 0,
                'message' => '',
            ];
        }

        $errorCode = $response->json('error.code');

        $retryable = $response->status() === 503
            || (
                $response->status() === 429
                && in_array($errorCode, [
                    'rate_limit_exceeded',
                    'concurrency_limit_exceeded',
                ], true)
            );

        if ($retryable) {
            $header = trim((string) $response->header('Retry-After'));

            return [
                'outcome' => 'retry',
                'retry_after_seconds' => ctype_digit($header)
                    ? (int) $header
                    : 0,
                'message' => 'Provider temporarily unavailable or limited.',
            ];
        }

        if ($errorCode === 'daily_limit_exceeded') {
            return [
                'outcome' => 'failed',
                'retry_after_seconds' => 0,
                'message' => 'The provider daily generation quota is exhausted.',
            ];
        }

        if (in_array($errorCode, [
            'invalid_api_key',
            'validation_failed',
            'content_policy_violation',
            'idempotency_key_reused',
            'not_found',
        ], true)) {
            return [
                'outcome' => 'failed',
                'retry_after_seconds' => 0,
                'message' => 'Pixelforge request failed: ' . $errorCode,
            ];
        }

        return [
            'outcome' => 'retry',
            'retry_after_seconds' => 0,
            'message' => 'Unable to obtain the provider generation.',
        ];
    }
}
