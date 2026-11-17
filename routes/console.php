<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');



use Illuminate\Support\Facades\Schedule;
use Modules\Generations\Services\GenerationRecoveryService;


Schedule::call(function (GenerationRecoveryService $recovery): void {
    $recovery->dispatchDue();
})
    ->name('recover-image-generations')
    ->everyMinute()
    ->withoutOverlapping(10);
