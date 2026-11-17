<?php

namespace Modules\Generations\Services;

use Illuminate\Support\Facades\Validator;
use UnexpectedValueException;

final class PixelForgeWebhookEventValidator
{
    /** @return array<string, mixed> */
    public function validate(string $payload): array
    {
        $event = json_decode($payload, true, 512);

        if (! is_array($event)) {
            throw new UnexpectedValueException('Invalid webhook JSON.');
        }

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
            'data.object.id' => [
                'required_unless:type,ping',
                'string',
                'max:255',
            ],
            'data.object.status' => [
                'required_unless:type,ping',
                'in:processing,succeeded,failed',
            ],
            'data.object.completed_at' => [
                'required_if:type,generation.failed,generation.succeeded',
                'nullable',
                'date',
            ],
            'data.object.error' => [
                'required_if:type,generation.failed',
                'nullable',
                'array',
            ],
            'data.object.error.code' => [
                'required_if:type,generation.failed',
                'string',
                'max:255',
            ],
            'data.object.error.message' => [
                'required_if:type,generation.failed',
                'string',
            ],
        ];

        if (($event['type'] ?? null) === 'generation.succeeded') {
            $rules['data.object.image_url'] = [
                'required',
                'url:http,https',
            ];

            $rules['data.object.image_url_expires_at'] = [
                'required',
                'date',
            ];

            $rules['data.object.usage'] = ['required', 'array'];
            $rules['data.object.rates'] = ['required', 'array'];

            foreach (
                [
                    'input_text_tokens',
                    'input_image_tokens',
                    'output_tokens',
                    'reasoning_tokens',
                ] as $category
            ) {
                $rules["data.object.usage.$category"] = [
                    'required',
                    'integer',
                    'min:0',
                ];

                $rules["data.object.rates.$category"] = [
                    'required',
                    'string',
                    'regex:/\A[0-9]+(?:\.[0-9]+)?\z/',
                ];
            }
        }

        if (Validator::make($event, $rules)->fails()) {
            throw new UnexpectedValueException('Invalid webhook event.');
        }

        return $event;
    }
}
