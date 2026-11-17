<?php

namespace Modules\Generations\Services\PixelForge;

use Illuminate\Support\Facades\Validator;
use Modules\Generations\Contracts\GeneratePayloadValidator;
use Modules\Generations\Contracts\WebhookValidatorContract;
use UnexpectedValueException;

final class PixelForgePayloadValidator implements WebhookValidatorContract, GeneratePayloadValidator
{
    /**
     * Validate pricing data returned by Pixelforge.
     *
     * @param array<string, mixed> $pricing
     */
    public function validatePricing(array $pricing): void
    {
        $rules = [
            'rates' => ['required', 'array'],
            'tokens' => ['required', 'array'],
            'tokens.characters_per_text_token' => ['required', 'integer', 'min:1'],
            'tokens.per_reference_image' => ['required', 'integer', 'min:0'],
            // Without these, we could accept rates expressed in an unexpected currency or unit.
            'currency' => ['required', 'in:usd'],
            'unit' => ['required', 'in:per_1m_tokens'],
        ];

        foreach (
            [
                'input_text_tokens',
                'input_image_tokens',
                'output_tokens',
                'reasoning_tokens',
            ] as $category
        ) {
            $rules["rates.$category"] = [
                'required',
                'string',
                'regex:/\A[0-9]+(?:\.[0-9]+)?\z/',
            ];
        }

        foreach (['low', 'medium', 'high'] as $quality) {
            foreach (['1024x1024', '1536x1024', '1024x1536'] as $size) {
                $rules["tokens.output.$quality.$size"] = [
                    'required',
                    'integer',
                    'min:0',
                ];
            }

            $rules["tokens.reasoning.$quality.min"] = [
                'required',
                'integer',
                'min:0',
            ];

            $rules["tokens.reasoning.$quality.max"] = [
                'required',
                'integer',
                'min:0',
                "gte:tokens.reasoning.$quality.min",
            ];
        }

        if (Validator::make($pricing, $rules)->fails()) {
            throw new UnexpectedValueException(
                'Pixelforge returned invalid pricing rates or token rules.',
            );
        }
    }

    /** @param array<string, mixed> $event */
    public function validateEvent(array $event): void
    {
        $rules = [
            'id' => ['required', 'string', 'max:255'],
            'object' => ['required', 'in:event'],
            'type' => [
                'required',
                'in:ping,generation.processing,generation.succeeded,generation.failed',
            ],
            'created_at' => ['required', 'date'],
            'data' => ['required_unless:type,ping', 'array'],
            'data.object' => ['required_unless:type,ping', 'array'],
        ];

        if (Validator::make($event, $rules)->fails()) {
            throw new UnexpectedValueException('Invalid webhook event.');
        }

        if ($event['type'] !== 'ping') {
            $generation = $event['data']['object'];
            $this->validateGeneration($generation);

            if ($event['type'] !== 'generation.' . $generation['status']) {
                throw new UnexpectedValueException(
                    'Webhook event type and generation status do not match.',
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $generation
     */
    public function validateGeneration(array $generation): void
    {
        $rules = [
            'id' => ['required', 'string', 'max:255'],
            'status' => [
                'required',
                'in:queued,processing,succeeded,failed',
            ],
            'completed_at' => [
                'required_if:status,succeeded,failed',
                'nullable',
                'date',
            ],
            'metadata' => ['sometimes', 'array'],
            'metadata.local_generation_id' => [
                'sometimes',
                'string',
                'regex:/\A[1-9][0-9]*\z/',
            ],
        ];

        if (($generation['status'] ?? null) === 'failed') {
            $rules['error'] = ['required', 'array'];
            $rules['error.code'] = ['required', 'string', 'max:255'];
            $rules['error.message'] = ['required', 'string'];
        }

        if (($generation['status'] ?? null) === 'succeeded') {
            $rules['image_url'] = ['required', 'url:http,https'];
            $rules['image_url_expires_at'] = ['required', 'date'];
            $rules['usage'] = ['required', 'array'];
            $rules['rates'] = ['required', 'array'];

            foreach (
                [
                    'input_text_tokens',
                    'input_image_tokens',
                    'output_tokens',
                    'reasoning_tokens',
                ] as $category
            ) {
                $rules["usage.$category"] = [
                    'required',
                    'integer',
                    'min:0',
                ];

                $rules["rates.$category"] = [
                    'required',
                    'string',
                    'regex:/\A[0-9]+(?:\.[0-9]+)?\z/',
                ];
            }
        }

        if (Validator::make($generation, $rules)->fails()) {
            throw new UnexpectedValueException(
                'Invalid provider generation response.',
            );
        }
    }
}
