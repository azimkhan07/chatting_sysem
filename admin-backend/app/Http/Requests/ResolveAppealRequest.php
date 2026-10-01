<?php

declare(strict_types=1);

namespace Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveAppealRequest extends FormRequest
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
            'action' => ['required', 'string', 'in:approve,reject'],
            'resolution' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
