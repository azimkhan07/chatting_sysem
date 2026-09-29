<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Saved\Models\SavedItem;
use App\Domain\Stories\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SavedItem */
final class SavedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $saveable = $this->saveable;

        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'collections' => $this->when(
                $this->relationLoaded('collections'),
                fn (): array => $this->collections->pluck('id')->all(),
            ),
            'saveable_type' => $saveable instanceof Story ? 'story' : 'post',
            'saveable' => $saveable instanceof Story
                ? (new StoryResource($saveable))->resolve()
                : (new PostResource($saveable))->resolve(),
        ];
    }

    public static function collection($resource)
    {
        return parent::collection($resource);
    }
}
