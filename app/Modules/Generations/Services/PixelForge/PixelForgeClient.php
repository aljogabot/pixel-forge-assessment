<?php

namespace Modules\Generations\Services\PixelForge;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Modules\Generations\Contracts\ImageGenerationProvider;

use Illuminate\Support\Facades\Http;
use Modules\Generations\Contracts\GenerationWebhookVerifier;
use UnexpectedValueException;
use Illuminate\Support\Facades\Validator;

class PixelForgeClient implements ImageGenerationProvider, GenerationWebhookVerifier
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $webhookSecret,
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

            $rules = [
                'rates' => ['required', 'array'],
                'tokens' => ['required', 'array'],
                'tokens.characters_per_text_token' => ['required', 'integer', 'min:1'],
                'tokens.per_reference_image' => ['required', 'integer', 'min:0'],
            ];

            foreach (
                [
                    'input_text_tokens',
                    'input_image_tokens',
                    'output_tokens',
                    'reasoning_tokens',
                ] as $category
            ) {
                $rules["rates.$category"] = [
                    'required',
                    'string',
                    'regex:/\A[0-9]+(?:\.[0-9]+)?\z/',
                ];
            }

            foreach (['low', 'medium', 'high'] as $quality) {
                foreach (['1024x1024', '1536x1024', '1024x1536'] as $size) {
                    $rules["tokens.output.$quality.$size"] = [
                        'required',
                        'integer',
                        'min:0',
                    ];
                }

                $rules["tokens.reasoning.$quality.min"] = [
                    'required',
                    'integer',
                    'min:0',
                ];

                $rules["tokens.reasoning.$quality.max"] = [
                    'required',
                    'integer',
                    'min:0',
                    "gte:tokens.reasoning.$quality.min",
                ];
            }

            if (Validator::make($pricing, $rules)->fails()) {
                throw new UnexpectedValueException(
                    'Pixelforge returned invalid pricing rates or token rules.',
                );
            }

            return $pricing;
        });
    }

    public function submit(array $parameters, string $idempotencyKey): Response
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->apiKey)
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->connectTimeout(5)
            ->timeout(15)
            ->post(rtrim($this->baseUrl, '/') . '/generations', $parameters);
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
}
