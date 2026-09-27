<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Domain\Chat\Enums\ChatWallpaper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update of the viewer's own conversation personalisation. Both fields
 * must be present so "clear the wallpaper" and "set the wallpaper" are
 * distinguishable, which is why a bare `{}` is rejected.
 */
final class PersonalizeConversationRequest extends FormRequest
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
            'nickname' => ['present', 'nullable', 'string', 'max:40'],
            'wallpaper_key' => [
                'present',
                'nullable',
                'string',
                'max:60',
                Rule::in(ChatWallpaper::keys()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'wallpaper_key.in' => 'Pick one of the built-in wallpapers.',
        ];
    }
}
