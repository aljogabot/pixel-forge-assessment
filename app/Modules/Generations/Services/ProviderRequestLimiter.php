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
     */
    public function acquire(): int
    {
        $key = 'provider-requests:' . hash('sha256', $this->scope);

        $delay = Cache::lock($key . ':lock', 5)->get(
            function () use ($key): int {
                $now = microtime(true);

                // Retain requests reserved within the last 60 seconds.
                $requests = array_values(array_filter(
                    Cache::get($key, []),
                    fn(float $timestamp): bool => $timestamp > $now - 60,
                ));

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
