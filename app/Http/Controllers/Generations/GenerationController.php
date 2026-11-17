<?php

namespace App\Http\Controllers\Generations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Billing\Actions\GetImageGenerationPriceEstimate;
use Modules\Generations\Contracts\ImageGenerationProvider;
use Modules\Generations\Http\Requests\Generations\GenerationRequest;
use Illuminate\Http\RedirectResponse;
use Modules\Generations\Actions\SubmitImageGeneration;

class GenerationController extends Controller
{
    //
    public function estimate(
        GenerationRequest $request,
        ImageGenerationProvider $provider,
        GetImageGenerationPriceEstimate $estimate,
    ): JsonResponse {
        $data = $request->validated();

        $charge = $estimate->handle(
            generationInput: $data,
            pricing: $provider->pricing(),
        );

        return response()->json([
            'estimated_charge_usd' => $charge,
            'currency' => 'USD',
        ]);
    }

    public function store(
        GenerationRequest $request,
        SubmitImageGeneration $submit,
    ): RedirectResponse {
        $submit->handle(
            user: $request->user(),
            generationInput: $request->validated(),
        );

        return to_route('dashboard');
    }
}
