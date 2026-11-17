<?php

namespace App\Http\Controllers\Generations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Billing\Actions\GenerationEstimate;
use Modules\Generations\Contracts\ImageGenerationProvider;
use Modules\Generations\Http\Requests\Generations\GenerationRequest;

class GenerationController extends Controller
{
    //
    public function estimate(
        GenerationRequest $request,
        ImageGenerationProvider $provider,
        GenerationEstimate $estimate,
    ): JsonResponse {
        $data = $request->validated();

        $charge = $estimate->handle(
            prompt: $data['prompt'],
            size: $data['size'],
            quality: $data['quality'],
            referenceImageCount: count($data['reference_images'] ?? []),
            pricing: $provider->pricing(),
        );

        return response()->json([
            'estimated_charge_usd' => $charge,
            'currency' => 'USD',
        ]);
    }
}
