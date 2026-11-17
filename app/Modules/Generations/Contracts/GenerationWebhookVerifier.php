<?php

namespace Modules\Generations\Contracts;

interface GenerationWebhookVerifier
{
    public function verifyWebhookSignature(
        string $payload,
        array $headers,
    ): bool;
}
