<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateConversationRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'type' => ['required', Rule::in(['dm', 'group'])],
            'user_id' => [
                'required_if:type,dm',
                'prohibited_if:type,group',
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::notIn($userId !== null ? [$userId] : []),
            ],
            'name' => ['required_if:type,group', 'nullable', 'string', 'max:60'],
            'member_ids' => ['sometimes', 'array', 'max:50'],
            'member_ids.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.not_in' => 'You cannot start a conversation with yourself.',
        ];
    }
}
