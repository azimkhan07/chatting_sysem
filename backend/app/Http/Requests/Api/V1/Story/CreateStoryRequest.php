<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Story;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class CreateStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|File>>
     */
    public function rules(): array
    {
        $accepted = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov'];

        return [
            'media' => ['required', 'file', File::types($accepted)->max(100 * 1024)],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'effects' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
