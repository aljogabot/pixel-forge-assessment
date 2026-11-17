<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Modules\Generations\Contracts\GeneratePayloadValidator;
use Modules\Generations\Contracts\ImageGenerationProvider;
use Modules\Generations\Contracts\WebhookProviderProcessor;
use Modules\Generations\Services\ApplyImageGenerationResult;
use Modules\Generations\Services\PixelForge\PixelForgeClient;
use Modules\Generations\Services\PixelForge\PixelForgePayloadValidator;
use Modules\Generations\Services\PixelForge\PixelWebhookProcessor;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind(
            PixelForgeClient::class,
            fn(Application $app) => new PixelForgeClient(
                baseUrl: config('services.generation_provider.pixel_forge.base_url'),
                apiKey: config('services.generation_provider.pixel_forge.api_key') ?? '',
                payloadValidator: $app->make(PixelForgePayloadValidator::class)
            ),
        );

        $this->app->bind(
            PixelWebhookProcessor::class,
            fn(Application $app) => new PixelWebhookProcessor(
                webhookSecret: config(
                    'services.generation_provider.pixel_forge.webhook_secret',
                ) ?? '',
                eventValidator: $app->make(PixelForgePayloadValidator::class),
                applyResult: $app->make(ApplyImageGenerationResult::class),
            )
        );

        $this->app->bind(WebhookProviderProcessor::class, PixelWebhookProcessor::class);
        $this->app->bind(ImageGenerationProvider::class, PixelForgeClient::class);
        $this->app->bind(GeneratePayloadValidator::class, PixelForgePayloadValidator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
                : null,
        );
    }
}
