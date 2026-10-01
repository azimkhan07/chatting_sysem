<?php

declare(strict_types=1);

namespace Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveGatewayRequest extends FormRequest
{
    /**
     * @return array{name: string[], key: string[], merchant_id: string[], secret: string[], endpoint: string[], currency: string[], enabled: string[]}
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'key' => ['nullable', 'string', 'max:200'],
            'merchant_id' => ['nullable', 'string', 'max:120'],
            'secret' => ['nullable', 'string'],
            'endpoint' => ['nullable', 'url', 'max:255'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
