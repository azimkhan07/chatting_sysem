<?php

declare(strict_types=1);

namespace Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveEmailConfigRequest extends FormRequest
{
    /**
     * @return array{host: string[], port: string[], username: string[], password: string[], encryption: string[], from_email: string[], from_name: string[], enabled: string[]}
     */
    public function rules(): array
    {
        return [
            'host' => ['nullable', 'string', 'max:160'],
            'port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:160'],
            'password' => ['nullable', 'string'],
            'encryption' => ['sometimes', 'string', 'in:none,tls,ssl'],
            'from_email' => ['nullable', 'email', 'max:160'],
            'from_name' => ['nullable', 'string', 'max:80'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
