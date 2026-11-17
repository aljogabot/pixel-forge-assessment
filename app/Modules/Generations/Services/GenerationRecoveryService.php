<?php

namespace Modules\Generations\Services;

use Modules\Generations\Models\Generation;
use Modules\Generations\Models\GenerationRecovery as GenerationRecoveryModel;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

use Modules\Generations\Jobs\SubmitImageGenerationJob;
use Throwable;

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

        foreach ($this->due() as $recovery) {
            $generationId = $this->claim($recovery);

            if ($generationId === null) {
                continue;
            }

            try {
                SubmitImageGenerationJob::dispatch($generationId)
                    ->afterCommit();

                $dispatched++;
            } catch (Throwable $exception) {
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
