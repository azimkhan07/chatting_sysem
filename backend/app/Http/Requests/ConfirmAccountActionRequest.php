<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Re-authentication for the irreversible Account Center actions.
 *
 * Deleting an account or logging out everywhere should not be one stolen
 * token away, so both demand the current password.
 */
final class ConfirmAccountActionRequest extends FormRequest
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
            'password' => ['required', 'string'],
        ];
    }
}
