<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Users;

use Illuminate\Foundation\Http\FormRequest;

final class MatchContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // A phone book is big; 500 keeps one request sane without being a
            // scraping surface, and nothing is stored server-side.
            'contacts' => ['required', 'array', 'min:1', 'max:500'],
            'contacts.*' => ['string', 'max:32', 'regex:/^[0-9+()\s-]{6,32}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'contacts.*.regex' => 'Each contact must be a phone number.',
        ];
    }
}
