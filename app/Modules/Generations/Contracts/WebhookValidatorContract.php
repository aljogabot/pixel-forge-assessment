<?php

namespace Modules\Generations\Contracts;

interface WebhookValidatorContract
{
    public function validateEvent(array $event): void;
}
