<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'media' => ['sometimes', 'array', 'max:5'],
            'media.*' => ['file'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => trim((string) $this->input('body')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(static function (Validator $validator): void {
            $hasText = trim((string) ($validator->getData()['body'] ?? '')) !== '';
            $hasMedia = (array) ($validator->getData()['media'] ?? []);

            if (! $hasText && $hasMedia === []) {
                $validator->errors()->add(
                    'content',
                    'A post requires text, media, or both.',
                );
            }
        });
    }
}
