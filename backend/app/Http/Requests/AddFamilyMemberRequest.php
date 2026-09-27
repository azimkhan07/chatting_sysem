<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Family\Enums\FamilyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding someone to the family by username.
 *
 * The role is validated against the enum rather than a hand-written list, so a
 * new role cannot exist in the domain and be rejected here.
 */
final class AddFamilyMemberRequest extends FormRequest
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
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_.]+$/'],
            'role' => ['required', 'string', Rule::enum(FamilyRole::class)],
        ];
    }

    public function role(): FamilyRole
    {
        return FamilyRole::from((string) $this->validated('role'));
    }
}
