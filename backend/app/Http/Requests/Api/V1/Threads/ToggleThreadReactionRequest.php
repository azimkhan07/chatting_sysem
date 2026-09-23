<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Threads;

use App\Domain\Threads\Enums\ThreadReactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class ToggleThreadReactionRequest extends FormRequest
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
            'reaction' => ['required', new Enum(ThreadReactionType::class)],
        ];
    }
}
