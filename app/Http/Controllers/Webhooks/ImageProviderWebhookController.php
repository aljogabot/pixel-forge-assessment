<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Modules\Generations\Actions\ProcessGenerationWebhook;
use UnexpectedValueException;

class ImageProviderWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        ProcessGenerationWebhook $processor
    ): Response {
        $payload = $request->getContent();

        $valid = $processor->verifyWebhookSignature(
            payload: $payload,
            headers: $request->headers->all()
        );

        abort_unless($valid, 400, 'Invalid signature.');

        try {
            $processor->handle($payload);
        } catch (UnexpectedValueException $exception) {
            abort(400, $exception->getMessage());
        }

        return response()->noContent();
    }
}
