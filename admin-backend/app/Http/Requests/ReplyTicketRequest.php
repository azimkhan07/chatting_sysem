<?php

declare(strict_types=1);

namespace Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReplyTicketRequest extends FormRequest
{
    /**
     * @return array{reply: string[], email: string[]}
     */
    public function rules(): array
    {
        return [
            'reply' => ['required', 'string', 'max:4000'],
            'email' => ['sometimes', 'boolean'],
        ];
    }
}
