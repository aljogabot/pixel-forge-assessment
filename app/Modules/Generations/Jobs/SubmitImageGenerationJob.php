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

    public function __construct(
        public readonly int $generationId
    ) {}

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
            // Explicit rejection and retry handling comes next.
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
        $result = $payloadValidator->validateGeneration($result);

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
