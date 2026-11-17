<?php

namespace Modules\Generations\Contracts;

interface GenerationWebhookVerifier
{
    public function verifyWebhookSignature(
        string $payload,
        string $header,
    ): bool;
}
