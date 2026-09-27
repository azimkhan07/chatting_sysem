<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Auth\Models\User;
use App\Support\Media\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->getAuthIdentifier();
        $isSelf = $viewerId !== null && (int) $viewerId === (int) $this->id;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            // Contact details are only ever visible to the account owner.
            'email' => $this->when($isSelf, $this->email),
            'mobile' => $this->when($isSelf, $this->mobile),
            'avatar_url' => MediaUrl::ofNullable($this->avatar_path),
            'cover_url' => MediaUrl::ofNullable($this->cover_path),
            'is_verified' => $this->is_verified,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'posts_count' => $this->whenCounted('posts', fn (): int => (int) $this->posts_count, 0),
            'followers_count' => $this->whenCounted('followers', fn (): int => (int) $this->followers_count, 0),
            'following_count' => $this->whenCounted('following', fn (): int => (int) $this->following_count, 0),
            'is_followed_by_me' => (bool) ($this->is_followed_by_me ?? false),
        ];
    }
}
