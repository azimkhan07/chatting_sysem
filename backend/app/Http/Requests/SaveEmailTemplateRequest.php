<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveEmailTemplateRequest extends FormRequest
{
    /**
     * @return array{title: string[], subject: string[], html_body: string[], text_body: string[], enabled: string[]}
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:180'],
            'html_body' => ['required', 'string'],
            'text_body' => ['nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}