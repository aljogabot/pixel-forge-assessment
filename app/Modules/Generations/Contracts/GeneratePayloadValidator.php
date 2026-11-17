<?php

namespace Modules\Generations\Contracts;

interface GeneratePayloadValidator
{
    /** @param array<string, mixed> $generation */
    public function validateGeneration(array $generation): void;
}
