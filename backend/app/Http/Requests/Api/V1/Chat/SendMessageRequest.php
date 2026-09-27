<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Support\Media\MediaUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['text', 'image', 'video', 'gif', 'drawing'])],
            'body' => ['required_without:media_url', 'nullable', 'string', 'max:2000'],
            'media_url' => [
                'nullable',
                'max:500',
                // http(s) for a provider GIF, or the `/storage/...` reference our
                // own upload endpoints just returned. `javascript:` and `data:`
                // never pass, so a message can never become a client-side
                // script or an inline payload source.
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! MediaUrl::isSafeReference($value)) {
                        $fail('The media URL must be a valid http(s) address.');
                    }
                },
            ],
            'client_id' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'media_url' => 'media URL',
        ];
    }
}
