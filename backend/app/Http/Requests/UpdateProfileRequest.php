<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Auth\Enums\AccountType;
use App\Domain\Auth\Enums\ProfileCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
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
            'display_name' => ['sometimes', 'string', 'min:2', 'max:60'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:160'],
            'account_type' => ['sometimes', Rule::enum(AccountType::class)],
            'is_private' => ['sometimes', 'boolean'],
            // Creator/business category - only meaningful on professional and
            // business accounts, so a personal account cannot carry one.
            'category' => ['sometimes', 'nullable', Rule::enum(ProfileCategory::class)],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:120'],
            // Stored digits only; the UI adds the + and separators.
            'contact_phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'show_contact' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Enter a phone number with 7 to 15 digits, e.g. +919876543210.',
        ];
    }
}
