<?php

namespace Modules\Generations\Models;

use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(GenerationFactory::class)]
class Generation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reference_images' => 'array',
            'usage' => 'array',
            'rates' => 'array',
            'estimated_charge_usd' => 'decimal:12',
            'provider_cost_usd' => 'decimal:12',
            'actual_charge_usd' => 'decimal:12',
            'submitted_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'image_url_expires_at' => 'immutable_datetime',
        ];
    }
}
