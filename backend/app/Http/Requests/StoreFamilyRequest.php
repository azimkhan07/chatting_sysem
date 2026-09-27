<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating a family.
 *
 * The name is the only input; there is no invite list here. Members are added
 * afterwards, by username, so a half-typed roster is never half-created.
 */
final class StoreFamilyRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ];
    }
}
