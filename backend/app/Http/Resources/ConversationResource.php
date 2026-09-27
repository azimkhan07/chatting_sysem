<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Chat\Contracts\PresenceService;
use App\Domain\Chat\Enums\ConversationState;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Models\Conversation;
use App\Support\Media\MediaUrl;
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
        $online = $this->onlineMemberIds($viewerId === null ? null : (int) $viewerId);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'state' => $this->state->value,
            // Only the recipient gets the decision buttons, so the sender's
            // copy of a pending request is inert.
            'is_request_actionable' => $this->state === ConversationState::Requested
                && $this->requested_by !== null
                && $this->requested_by !== $viewerId,
            'display_name' => $isDm && $peer !== null && $peer->user !== null
                ? $peer->user->display_name
                : ($isDm ? 'Chat' : $this->name),
            'avatar_url' => $isDm && $peer !== null && $peer->user !== null
                ? MediaUrl::ofNullable($peer->user->avatar_path)
                : null,
            'peer_verified' => $isDm && $peer !== null && $peer->user !== null ? (bool) $peer->user->is_verified : null,
            'members_count' => $members->count(),
            'members' => $members->map(fn ($member): array => [
                'user' => $member->relationLoaded('user') && $member->user !== null ? [
                    'id' => $member->user->id,
                    'username' => $member->user->username,
                    'display_name' => $member->user->display_name,
                    'avatar_url' => MediaUrl::ofNullable($member->user->avatar_path),
                    'is_online' => in_array((int) $member->user->id, $online, true),
                ] : null,
                'role' => $member->role->value,
            ])->values(),
            'online_count' => count($online),
            'peer_presence' => $isDm && $peer !== null && $peer->user !== null ? [
                'user_id' => (int) $peer->user->id,
                'is_online' => in_array((int) $peer->user->id, $online, true),
                'last_seen_at' => $peer->user->last_seen_at?->toIso8601String(),
            ] : null,
            'last_message' => $this->relationLoaded('lastMessage') && $this->lastMessage !== null
                ? (new MessageResource($this->lastMessage))->resolve($request)
                : null,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'muted' => (bool) ($myMember !== null ? $myMember->muted : false),
            // Personalisation is per-viewer, so only the viewer's own copy is
            // ever returned — a nickname is private to the person who set it.
            'my_nickname' => $myMember !== null ? $myMember->nickname : null,
            'my_wallpaper_key' => $myMember !== null ? $myMember->wallpaper_key : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Online member ids, never including the viewer. The presence store
     * memoises the online set per request, so an inbox of 100+ conversations
     * still costs a single presence read.
     *
     * @return list<int>
     */
    private function onlineMemberIds(?int $viewerId): array
    {
        $users = [];
        foreach ($this->relationLoaded('members') ? $this->members : [] as $member) {
            if ($member->user !== null) {
                $users[(int) $member->user->id] = $member->user;
            }
        }

        if ($users === []) {
            return [];
        }

        /** @var array<int, bool> $map */
        $map = app(PresenceService::class)->onlineMap($users);

        $online = [];
        foreach ($map as $userId => $isOnline) {
            if ($isOnline && $userId !== $viewerId) {
                $online[] = $userId;
            }
        }

        return $online;
    }
}
