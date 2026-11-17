<?php

namespace Modules\Generations\Contracts;

use Illuminate\Http\Client\Response;

interface ImageGenerationProvider
{
    /** @return array<string, mixed> */
    public function pricing(): array;

    /** @param array<string, mixed> $parameters */
    public function submit(array $parameters, string $idempotencyKey): Response;

    public function retrieve(string $providerGenerationId): Response;
}
