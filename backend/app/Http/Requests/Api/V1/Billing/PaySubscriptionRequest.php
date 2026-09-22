<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;

final class PaySubscriptionRequest extends FormRequest
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
        return [
            'gateway' => ['required', 'string', 'max:40'],
            'token' => ['required', 'string', 'max:160'],
        ];
    }
}
