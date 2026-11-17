<?php

namespace Modules\Generations\Contracts;

use Illuminate\Http\Client\Response;
use Modules\Generations\Models\Generation;

interface ImageGenerationProvider
{
    /** @return array<string, mixed> */
    public function pricing(): array;

    /** @param Generation $generation */
    public function submit(Generation $generation): Response;

    public function retrieve(string $providerGenerationId): Response;


    public function isTerminal(string $status): bool;

    /**
     * @return array{
     *     outcome: 'accepted'|'retry'|'failed',
     *     retry_after_seconds: int,
     *     message: string
     * }
     */
    public function classifyResponse(
        Response $response,
        string $operation,
    ): array;
}
