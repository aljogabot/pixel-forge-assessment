<?php

namespace Modules\Generations\Contracts;

interface WebhookValidatorContract
{
    /** @param array<string, mixed> $event */
    public function validateEvent(array $event): void;
}
