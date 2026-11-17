<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Modules\Generations\Contracts\GenerationWebhookVerifier;

class PixelForgeWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        GenerationWebhookVerifier $verifier,
    ): Response {
        $valid = $verifier->verifyWebhookSignature(
            payload: $request->getContent(),
            header: (string) $request->header('Pixelforge-Signature'),
        );

        abort_unless($valid, 400, 'Invalid Pixelforge signature.');

        // Event persistence and processing come next.
        abort(503, 'Webhook processing is not configured yet.');
    }
}
