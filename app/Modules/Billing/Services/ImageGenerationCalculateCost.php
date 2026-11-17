<?php

namespace Modules\Billing\Services;

final class ImageGenerationCalculateCost
{
    /**
     * @param array{
     *     input_text_tokens: int,
     *     input_image_tokens: int,
     *     output_tokens: int,
     *     reasoning_tokens: int
     * } $usage
     * @param array{
     *     input_text_tokens: string,
     *     input_image_tokens: string,
     *     output_tokens: string,
     *     reasoning_tokens: string
     * } $rates
     * @return array{provider_cost_usd: string, actual_charge_usd: string}
     */
    public function handle(array $usage, array $rates): array
    {
        $providerCost = '0.000000000000';

        foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $category) {
            $categoryCost = bcdiv(
                bcmul((string) $usage[$category], $rates[$category], 12),
                '1000000',
                12,
            );

            $providerCost = bcadd($providerCost, $categoryCost, 12);
        }

        return [
            'provider_cost_usd' => $providerCost,
            'actual_charge_usd' => bcmul($providerCost, '1.25', 12),
        ];
    }
}
