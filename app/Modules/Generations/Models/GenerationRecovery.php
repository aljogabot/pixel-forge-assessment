<?php

namespace Modules\Generations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'generation_id',
    'attempts',
    'next_attempt_at',
    'last_error',
    'completed_at',
])]
final class GenerationRecovery extends Model
{
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
