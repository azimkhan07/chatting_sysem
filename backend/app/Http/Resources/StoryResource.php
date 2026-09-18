<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Stories\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Story */
final class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'url' => asset('storage/'.$this->media_path),
            'caption' => $this->caption,
            'effects' => $this->effects,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
