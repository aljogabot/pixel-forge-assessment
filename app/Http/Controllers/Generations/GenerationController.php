<?php

namespace App\Http\Controllers\Generations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Billing\Actions\GetImageGenerationPriceEstimate;
use Modules\Generations\Contracts\ImageGenerationProvider;
use Modules\Generations\Http\Requests\Generations\GenerationRequest;
use Illuminate\Http\RedirectResponse;
use Modules\Generations\Actions\SubmitImageGeneration;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

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
        try {
            $submit->handle(
                user: $request->user(),
                generationInput: $request->validated(),
            );
        } catch (
            ConnectionException
            | RequestException
            | UnexpectedValueException $exception
        ) {
            report($exception);

            throw ValidationException::withMessages([
                'submission' =>
                'Unable to submit your generation because provider pricing '
                    . 'is unavailable. Please try again later.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Your image generation request was submitted successfully.',
        ]);

        return to_route('dashboard');
    }


    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'generations' => $request->user()
                ->generations()
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('generations/Create');
    }
}
