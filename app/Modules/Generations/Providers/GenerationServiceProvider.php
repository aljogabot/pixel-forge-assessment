<?php

namespace Modules\Generations\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Modules\Generations\Contracts\GeneratePayloadValidator;
use Modules\Generations\Contracts\ImageGenerationProvider;
use Modules\Generations\Contracts\WebhookProviderProcessor;
use Modules\Generations\Services\ProviderRequestLimiter;

final class GenerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $providerName = config('services.generation_provider.default');

        $provider = config(
            "services.generation_provider.providers.{$providerName}",
        );

        $this->app->bind(
            GeneratePayloadValidator::class,
            $provider['payload_validator']['class'],
        );

        $this->app->bind(
            ImageGenerationProvider::class,
            fn(Application $app): ImageGenerationProvider => $app->make(
                $provider['client']['class'],
                [
                    ...$provider['client']['settings'],
                    'payloadValidator' => $app->make(
                        $provider['payload_validator']['class'],
                    ),
                ],
            ),
        );

        $this->app->bind(
            WebhookProviderProcessor::class,
            fn(Application $app): WebhookProviderProcessor => $app->make(
                $provider['webhook_processor']['class'],
                [
                    ...$provider['webhook_processor']['settings'],
                    'eventValidator' => $app->make(
                        $provider['payload_validator']['class'],
                    ),
                ],
            ),
        );


        $this->app->bind(
            ProviderRequestLimiter::class,
            fn() => new ProviderRequestLimiter(
                scope: $providerName,
                requestsPerMinute: (int) $provider['traffic']['requests_per_minute'],
            ),
        );
    }
}
