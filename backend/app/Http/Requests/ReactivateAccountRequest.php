<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reactivation of a self-deactivated account.
 *
 * Takes the identifier rather than relying on an existing token, because
 * deactivation revokes every token - the user is, by definition, signed out.
 */
final class ReactivateAccountRequest extends FormRequest
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
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'identifier' => mb_strtolower(trim((string) $this->input('identifier'))),
        ]);
    }
}
