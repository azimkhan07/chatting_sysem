<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Family\Enums\FamilyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changing an existing member's role.
 */
final class UpdateFamilyMemberRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::enum(FamilyRole::class)],
        ];
    }

    public function role(): FamilyRole
    {
        return FamilyRole::from((string) $this->validated('role'));
    }
}
