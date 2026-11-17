<?php

use Illuminate\Support\Facades\Queue;
use Modules\Generations\Jobs\SubmitImageGenerationJob;
use Modules\Generations\Models\Generation;
use Modules\Generations\Models\GenerationRecovery;
use Modules\Generations\Services\GenerationRecoveryService;

test('dispatches a due recovery once and advances its schedule', function () {
    $this->freezeSecond();

    $generation = Generation::factory()->create([
        'status' => 'queued',
        'provider_generation_id' => 'gen_recovery',
    ]);

    $service = $this->app->make(GenerationRecoveryService::class);
    $service->initialize($generation);

    Queue::fake([SubmitImageGenerationJob::class]);

    // Initial recovery becomes due after two minutes.
    $this->travel(3)->minutes();

    expect($service->dispatchDue())->toBe(1);

    // It must not dispatch again before the next scheduled attempt.
    expect($service->dispatchDue())->toBe(0);

    Queue::assertPushed(
        SubmitImageGenerationJob::class,
        fn(SubmitImageGenerationJob $job): bool =>
        $job->generationId === $generation->getKey(),
    );

    Queue::assertPushed(SubmitImageGenerationJob::class, 1);

    $recovery = GenerationRecovery::query()
        ->where('generation_id', $generation->getKey())
        ->firstOrFail();

    expect($recovery->attempts)->toBe(1)
        ->and($recovery->next_attempt_at->equalTo(now()->addMinutes(5)))
        ->toBeTrue()
        ->and($recovery->completed_at)->toBeNull();
});

test('does not consume recovery attempts while a generation job is outstanding', function () {
    $this->freezeSecond();

    $generation = Generation::factory()->create([
        'status' => 'queued',
        'provider_generation_id' => 'gen_outstanding',
    ]);

    $service = $this->app->make(GenerationRecoveryService::class);
    $service->initialize($generation);

    Queue::fake([SubmitImageGenerationJob::class]);

    // Queue the original job without executing it.
    SubmitImageGenerationJob::dispatch($generation->getKey());

    $this->travel(3)->minutes();

    expect($service->dispatchDue())->toBe(0);

    Queue::assertPushed(SubmitImageGenerationJob::class, 1);

    $recovery = GenerationRecovery::query()
        ->where('generation_id', $generation->getKey())
        ->firstOrFail();

    expect($recovery->attempts)->toBe(0)
        ->and($recovery->completed_at)->toBeNull();
});


test('does not dispatch an ineligible recovery', function (
    ?string $errorCode,
    int $attempts,
    bool $completed,
    int $nextAttemptOffset,
) {
    $this->freezeSecond();

    $generation = Generation::factory()->create([
        'status' => 'queued',
        'error_code' => $errorCode,
    ]);

    $service = $this->app->make(GenerationRecoveryService::class);
    $service->initialize($generation);

    $recovery = GenerationRecovery::query()
        ->where('generation_id', $generation->getKey())
        ->firstOrFail();

    $recovery->forceFill([
        'attempts' => $attempts,
        'completed_at' => $completed ? now() : null,
        'next_attempt_at' => now()->addMinutes($nextAttemptOffset),
    ])->save();

    Queue::fake([SubmitImageGenerationJob::class]);

    expect($service->dispatchDue())->toBe(0);

    // Claim must also reject it if selection happened before eligibility changed.
    expect($service->claim($recovery))->toBeNull();

    Queue::assertNothingPushed();

    expect($recovery->fresh()->attempts)->toBe($attempts);
})->with([
    'failed job requires intervention' => [
        'submission_job_failed',
        0,
        false,
        -1,
    ],
    'recovery budget exhausted' => [
        null,
        3,
        false,
        -1,
    ],
    'recovery already completed' => [
        null,
        0,
        true,
        -1,
    ],
    'next attempt is not due' => [
        null,
        0,
        false,
        5,
    ],
]);
