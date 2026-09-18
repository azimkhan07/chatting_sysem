<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Chat\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConversationMessage */
final class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => $this->relationLoaded('user') && $this->user !== null ? [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'display_name' => $this->user->display_name,
                'avatar_url' => $this->user->avatar_path !== null ? asset('storage/'.$this->user->avatar_path) : null,
                'is_verified' => $this->user->is_verified,
            ] : null,
            'type' => $this->type->value,
            'body' => $this->body,
            'media_url' => $this->media_url,
            'read' => $this->readByOthers($viewerId),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Read receipt: every other participant has a watermark at or past this
     * message. Pure DB watermark math - no per-message scan, no client sums.
     */
    private function readByOthers(?int $viewerId): bool
    {
        if (! $this->relationLoaded('conversation') || $this->conversation === null || $viewerId === null) {
            return false;
        }

        $others = $this->conversation->relationLoaded('members')
            ? $this->conversation->members->filter(fn ($member): bool => $member->user_id !== $viewerId)
            : collect();

        if ($others->isEmpty()) {
            return true;
        }

        return $others->every(fn ($member): bool => (int) $member->last_read_message_id >= (int) $this->id);
    }
}
