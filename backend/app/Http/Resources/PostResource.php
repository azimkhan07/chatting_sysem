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
            // Only present when a caller explicitly eager loads them: feed
            // queries skip the relation because it costs a query per page and
            // no client reads it, so returning a fake empty list would lie.
            'hashtags' => $this->whenLoaded(
                'hashtags',
                fn (): array => $this->hashtags->pluck('name')->values()->all(),
            ),
            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'shares_count' => (int) ($this->shares_count ?? 0),
            'liked_by_me' => (bool) ($this->liked_by_me ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
