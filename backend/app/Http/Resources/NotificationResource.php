<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Social\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserNotification */
final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'data' => $this->data ?? [],
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // The actor can be a console staff id (subscription approvals),
            // which has no row in the app users table — surface null instead of
            // a crash or a ghost account.
            'actor' => $this->whenLoaded('actor', fn (): ?array => $this->actor === null
                ? null
                : (new UserResource($this->actor))->resolve()),
        ];
    }
}
