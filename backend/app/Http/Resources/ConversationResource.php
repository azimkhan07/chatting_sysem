<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
final class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;
        $members = $this->relationLoaded('members') ? $this->members : collect();
        $myMember = $members->firstWhere('user_id', $viewerId);
        $peer = $members->first(fn ($member): bool => $member->user_id !== $viewerId);
        $isDm = $this->type === ConversationType::Dm;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'display_name' => $isDm ? ($peer?->user?->display_name ?? 'Chat') : $this->name,
            'avatar_url' => $isDm && $peer?->user?->avatar_path !== null
                ? asset('storage/'.$peer->user->avatar_path)
                : null,
            'peer_verified' => $isDm ? (bool) ($peer?->user?->is_verified ?? false) : null,
            'members_count' => $members->count(),
            'members' => $members->map(fn ($member): array => [
                'user' => $member->relationLoaded('user') && $member->user !== null ? [
                    'id' => $member->user->id,
                    'username' => $member->user->username,
                    'display_name' => $member->user->display_name,
                    'avatar_url' => $member->user->avatar_path !== null ? asset('storage/'.$member->user->avatar_path) : null,
                ] : null,
                'role' => $member->role->value,
            ])->values(),
            'last_message' => $this->relationLoaded('lastMessage') && $this->lastMessage !== null
                ? (new MessageResource($this->lastMessage))->resolve($request)
                : null,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'muted' => (bool) ($myMember?->muted ?? false),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
