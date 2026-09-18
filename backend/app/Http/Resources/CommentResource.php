<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Posts\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
final class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => new UserResource($this->whenLoaded('user', $this->user)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
