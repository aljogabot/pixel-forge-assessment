<?php

namespace Modules\Generations\Services;

use Modules\Generations\Models\Generation;
use Modules\Generations\Models\GenerationRecovery as GenerationRecoveryModel;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

use Modules\Generations\Jobs\SubmitImageGenerationJob;
use Throwable;

use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

final class GenerationRecoveryService
{
    public function initialize(Generation $generation): void
    {
        GenerationRecoveryModel::query()->createOrFirst(
            [
                'generation_id' => $generation->getKey(),
            ],
            [
                'attempts' => 0,
                'next_attempt_at' => now()->addMinutes(2),
            ],
        );
    }

    public function complete(Generation $generation): void
    {
        GenerationRecoveryModel::query()
            ->where('generation_id', $generation->getKey())
            ->whereNull('completed_at')
            ->update([
                'completed_at' => now(),
                'last_error' => null,
            ]);
    }

    /**
     * @return Collection<int, GenerationRecoveryModel>
     */
    public function due(): Collection
    {
        return GenerationRecoveryModel::query()
            ->whereNull('completed_at')
            ->where('attempts', '<', 3)
            ->where('next_attempt_at', '<=', now())
            ->whereDoesntHave('generation', function (Builder $query): void {
                $query->where('error_code', 'submission_job_failed');
            })
            ->orderBy('next_attempt_at')
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    public function claim(GenerationRecoveryModel $recovery): ?int
    {
        return DB::transaction(function () use ($recovery): ?int {
            $lockedRecovery = GenerationRecoveryModel::query()
                ->lockForUpdate()
                ->find($recovery->getKey());

            if ($lockedRecovery === null) {
                return null;
            }

            if (
                $lockedRecovery->completed_at !== null
                || $lockedRecovery->attempts >= 3
                || $lockedRecovery->next_attempt_at->isFuture()
            ) {
                return null;
            }

            // Recheck eligibility because the job may have failed since selection.
            if (
                $lockedRecovery->generation()
                ->where('error_code', 'submission_job_failed')
                ->exists()
            ) {
                return null;
            }

            $lockedRecovery->attempts++;
            $lockedRecovery->next_attempt_at = now()->addMinutes(5);
            $lockedRecovery->save();

            return $lockedRecovery->generation_id;
        });
    }

    public function dispatchDue(): int
    {
        $dispatched = 0;
        $uniqueLock = new UniqueLock(Cache::store());

        foreach ($this->due() as $recovery) {
            $job = new SubmitImageGenerationJob(
                $recovery->generation_id,
            );

            // Reserve job uniqueness before consuming a recovery attempt.
            if (! $uniqueLock->acquire($job)) {
                continue;
            }

            try {
                $generationId = $this->claim($recovery);

                if ($generationId === null) {
                    $uniqueLock->release($job);

                    continue;
                }

                // Uniqueness was acquired above. Dispatch directly through
                // the bus so PendingDispatch doesn't acquire it a second time.
                Bus::dispatch($job);

                $dispatched++;
            } catch (Throwable $exception) {
                $uniqueLock->release($job);

                report($exception);

                GenerationRecoveryModel::query()
                    ->whereKey($recovery->getKey())
                    ->whereNull('completed_at')
                    ->update([
                        'last_error' => 'Unable to dispatch the recovery job.',
                    ]);
            }
        }

        return $dispatched;
    }
}
