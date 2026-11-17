<?php

namespace Modules\Generations\Contracts;

interface GeneratePayloadValidator
{
    public function validateGeneration(array $generation): void;
}
