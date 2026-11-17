<?php

namespace Modules\Generations\Services;

use Modules\Billing\Services\ImageGenerationCalculateCost;
use Modules\Generations\Models\Generation;
use UnexpectedValueException;

final class ApplyImageGenerationResult
{
    public function __construct(
        private readonly ImageGenerationCalculateCost $calculator,
    ) {}

    /**
     * Apply a validated provider generation snapshot.
     *
     * @param array<string, mixed> $result
     */
    public function handle(Generation $generation, array $result): void
    {
        if (
            $generation->provider_generation_id !== null
            && $generation->provider_generation_id !== $result['id']
        ) {
            throw new UnexpectedValueException(
                'Provider generation ID does not match.',
            );
        }

        // Prevent any late events that might overwrite newer event
        if (in_array(
            $generation->status,
            ['succeeded', 'failed'],
            true,
        )) {
            return;
        }


        if (!in_array(
            $result['status'],
            ['queued', 'processing', 'succeeded', 'failed'],
            true,
        )) {
            throw new UnexpectedValueException(
                'Invalid provider generation status.',
            );
        }

        $generation->provider_generation_id = $result['id'];

        // An older queued snapshot must not undo processing.
        if (
            $generation->status === 'processing'
            && $result['status'] === 'queued'
        ) {
            $generation->save();

            return;
        }

        // processing, failed and succeeded only passes here ...
        $generation->status = $result['status'];

        if ($result['status'] === 'failed') {
            $generation->provider_cost_usd = '0.000000000000';
            $generation->actual_charge_usd = '0.000000000000';
            $generation->completed_at = $result['completed_at'];
            $generation->error_code = $result['error']['code'];
            $generation->error_message = $result['error']['message'];
        }

        if ($result['status'] === 'succeeded') {
            $cost = $this->calculator->handle(
                usage: $result['usage'],
                rates: $result['rates'],
            );

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

        // if status is processing ...
        // we can wait for another webhook for the final result ...
    }
}
