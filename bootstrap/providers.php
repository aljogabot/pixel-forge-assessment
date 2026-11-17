<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Modules\Generations\Providers\GenerationServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    GenerationServiceProvider::class,
];
