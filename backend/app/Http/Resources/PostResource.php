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
            // Where it happened, as the name the author typed. Null when they
            // did not say, and the client renders nothing rather than a pin.
            'location' => $this->location,
            'song' => $this->relationLoaded('song') && $this->song !== null
                ? (new SongResource($this->song))->resolve()
                : null,
            // Only on the post the author just made. A feed page would cost a
            // query per post to load a line most posts will not show.
            'tagged_users' => $this->whenLoaded(
                'mentions',
                fn (): array => UserResource::collection($this->mentions)->resolve(),
            ),
            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'shares_count' => (int) ($this->shares_count ?? 0),
            'liked_by_me' => (bool) ($this->liked_by_me ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
