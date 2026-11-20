<?php

namespace Modules\Generations\Services;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class ProviderRequestLimiter
{
    public function __construct(
        private readonly string $scope,
        private readonly int $requestsPerMinute,
    ) {
        if ($requestsPerMinute < 1) {
            throw new InvalidArgumentException(
                'The provider request limit must be positive.',
            );
        }
    }

    /**
     * Reserve one request slot.
     *
     * Returns zero when allowed, otherwise seconds to wait.
     *
     * The value of the lock is:
     *
     *  [
            1795204800.123,
            1795204810.456,
            1795204820.789,
        ]
     */
    public function acquire(): int
    {
        $key = 'provider-requests:' . hash('sha256', $this->scope);

        $delay = Cache::lock($key . ':lock', 5)->get(
            function () use ($key): int {
                $now = microtime(true);

                // Retain requests reserved within the last 60 seconds.
                // the timestamps where workers started to process,
                // and filter them by requests that processed now - 60 seconds ...
                // meaning within the minute ...
                $requests = array_values(array_filter(
                    Cache::get($key, []),
                    fn(float $timestamp): bool => $timestamp > $now - 60,
                ));


                // so get the count of the requests within the last minute
                // MINUS the providers request per minute from the provider settings ...
                // return atleast 1 second or if the earliest time within the minute ...
                if (count($requests) >= $this->requestsPerMinute) {
                    return max(
                        1,
                        (int) ceil($requests[0] + 60 - $now),
                    );
                }

                $requests[] = $now;

                Cache::put($key, $requests, 70);

                return 0;
            },
        );

        // Another worker currently holds the reservation lock.
        return $delay === false ? 1 : $delay;
    }
}
