<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Generations\Models\Generation;
use Modules\User\Models\User;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'prompt' => fake()->sentence(),
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'status' => 'pending',
            'estimated_charge_usd' => '0.060000000000',
        ];
    }
}
