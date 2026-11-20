<?php

use Modules\Generations\Services\PixelForge\PixelForgeClient;
use Modules\Generations\Services\PixelForge\PixelForgePayloadValidator;
use Modules\Generations\Services\PixelForge\PixelWebhookProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    'generation_provider' => [
        'default' => env('GENERATION_PROVIDER', 'pixel_forge'),

        'providers' => [
            'pixel_forge' => [
                'client' => [
                    'class' => PixelForgeClient::class,
                    'settings' => [
                        'baseUrl' => env(
                            'PIXELFORGE_BASE_URL',
                            'https://pixelforge.promptedit.dev/v1',
                        ),
                        'apiKey' => env('PIXELFORGE_API_KEY'),
                    ]
                ],
                'webhook_processor' => [
                    'class' => PixelWebhookProcessor::class,
                    'settings' => [
                        'webhookSecret' => env('PIXELFORGE_WEBHOOK_SECRET'),
                    ]
                ],

                'payload_validator' => [
                    'class' => PixelForgePayloadValidator::class,
                ],

                'traffic' => [
                    // created a headroom of 50 vs 60 on pixel's documentation ...
                    'requests_per_minute' => 50,
                    'max_active_generations' => 3,
                ],
            ],
        ],
    ],

];


class GenerationProvider
{
    public static function provide(string $provider, string $class)
    {
        $provider = config('services.generation_provider.providers.' . $provider . '.' . $class);
        return app()->make($provider['class'], $provider['settings']);
    }
}


$pixelForgeClient = GenerationProvider::provideClient('pixel_forge', 'client');
