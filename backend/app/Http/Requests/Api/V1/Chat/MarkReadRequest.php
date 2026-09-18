<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use Illuminate\Foundation\Http\FormRequest;

final class MarkReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'up_to_message_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
