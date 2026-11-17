<?php

namespace Modules\Generations\Actions;

use Modules\Generations\Contracts\WebhookProviderProcessor;

/**
 * I added comments per process so it is easy to follow ....
 */
final class ProcessGenerationWebhook
{
    public function __construct(
        private readonly WebhookProviderProcessor $processor,
    ) {}

    public function verifyWebhookSignature(
        string $payload,
        array $headers,
    ): bool {
        return $this->processor->verifyWebhookSignature($payload, $headers);
    }

    /** @param string $payload */
    public function handle(string $payload): void
    {
        $this->processor->handle($payload);
    }
}
