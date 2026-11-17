<?php

namespace Modules\Generations\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Billing\Actions\GetImageGenerationPriceEstimate;
use Modules\Generations\Contracts\ImageGenerationProvider;
use Modules\Generations\Models\Generation;
use Modules\User\Models\User;
use Modules\Generations\Jobs\SubmitImageGenerationJob;
use Modules\Generations\Services\GenerationRecoveryService;

final class SubmitImageGeneration
{
    public function __construct(
        private readonly ImageGenerationProvider $provider,
        private readonly GetImageGenerationPriceEstimate $priceEstimate,
        private readonly GenerationRecoveryService $recovery
    ) {}

    /**
     * @param array{
     *     prompt: string,
     *     size: string,
     *     quality: string,
     *     reference_images?: list<string>
     * } $generationInput
     */
    public function handle(
        User $user,
        array $generationInput,
    ): Generation {
        $estimatedCharge = $this->priceEstimate->handle(
            generationInput: $generationInput,
            pricing: $this->provider->pricing(),
        );

        return DB::transaction(function () use (
            $user,
            $generationInput,
            $estimatedCharge,
        ): Generation {
            $generation = new Generation();
            $generation->user_id = $user->getKey();
            $generation->idempotency_key = (string) Str::uuid();
            $generation->prompt = $generationInput['prompt'];
            $generation->size = $generationInput['size'];
            $generation->quality = $generationInput['quality'];
            $generation->reference_images =
                $generationInput['reference_images'] ?? [];
            $generation->status = 'pending';
            $generation->estimated_charge_usd = $estimatedCharge;
            $generation->save();

            // Initialize recovery tracking in case submission stalls
            // or a completion webhook is missed.
            $this->recovery->initialize($generation);

            // Dispatch only after both records commit successfully.
            SubmitImageGenerationJob::dispatch($generation->getKey())
                ->afterCommit();

            return $generation;
        });
    }
}
