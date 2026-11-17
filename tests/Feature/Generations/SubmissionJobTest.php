<?php

use Illuminate\Support\Facades\Http;
use Modules\Generations\Jobs\SubmitImageGenerationJob;
use Modules\Generations\Models\Generation;

it('releases the job using the greater of Retry-After and backoff', function (
    int $status,
    string $errorCode,
    string $retryAfter,
    int $expectedDelay,
): void {
    Http::preventStrayRequests();

    $provider = config('services.generation_provider.default');
    $baseUrl = rtrim(
        config("services.generation_provider.providers.{$provider}.base_url"),
        '/',
    );

    Http::fake([
        "{$baseUrl}/generations" => Http::response(
            ['error' => ['code' => $errorCode]],
            $status,
            ['Retry-After' => $retryAfter],
        ),
    ]);

    $generation = Generation::factory()->create([
        'status' => 'pending',
        'provider_generation_id' => null,
    ]);

    $job = (new SubmitImageGenerationJob($generation->getKey()))
        ->withFakeQueueInteractions();

    $this->app->call([$job, 'handle']);

    $job->assertReleased($expectedDelay);
    $job->assertNotFailed();

    Http::assertSentCount(1);

    $generation->refresh();

    expect($generation->status)->toBe('pending')
        ->and($generation->provider_generation_id)->toBeNull();
})->with([
    'rate limit respects Retry-After' => [
        429,
        'rate_limit_exceeded',
        '120',
        120,
    ],
    'concurrency limit respects Retry-After' => [
        429,
        'concurrency_limit_exceeded',
        '20',
        20,
    ],
    'overload keeps minimum backoff' => [
        503,
        'overloaded',
        '2',
        5,
    ],
]);

it('fails the job without retrying a permanent provider error', function (): void {
    Http::preventStrayRequests();

    $provider = config('services.generation_provider.default');
    $baseUrl = rtrim(
        config("services.generation_provider.providers.{$provider}.base_url"),
        '/',
    );

    Http::fake([
        "{$baseUrl}/generations" => Http::response(
            ['error' => ['code' => 'invalid_api_key']],
            401,
        ),
    ]);

    $generation = Generation::factory()->create([
        'status' => 'pending',
        'provider_generation_id' => null,
    ]);

    $job = (new SubmitImageGenerationJob($generation->getKey()))
        ->withFakeQueueInteractions();

    $this->app->call([$job, 'handle']);

    $job->assertFailedWith(
        new RuntimeException('Pixelforge request failed: invalid_api_key'),
    );

    $job->assertNotReleased();

    Http::assertSentCount(1);
});
