<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Posts\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
final class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => new UserResource($this->whenLoaded('user', $this->user)),
            'media' => PostMediaResource::collection($this->relationLoaded('media') ? $this->media : collect()),
            'hashtags' => $this->relationLoaded('hashtags')
                ? $this->hashtags->pluck('name')->values()->all()
                : [],
            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'liked_by_me' => (bool) ($this->liked_by_me ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
