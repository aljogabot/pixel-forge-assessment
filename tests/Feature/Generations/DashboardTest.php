<?php

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Generations\Models\Generation;
use Modules\User\Models\User;

test('shows only the signed-in users generations in bounded pages', function () {
    $user = User::factory()->create();

    $ownedGenerations = Generation::factory()
        ->for($user)
        ->count(21)
        ->create();

    // This newer generation belongs to someone else.
    Generation::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('Dashboard')
                ->has('generations.data', 20)
                ->where('generations.total', 21)
                ->where(
                    'generations.data.0.id',
                    $ownedGenerations->last()->getKey(),
                )
                ->where(
                    'generations.data.19.id',
                    $ownedGenerations[1]->getKey(),
                )
        );

    $this->actingAs($user)
        ->get(route('dashboard', ['page' => 2]))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->has('generations.data', 1)
                ->where('generations.total', 21)
                ->where(
                    'generations.data.0.id',
                    $ownedGenerations->first()->getKey(),
                )
        );
});
