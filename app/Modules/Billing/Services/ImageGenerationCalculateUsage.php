<?php

namespace Modules\Billing\Services;

final class ImageGenerationCalculateUsage
{
    /**
     * Estimate token usage for an image generation.
     *
     * Text tokens use the provider's characters-per-token rule.
     * Reasoning tokens use the integer midpoint of the quality range.
     * Actual text and reasoning usage are known only after completion.
     * @param string $prompt The user's image-generation prompt.
     * @param string $size A supported image size.
     * @param string $quality A supported quality level.
     * @param int $referenceImageCount Number of reference images, from 0 to 4.
     * @param array{
     *   characters_per_text_token: int,
     *   per_reference_image: int,
     *   output: array<string, array<string, int>>,
     *   reasoning: array<string, array{min: int, max: int}>
     * } $tokens
     *
     * @return array{
     *  input_text_tokens: int,
     *  input_image_tokens: int,
     *  output_tokens: int,
     *  reasoning_tokens: int
     * }
     */
    public function handle(
        string $prompt,
        string $size,
        string $quality,
        int $referenceImageCount,
        array $tokens,
    ): array {
        $reasoning = $tokens['reasoning'][$quality];

        $usage = [
            'input_text_tokens' => (int) ceil(
                mb_strlen($prompt, 'UTF-8')
                    / $tokens['characters_per_text_token'],
            ),
            'input_image_tokens' => $referenceImageCount
                * $tokens['per_reference_image'],
            'output_tokens' => $tokens['output'][$quality][$size],
            'reasoning_tokens' => intdiv(
                $reasoning['min'] + $reasoning['max'],
                2,
            ),
        ];

        return $usage;
    }
}
