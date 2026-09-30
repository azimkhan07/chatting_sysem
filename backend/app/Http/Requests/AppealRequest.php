<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filing an appeal for a suspended account.
 *
 * The caller is signed out - suspension revoked every token - so identity is
 * proven with the same identifier + password proof used by reactivation.
 */
final class AppealRequest extends FormRequest
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
            'message' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'identifier' => mb_strtolower(trim((string) $this->input('identifier'))),
        ]);
    }
}