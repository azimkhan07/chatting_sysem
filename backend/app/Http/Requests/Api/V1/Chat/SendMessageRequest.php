<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

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
            'type' => ['sometimes', Rule::in(['text', 'image', 'video'])],
            'body' => ['required_without:media_url', 'nullable', 'string', 'max:2000'],
            'media_url' => ['nullable', 'url', 'max:500'],
            'client_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
