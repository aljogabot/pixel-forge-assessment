<?php

namespace Modules\Generations\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Services\ImageGenerationCalculateCost;
use Modules\Generations\Models\Generation;
use Modules\Generations\Models\GenerationWebhookEvent;
use Modules\Generations\Services\PixelForgeWebhookEventValidator;
use RuntimeException;
use UnexpectedValueException;

/**
 * I added comments per process so it is easy to follow ....
 */
final class ProcessGenerationWebhook
{
    public function __construct(
        private readonly PixelForgeWebhookEventValidator $eventValidator,
        private readonly ImageGenerationCalculateCost $calculator
    ) {}

    /** @param string $payload */
    public function handle(string $payload): void
    {
        // Validate and decode the webhook payload before writing to the database.
        $event = $this->eventValidator->validate($payload);

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


            // Prevent any late events that might overwrite newer event
            if (in_array(
                $generation->status,
                ['succeeded', 'failed'],
                true,
            )) {
                $lockedEvent->processed_at = now();
                $lockedEvent->save();

                return;
            }

            // Update the generation when Pixelforge starts processing it.
            if ($result['status'] === 'processing') {
                $generation->status = 'processing';
                $generation->save();

                $lockedEvent->processed_at = now();
                $lockedEvent->save();

                return;
            }

            // Failed status
            if ($result['status'] === 'failed') {
                $generation->status = 'failed';
                $generation->provider_cost_usd = '0.000000000000';
                $generation->actual_charge_usd = '0.000000000000';
                $generation->completed_at = $result['completed_at'];
                $generation->error_code = $result['error']['code'];
                $generation->error_message = $result['error']['message'];
                $generation->save();

                $lockedEvent->processed_at = now();
                $lockedEvent->save();

                return;
            }


            // Success
            if ($result['status'] === 'succeeded') {
                $cost = $this->calculator->handle(
                    usage: $result['usage'],
                    rates: $result['rates'],
                );

                $generation->status = 'succeeded';
                $generation->provider_cost_usd = $cost['provider_cost_usd'];
                $generation->actual_charge_usd = $cost['actual_charge_usd'];
                $generation->usage = $result['usage'];
                $generation->rates = $result['rates'];
                $generation->image_url = $result['image_url'];
                $generation->image_url_expires_at =
                    $result['image_url_expires_at'];
                $generation->completed_at = $result['completed_at'];
                $generation->error_code = null;
                $generation->error_message = null;
            }

            $generation->save();

            $lockedEvent->processed_at = now();
            $lockedEvent->save();
        });

        // Any exception here rolls back this transaction's writes.
        // It is different from node/nestjs
        // Laravel makes some operations automatic ...
    }
}
