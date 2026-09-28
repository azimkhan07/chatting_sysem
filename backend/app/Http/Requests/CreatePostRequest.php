<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'song_id' => ['sometimes', 'nullable', 'integer', Rule::exists('songs', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = trim((string) $this->input('body'));
        $location = $this->input('location');

        $this->merge([
            'body' => $body,
            // A location of "   " is not a location, and an empty string would
            // otherwise be stored as one and rendered as a blank place line.
            'location' => is_string($location) && trim($location) !== '' ? trim($location) : null,
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
