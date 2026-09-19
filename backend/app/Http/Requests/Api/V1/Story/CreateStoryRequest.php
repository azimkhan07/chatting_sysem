<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Story;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;

final class CreateStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|File|Exists>>
     */
    public function rules(): array
    {
        $accepted = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov'];

        return [
            'media' => ['required_without:media_url', 'file', File::types($accepted)->max(100 * 1024)],
            'media_url' => ['required_without:media', 'nullable', 'url', 'max:2048'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'effects' => ['sometimes', 'nullable', 'string', 'max:32'],
            'song_id' => ['sometimes', 'nullable', 'integer', new Exists('songs', 'id')],
            'text_style' => ['sometimes', 'nullable', 'array'],
            'text_style.font' => ['sometimes', 'string', 'in:sm,md,lg,xl,2xl'],
            'text_style.color' => ['sometimes', 'string', 'in:white,black,yellow,red,green,blue,pink,orange,purple'],
            'text_style.align' => ['sometimes', 'string', 'in:left,center,right'],
            'text_style.bg' => ['sometimes', 'string', 'in:none,solid,gradient'],
            'text_style.pos' => ['sometimes', 'string', 'in:top,middle,bottom'],
        ];
    }
}
