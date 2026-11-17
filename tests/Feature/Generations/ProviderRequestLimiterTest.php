<?php

use Modules\Generations\Services\ProviderRequestLimiter;

test('shares the request budget between instances using the same scope', function () {
    $firstWorker = new ProviderRequestLimiter(
        scope: 'shared-provider-account',
        requestsPerMinute: 2,
    );

    $secondWorker = new ProviderRequestLimiter(
        scope: 'shared-provider-account',
        requestsPerMinute: 2,
    );

    expect($firstWorker->acquire())->toBe(0)
        ->and($secondWorker->acquire())->toBe(0)
        ->and($firstWorker->acquire())->toBeGreaterThan(0);
});

test('keeps different provider scopes independent', function () {
    $firstAccount = new ProviderRequestLimiter(
        scope: 'account-one',
        requestsPerMinute: 1,
    );

    $secondAccount = new ProviderRequestLimiter(
        scope: 'account-two',
        requestsPerMinute: 1,
    );

    expect($firstAccount->acquire())->toBe(0)
        ->and($firstAccount->acquire())->toBeGreaterThan(0)
        ->and($secondAccount->acquire())->toBe(0);
});
