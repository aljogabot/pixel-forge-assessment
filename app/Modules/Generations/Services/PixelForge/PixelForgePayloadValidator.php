<?php

namespace Modules\Generations\Services\PixelForge;

use Illuminate\Support\Facades\Validator;
use UnexpectedValueException;

final class PixelForgePayloadValidator
{
    /** @return array<string, mixed> */
    public function validateEvent(string $payload): array
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
        ];

        if (Validator::make($event, $rules)->fails()) {
            throw new UnexpectedValueException('Invalid webhook event.');
        }

        if ($event['type'] !== 'ping') {
            $generation = $this->validateGeneration(
                $event['data']['object'],
            );

            if ($event['type'] !== 'generation.' . $generation['status']) {
                throw new UnexpectedValueException(
                    'Webhook event type and generation status do not match.',
                );
            }
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $generation
     * @return array<string, mixed>
     */
    public function validateGeneration(array $generation): array
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

        return $generation;
    }
}
