<?php

use Modules\Billing\Services\ImageGenerationCalculateCost;

test('calculates exact provider cost and the user charge', function () {
    $calculator = new ImageGenerationCalculateCost();

    $result = $calculator->handle(
        usage: [
            'input_text_tokens' => 8,
            'input_image_tokens' => 0,
            'output_tokens' => 280,
            'reasoning_tokens' => 455,
        ],
        rates: [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    );

    expect($result)->toBe([
        'provider_cost_usd' => '0.016927500000',
        'actual_charge_usd' => '0.021159375000',
    ]);
});
