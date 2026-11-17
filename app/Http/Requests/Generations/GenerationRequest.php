<?php

namespace Modules\Generations\Http\Requests\Generations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => [
                'required',
                'string',
                'in:1024x1024,1536x1024,1024x1536',
            ],
            'quality' => ['required', 'string', 'in:low,medium,high'],
            'reference_images' => ['sometimes', 'array', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https'],
        ];
    }
}
