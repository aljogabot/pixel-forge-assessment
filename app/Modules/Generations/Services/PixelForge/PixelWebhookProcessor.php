<?php

namespace Modules\Generations\Services\PixelForge;

use Modules\Generations\Contracts\WebhookProviderProcessor;
use Modules\Generations\Services\ApplyImageGenerationResult;
use Modules\Generations\Models\Generation;
use Modules\Generations\Models\GenerationWebhookEvent;
use RuntimeException;
use UnexpectedValueException;
use Illuminate\Support\Facades\DB;

class PixelWebhookProcessor implements WebhookProviderProcessor
{
    public function __construct(
        private readonly string $webhookSecret,
        private readonly PixelForgePayloadValidator $eventValidator,
        private readonly ApplyImageGenerationResult $applyResult
    ) {}

    /** @param array<string, list<string>> $headers */
    public function verifyWebhookSignature(
        string $payload,
        array $headers,
    ): bool {
        $header = trim($headers['pixelforge-signature'][0] ?? '');

        if ($header === '') {
            return false;
        }

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

        // for the payload, it should have the exact characters/bytes sent
        // The signature checks the exact bytes sent, including spaces.
        $expected = hash_hmac(
            'sha256',
            $timestamp . '.' . $payload,
            $this->webhookSecret,
        );

        return hash_equals($expected, $signature);
    }

    /** @param string $payload */
    public function handle(string $payload): void
    {
        $event = json_decode($payload, true, 512);

        if (! is_array($event)) {
            throw new UnexpectedValueException('Invalid webhook JSON.');
        }

        // Validate and decode the webhook payload before writing to the database.
        $this->eventValidator->validateEvent($event);

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

            // An early webhook may arrive before SubmitImageGenerationJob
            // saves the provider generation ID from the submission response.
            if ($generation === null) {
                $localId = $result['metadata']['local_generation_id'] ?? null;

                if ($localId !== null) {
                    $generation = Generation::query()
                        ->whereKey($localId)
                        ->lockForUpdate()
                        ->first();
                }
            }

            if ($generation === null) {
                throw new RuntimeException(
                    'The webhook generation is not available locally yet.',
                );
            }

            $lockedEvent->generation_id = $generation->getKey();

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
