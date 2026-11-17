<?php

namespace Modules\Billing\Actions;

use Modules\Billing\Services\ImageGenerationCalculateCost;
use Modules\Billing\Services\ImageGenerationCalculateUsage;

final class GetImageGenerationPriceEstimate
{
    public function __construct(
        private readonly ImageGenerationCalculateUsage $usageBuilder,
        private readonly ImageGenerationCalculateCost $calculator,
    ) {}

    /**
     * Calculate an estimated user charge from estimated token usage.
     *
     * @param array{
     *     prompt: string,
     *     size: string,
     *     quality: string,
     *     reference_images?: list<string>
     * } $generationInput
     * @param array{
     *     rates: array{
     *         input_text_tokens: string,
     *         input_image_tokens: string,
     *         output_tokens: string,
     *         reasoning_tokens: string
     *     },
     *     tokens: array{
     *         characters_per_text_token: int,
     *         per_reference_image: int,
     *         output: array<string, array<string, int>>,
     *         reasoning: array<string, array{min: int, max: int}>
     *     }
     * } $pricing Provider rates in USD per million tokens and token estimation rules.
     * @return string Estimated user charge in USD with 12 decimal places.
     */
    public function handle(
        array $generationInput,
        array $pricing,
    ): string {
        $usage = $this->usageBuilder->handle(
            $generationInput['prompt'],
            $generationInput['size'],
            $generationInput['quality'],
            count($generationInput['reference_images'] ?? []),
            $pricing['tokens'],
        );

        $result = $this->calculator->handle($usage, $pricing['rates']);

        return $result['actual_charge_usd'];
    }
}
