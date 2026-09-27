<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Threads\Enums\ThreadReactionType;
use App\Domain\Threads\Models\ThreadEntry;
use App\Domain\Threads\Models\ThreadReaction;
use App\Support\Media\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThreadEntry
 */
final class ThreadEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reactions = $this->relationLoaded('reactions')
            ? $this->reactions
                ->map(static fn (ThreadReaction $reaction): string => $reaction->reaction->value)
                ->countBy()
                ->all()
            : [];

        $totals = [];
        foreach (ThreadReactionType::cases() as $case) {
            $totals[$case->value] = $reactions[$case->value] ?? 0;
        }

        $myReaction = $request->user() !== null && $this->relationLoaded('reactions')
            ? $this->reactions->firstWhere('user_id', $request->user()->id)?->reaction?->value
            : null;

        return [
            'id' => $this->id,
            'thread_id' => $this->thread_id,
            'user' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'username' => $this->author->username,
                'display_name' => $this->author->display_name,
                'avatar_url' => MediaUrl::ofNullable($this->author->avatar_path),
            ]),
            'body' => $this->body,
            'media_url' => MediaUrl::ofNullable($this->media_path),
            'media_type' => $this->media_type,
            'reactions' => $totals,
            'my_reaction' => $myReaction,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
