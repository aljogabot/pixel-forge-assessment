<?php

namespace Modules\Generations\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Modules\Generations\Contracts\GeneratePayloadValidator;
use Modules\Generations\Contracts\ImageGenerationProvider;
use Modules\Generations\Models\Generation;
use Modules\Generations\Services\ApplyImageGenerationResult;
use RuntimeException;
use UnexpectedValueException;
use Throwable;

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
        GeneratePayloadValidator $payloadValidator,
    ): void {
        $generation = Generation::query()
            ->findOrFail($this->generationId);

        if ($provider->isTerminal($generation->status)) {
            return;
        }

        if ($generation->provider_generation_id !== null) {
            $response = $provider->retrieve(
                $generation->provider_generation_id,
            );

            $operation = 'retrieve';
        } else {

            // Provider submission and response handling come next.
            // In This case, Provider is PixelForge, we can just change Providers without updating this code ...
            $response = $provider->submit($generation);

            $operation = 'submit';
        }


        $decision = $provider->classifyResponse($response, $operation);

        if ($decision['outcome'] === 'failed') {
            $this->fail(new RuntimeException($decision['message']));

            return;
        }

        if ($decision['outcome'] === 'retry') {
            if ($this->attempts() >= $this->tries) {
                $this->fail(new RuntimeException($decision['message']));

                return;
            }

            $delays = $this->backoff();

            $backoffDelay = $delays[min($this->attempts() - 1, count($delays) - 1)];

            $this->release(max(
                $decision['retry_after_seconds'],
                $backoffDelay,
            ));

            return;
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

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function (): void {
            $generation = Generation::query()
                ->lockForUpdate()
                ->find($this->generationId);

            if ($generation === null) {
                return;
            }

            // A webhook may already have completed the generation.
            if (in_array(
                $generation->status,
                ['succeeded', 'failed'],
                true,
            )) {
                return;
            }

            // Record the job failure without assuming the provider outcome.
            $generation->error_code = 'submission_job_failed';
            $generation->error_message =
                'Submission or status retrieval stopped. Recovery is required.';

            $generation->save();
        });
    }
}
