<?php

declare(strict_types=1);

namespace Admin\Http\Resources;

use Admin\Domain\App\Enums\UserStatus;
use Admin\Domain\App\Models\User;
use Admin\Support\Media\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as the console lists them.
 *
 * Deliberately not the app's UserResource. That one is shaped for a signed-in
 * user looking at a profile: it decides whether to expose contact details,
 * counts posts and follows, and reflects `is_followed_by_me`. None of that
 * applies here, where the reader is staff answering "which account is this".
 *
 * `email` is included because the console genuinely needs it — it is a column
 * in the user list and half of what the search box matches on. `mobile` is not:
 * no console screen shows a phone number, and the fewer identity columns a
 * read endpoint hands out, the less damage a leaked console session does.
 *
 * @mixin User
 */
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
            'country' => $this->country,
            'avatar_url' => MediaUrl::ofNullable($this->avatar_path),
            'is_verified' => (bool) $this->is_verified,
            'status' => $this->status instanceof UserStatus ? $this->status->value : $this->status,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
