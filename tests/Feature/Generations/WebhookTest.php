<?php

use Illuminate\Support\Facades\Http;
use Modules\Generations\Contracts\WebhookProviderProcessor;
use Modules\Generations\Models\Generation;
use Modules\Generations\Models\GenerationWebhookEvent;
use Modules\Generations\Services\ApplyImageGenerationResult;
use Modules\Generations\Services\PixelForge\PixelForgePayloadValidator;
use Modules\Generations\Services\PixelForge\PixelWebhookProcessor;

use Modules\Generations\Models\GenerationRecovery;
use Modules\Generations\Services\GenerationRecoveryService;

test('settles a generation and records duplicate delivery only once', function () {
    Http::preventStrayRequests();

    $secret = 'test-webhook-secret';

    $this->app->instance(
        WebhookProviderProcessor::class,
        new PixelWebhookProcessor(
            webhookSecret: $secret,
            eventValidator: $this->app->make(PixelForgePayloadValidator::class),
            applyResult: $this->app->make(ApplyImageGenerationResult::class),
        ),
    );

    $generation = Generation::factory()->create([
        'provider_generation_id' => 'gen_test',
        'status' => 'processing',
    ]);

    $payload = json_encode([
        'id' => 'evt_test',
        'object' => 'event',
        'type' => 'generation.succeeded',
        'created_at' => now()->toIso8601String(),
        'data' => [
            'object' => [
                'id' => 'gen_test',
                'status' => 'succeeded',
                'completed_at' => now()->toIso8601String(),
                'image_url' => 'https://example.com/generated.png',
                'image_url_expires_at' => now()->addDay()->toIso8601String(),
                'usage' => [
                    'input_text_tokens' => 8,
                    'input_image_tokens' => 0,
                    'output_tokens' => 280,
                    'reasoning_tokens' => 455,
                ],
                'rates' => [
                    'input_text_tokens' => '5.00',
                    'input_image_tokens' => '10.00',
                    'output_tokens' => '40.00',
                    'reasoning_tokens' => '12.50',
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signature = hash_hmac(
        'sha256',
        $timestamp . '.' . $payload,
        $secret,
    );

    for ($delivery = 0; $delivery < 2; $delivery++) {
        $this->call(
            'POST',
            route('webhooks.pixelforge'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PIXELFORGE_SIGNATURE' =>
                "t={$timestamp},v1={$signature}",
            ],
            $payload,
        )->assertNoContent();
    }

    $generation->refresh();

    expect($generation->status)->toBe('succeeded')
        ->and($generation->provider_cost_usd)->toBe('0.016927500000')
        ->and($generation->actual_charge_usd)->toBe('0.021159375000');

    $this->assertDatabaseCount('generation_webhook_events', 1);

    expect(
        GenerationWebhookEvent::query()->firstOrFail()->processed_at,
    )->not->toBeNull();
});


test('rejects an invalid or expired webhook signature', function (
    int $timestampOffset,
    string $signingSecret,
) {
    Http::preventStrayRequests();

    $this->app->instance(
        WebhookProviderProcessor::class,
        new PixelWebhookProcessor(
            webhookSecret: 'test-webhook-secret',
            eventValidator: $this->app->make(PixelForgePayloadValidator::class),
            applyResult: $this->app->make(ApplyImageGenerationResult::class),
        ),
    );

    $payload = json_encode([
        'id' => 'evt_rejected',
        'object' => 'event',
        'type' => 'ping',
        'created_at' => now()->toIso8601String(),
    ], JSON_THROW_ON_ERROR);

    $timestamp = time() + $timestampOffset;
    $signature = hash_hmac(
        'sha256',
        $timestamp . '.' . $payload,
        $signingSecret,
    );

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' =>
            "t={$timestamp},v1={$signature}",
        ],
        $payload,
    )->assertBadRequest();

    $this->assertDatabaseCount('generation_webhook_events', 0);
})->with([
    'incorrect signature' => [0, 'wrong-secret'],
    'expired signature' => [-600, 'test-webhook-secret'],
]);

test('does not regress a terminal generation after a late processing event', function (
    string $status,
    string $providerCost,
    string $userCharge,
) {
    $secret = 'test-webhook-secret';

    $this->app->instance(
        WebhookProviderProcessor::class,
        new PixelWebhookProcessor(
            webhookSecret: $secret,
            eventValidator: $this->app->make(PixelForgePayloadValidator::class),
            applyResult: $this->app->make(ApplyImageGenerationResult::class),
        ),
    );

    $generation = Generation::factory()->create([
        'provider_generation_id' => 'gen_terminal',
        'status' => $status,
        'provider_cost_usd' => $providerCost,
        'actual_charge_usd' => $userCharge,
    ]);

    $payload = json_encode([
        'id' => 'evt_late_processing',
        'object' => 'event',
        'type' => 'generation.processing',
        'created_at' => now()->subMinute()->toIso8601String(),
        'data' => [
            'object' => [
                'id' => 'gen_terminal',
                'status' => 'processing',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signature = hash_hmac(
        'sha256',
        $timestamp . '.' . $payload,
        $secret,
    );

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' =>
            "t={$timestamp},v1={$signature}",
        ],
        $payload,
    )->assertNoContent();

    $generation->refresh();

    expect($generation->status)->toBe($status)
        ->and($generation->provider_cost_usd)->toBe($providerCost)
        ->and($generation->actual_charge_usd)->toBe($userCharge);

    expect(
        GenerationWebhookEvent::query()->firstOrFail()->processed_at,
    )->not->toBeNull();
})->with([
    'already succeeded' => [
        'succeeded',
        '0.016927500000',
        '0.021159375000',
    ],
    'already failed' => [
        'failed',
        '0.000000000000',
        '0.000000000000',
    ],
]);


test('records zero charges and completes recovery when generation fails', function () {
    $secret = 'test-webhook-secret';

    $this->app->instance(
        WebhookProviderProcessor::class,
        new PixelWebhookProcessor(
            webhookSecret: $secret,
            eventValidator: $this->app->make(PixelForgePayloadValidator::class),
            applyResult: $this->app->make(ApplyImageGenerationResult::class),
        ),
    );

    $generation = Generation::factory()->create([
        'provider_generation_id' => 'gen_failed',
        'status' => 'processing',
        'estimated_charge_usd' => '0.060000000000',
    ]);

    $this->app->make(GenerationRecoveryService::class)
        ->initialize($generation);

    $payload = json_encode([
        'id' => 'evt_failed',
        'object' => 'event',
        'type' => 'generation.failed',
        'created_at' => now()->toIso8601String(),
        'data' => [
            'object' => [
                'id' => 'gen_failed',
                'status' => 'failed',
                'completed_at' => now()->toIso8601String(),
                'error' => [
                    'code' => 'generation_failed',
                    'message' => 'The provider could not generate an image.',
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signature = hash_hmac(
        'sha256',
        $timestamp . '.' . $payload,
        $secret,
    );

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' =>
            "t={$timestamp},v1={$signature}",
        ],
        $payload,
    )->assertNoContent();

    $generation->refresh();

    expect($generation->status)->toBe('failed')
        ->and($generation->provider_cost_usd)->toBe('0.000000000000')
        ->and($generation->actual_charge_usd)->toBe('0.000000000000')
        ->and($generation->estimated_charge_usd)->toBe('0.060000000000')
        ->and($generation->error_code)->toBe('generation_failed')
        ->and($generation->error_message)
        ->toBe('The provider could not generate an image.');

    expect(
        GenerationRecovery::query()
            ->where('generation_id', $generation->getKey())
            ->firstOrFail()
            ->completed_at,
    )->not->toBeNull();

    expect(
        GenerationWebhookEvent::query()->firstOrFail()->processed_at,
    )->not->toBeNull();
});
