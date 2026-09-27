<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Enums\MessageReactionType;
use App\Domain\Chat\Models\ConversationMessage;
use App\Support\Media\MediaUrl;
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
                'avatar_url' => MediaUrl::ofNullable($this->user->avatar_path),
                'is_verified' => $this->user->is_verified,
            ] : null,
            'type' => $this->type->value,
            'body' => $this->body,
            'media_url' => $this->media_url,
            'read' => $this->readByOthers($viewerId),
            'read_by' => $this->readBy($viewerId),
            'reactions' => $this->reactionSummary(),
            'my_reaction' => $this->viewerReaction($viewerId),
            // Shared pin state: the whole conversation sees it, so unlike a
            // nickname this is not per-viewer.
            'pinned_at' => $this->pinned_at?->toIso8601String(),
            'pinned_by' => $this->pinned_by,
            'can_pin' => $this->canPin($viewerId),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function reactionSummary(): array
    {
        $defaults = [];
        foreach (MessageReactionType::cases() as $case) {
            $defaults[$case->value] = 0;
        }

        if (! $this->relationLoaded('reactions')) {
            return $defaults;
        }

        $counts = $this->reactions->map(fn ($reaction): string => $reaction->reaction->value)->countBy()->all();

        return array_merge($defaults, $counts);
    }

    private function viewerReaction(?int $viewerId): ?string
    {
        if ($viewerId === null || ! $this->relationLoaded('reactions')) {
            return null;
        }

        $mine = $this->reactions->firstWhere('user_id', $viewerId);

        return $mine === null ? null : $mine->reaction->value;
    }

    /**
     * Whether this viewer may pin *this* message. The client uses it to decide
     * between a plain pin action and a crown, but the pin route re-checks both
     * this and the subscription, so it is affordance, not enforcement.
     */
    private function canPin(?int $viewerId): bool
    {
        if ($viewerId === null) {
            return false;
        }

        if ((int) $this->user_id === $viewerId) {
            return true;
        }

        if (! $this->relationLoaded('conversation') || $this->conversation === null) {
            return false;
        }

        return $this->conversation->type === ConversationType::Group
            && $this->conversation->relationLoaded('members')
            && $this->conversation->members->contains(
                fn ($member): bool => (int) $member->user_id === $viewerId
                    && in_array($member->role->value, ['owner', 'admin'], true)
            );
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

    /**
     * Read receipt readers: every other participant whose watermark is at or
     * past this message. Powers Instagram-style avatar receipts in the UI.
     *
     * @return list<array{
     *     id: int,
     *     username: string,
     *     display_name: string,
     *     avatar_url: string|null,
     * }>
     */
    private function readBy(?int $viewerId): array
    {
        if (! $this->relationLoaded('conversation') || $this->conversation === null || $viewerId === null) {
            return [];
        }

        $readers = $this->conversation->relationLoaded('members')
            ? $this->conversation->members->filter(
                fn ($member): bool => $member->user_id !== $viewerId
                    && (int) $member->last_read_message_id >= (int) $this->id
                    && $member->relationLoaded('user')
                    && $member->user !== null
            )
            : collect();

        return $readers->values()
            ->map(fn ($member): array => [
                'id' => (int) $member->user->id,
                'username' => $member->user->username,
                'display_name' => $member->user->display_name,
                'avatar_url' => MediaUrl::ofNullable($member->user->avatar_path),
            ])
            ->all();
    }
}
