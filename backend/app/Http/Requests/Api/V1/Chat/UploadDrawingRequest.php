<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use Illuminate\Foundation\Http\FormRequest;

final class UploadDrawingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The `image` rule is a client-side courtesy; the real gate is the byte
     * sniffing in ChatDrawingProcessor.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'drawing' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:4096'],
        ];
    }
}
