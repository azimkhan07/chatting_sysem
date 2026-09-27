<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Posts\Models\PostMedia;
use App\Support\Media\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PostMedia */
final class PostMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'url' => MediaUrl::ofNullable($this->file_path),
            'mime' => $this->mime,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
        ];
    }
}
