<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
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
            // Uniqueness of username is a DOMAIN decision (Action throws
            // UsernameTakenException → mapped to HTTP 409 per API contract).
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9._]+$/',
            ],
            'display_name' => ['required', 'string', 'min:2', 'max:60'],
            // NOTE: deliberately NOT unique — a public can own several accounts
            // with one email/mobile to engage freely (uniqueness enforced in v3+).
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+][0-9\-\s]*$/'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => mb_strtolower(trim((string) $this->input('username'))),
            'display_name' => trim((string) $this->input('display_name')),
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
            'mobile' => $this->filled('mobile') ? trim((string) $this->input('mobile')) : null,
        ]);
    }
}
