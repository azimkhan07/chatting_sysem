<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'avatar_url' => $this->avatar_path !== null ? asset('storage/'.$this->avatar_path) : null,
            'cover_url' => $this->cover_path !== null ? asset('storage/'.$this->cover_path) : null,
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
