<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Threads;

use Illuminate\Foundation\Http\FormRequest;

final class AddThreadEntryRequest extends FormRequest
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
            'body' => ['required_without:media', 'nullable', 'string', 'max:1000'],
            'media' => ['required_without:body', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ];
    }
}
