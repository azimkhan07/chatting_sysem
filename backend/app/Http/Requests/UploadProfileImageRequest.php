<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UploadProfileImageRequest extends FormRequest
{
    /**
     * The largest edge we accept. Anything bigger is a decompression-bomb risk and
     * is never a real profile picture, so it is rejected before it is stored.
     */
    private const MAX_EDGE = 6000;

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
            'image' => [
                'required',
                'file',
                'mimes:jpeg,png,webp',
                'dimensions:min_width=64,min_height=64,max_width='.self::MAX_EDGE.',max_height='.self::MAX_EDGE,
                'max:5120',
            ],
        ];
    }
}
