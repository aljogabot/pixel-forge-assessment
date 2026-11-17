<?php

namespace Modules\Generations\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Modules\Generations\Contracts\ImageGenerationProvider;
use Modules\Generations\Models\Generation;
use Modules\Generations\Services\ApplyImageGenerationResult;
use Modules\Generations\Services\PixelForge\PixelForgePayloadValidator;
use RuntimeException;
use UnexpectedValueException;

final class SubmitImageGenerationJob implements ShouldQueue
{
    use Queueable;

    // Allow at most six queue attempts.
    public int $tries = 6;

    // Limit each job execution to 30 seconds.
    public int $timeout = 30;

    public function __construct(
        public readonly int $generationId
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function handle(
        ImageGenerationProvider $provider,
        ApplyImageGenerationResult $applyResult,
        PixelForgePayloadValidator $payloadValidator,
    ): void {
        $generation = Generation::query()
            ->findOrFail($this->generationId);

        if (in_array($generation->status, ['succeeded', 'failed'], true)) {
            return;
        }

        if ($generation->provider_generation_id !== null) {
            $response = $provider->retrieve(
                $generation->provider_generation_id,
            );

            $expectedStatus = 200;
        } else {

            // Provider submission and response handling come next.
            // In This case, Provider is PixelForge, we can just change Providers without updating this code ...
            // I havent used other providers, but we can have an adapter pattern to suit the params ... and response ...
            $response = $provider->submit(
                parameters: [
                    'prompt' => $generation->prompt,
                    'size' => $generation->size,
                    'quality' => $generation->quality,
                    'reference_images' => $generation->reference_images ?? [],
                    'metadata' => [
                        'local_generation_id' => (string) $generation->getKey(),
                    ],
                ],
                // This Key prevents duplication request to the provider ...
                // PixelForge does not have a lookup by this key so if there is a record, we will use that
                // Probably they do that internally ...
                idempotencyKey: $generation->idempotency_key,
            );

            $expectedStatus = 202;
        }


        if ($response->status() !== $expectedStatus) {
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
                // Stop retrying after the configured attempt limit.
                if ($this->attempts() >= $this->tries) {
                    $this->fail(new RuntimeException(
                        'Provider submission or retrieval retry limit reached.',
                    ));

                    return;
                }

                // Pixelforge documents Retry-After as a number of seconds.
                $header = trim((string) $response->header('Retry-After'));

                $providerDelay = ctype_digit($header)
                    ? (int) $header
                    : 0;

                $delays = $this->backoff();

                $backoffDelay = $delays[min($this->attempts() - 1, count($delays) - 1)];

                // Never retry earlier than the provider permits.
                $this->release(max($providerDelay, $backoffDelay));

                return;
            }

            if ($errorCode === 'daily_limit_exceeded') {
                $this->fail(new RuntimeException(
                    'The provider daily generation quota is exhausted.',
                ));

                return;
            }

            // These errors require corrected input, credentials, or configuration.
            // Repeating the same request will not resolve them.
            if (in_array($errorCode, [
                'invalid_api_key',
                'validation_failed',
                'content_policy_violation',
                'idempotency_key_reused',
                'not_found',
            ], true)) {
                $this->fail(new RuntimeException(
                    'Pixelforge request failed: ' . $errorCode,
                ));

                return;
            }

            // Retry unclassified failures within the configured attempt limit.
            throw new RuntimeException(
                'Unable to obtain the provider generation.',
            );
        }

        $result = $response->json();

        if (! is_array($result)) {
            throw new UnexpectedValueException(
                'Invalid provider generation JSON.',
            );
        }

        // Validate the same generation fields used by webhook processing.
        $payloadValidator->validateGeneration($result);

        // We can have a Repository so that we can change persistence implementations
        DB::transaction(function () use ($result, $applyResult): void {
            $generation = Generation::query()
                ->lockForUpdate()
                ->findOrFail($this->generationId);

            $applyResult->handle(
                generation: $generation,
                result: $result,
            );
        });
    }
}
