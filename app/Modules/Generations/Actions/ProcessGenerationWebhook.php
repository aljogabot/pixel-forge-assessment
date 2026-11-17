<?php

namespace Modules\Generations\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Generations\Models\Generation;
use Modules\Generations\Models\GenerationWebhookEvent;
use Modules\Generations\Services\ApplyImageGenerationResult;
use Modules\Generations\Services\PixelForge\PixelForgePayloadValidator;
use RuntimeException;
use UnexpectedValueException;

/**
 * I added comments per process so it is easy to follow ....
 */
final class ProcessGenerationWebhook
{
    public function __construct(
        private readonly PixelForgePayloadValidator $eventValidator,
        private readonly ApplyImageGenerationResult $applyResult
    ) {}

    /** @param string $payload */
    public function handle(string $payload): void
    {
        // Validate and decode the webhook payload before writing to the database.
        $event = $this->eventValidator->validateEvent($payload);

        // Create Or First handles Uniqueness ...
        // The Provider might send 2 or more concurrent requests...
        $record = GenerationWebhookEvent::query()->createOrFirst(
            [
                'provider' => 'pixelforge',
                'provider_event_id' => $event['id'],
            ],
            [
                'type' => $event['type'],
                'payload' => $event,
            ],
        );

        // Process this event under a row lock so concurrent deliveries wait.
        DB::transaction(function () use ($record): void {
            $lockedEvent = GenerationWebhookEvent::query()
                // SELECT ... FOR UPDATE
                // So this row is locked for transactions only ...
                ->lockForUpdate()
                ->findOrFail($record->getKey());

            // Skip events whose effects were already committed successfully.
            // Meaning if this is not null ... Event Record is done processing ... cannot be changed...
            if ($lockedEvent->processed_at !== null) {
                return;
            }

            if ($lockedEvent->type === 'ping') {
                $lockedEvent->processed_at = now();
                $lockedEvent->save();

                return;
            }

            // Read the generation snapshot captured when this event occurred.
            $result = $lockedEvent->payload['data']['object'];

            // Reject contradictory event types and generation statuses.
            // generation.processing	Generation started processing
            // generation.succeeded	    Generation completed successfully; includes usage and image
            // generation.failed	    Generation failed; includes error details
            // ping ...
            if ($lockedEvent->type !== 'generation.' . $result['status']) {
                throw new UnexpectedValueException(
                    'Webhook event type and generation status do not match.',
                );
            }

            // Lock the local generation so different events cannot update it concurrently.
            $generation = Generation::query()
                ->where('provider_generation_id', $result['id'])
                ->lockForUpdate()
                ->first();

            if ($generation === null) {
                throw new RuntimeException(
                    'The webhook generation is not available locally yet.',
                );
            }


            // The service protects terminal states and applies status,
            // failure details, or successful settlement.
            $this->applyResult->handle(
                generation: $generation,
                result: $result,
            );

            $lockedEvent->processed_at = now();
            $lockedEvent->save();
        });

        // Any exception here rolls back this transaction's writes.
        // It is different from node/nestjs
        // Laravel makes some operations automatic ...
    }
}
