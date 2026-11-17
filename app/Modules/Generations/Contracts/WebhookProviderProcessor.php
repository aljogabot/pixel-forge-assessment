<?php

namespace Modules\Generations\Contracts;

interface WebhookProviderProcessor
{
    /** @param array<string, list<string>> $headers */
    public function verifyWebhookSignature(
        string $payload,
        array $headers,
    ): bool;

    public function handle(string $payload): void;
}
