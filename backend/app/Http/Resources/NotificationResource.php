<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Social\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserNotification */
final class NotificationResource extends JsonResource
{
    /**
     * The `data` key that marks a notification as having been written by the
     * admin console rather than by a user. The console is a separate app and
     * writes this row directly, so the value is the contract between the two -
     * it is a constant on `AppNotificationWriter` in admin-backend/ and is
     * repeated here as a string because that class is not autoloadable from
     * this app.
     */
    private const CONSOLE_STAFF_KEY = 'console_staff';

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'data' => $this->data ?? [],
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // A console decision has no acting user. `actor_id` still holds the
            // staff id because the column is NOT NULL, and staff ids and user
            // ids are both small integers from 1 - so resolving that column as
            // a user would attach an unrelated real person's name and photo to a
            // support decision. The marker in `data` is what distinguishes the
            // two, and it is checked before the relation is touched, so a
            // console notification can never be attributed to a user.
            'actor' => $this->isConsoleAction()
                ? null
                : $this->whenLoaded('actor', fn (): ?array => $this->actor === null
                    ? null
                    : (new UserResource($this->actor))->resolve()),
            // Surfaced separately so the app can still say who acted, without
            // pretending a member of the public did it.
            'console_staff' => $this->isConsoleAction()
                ? ($this->data[self::CONSOLE_STAFF_KEY] ?? null)
                : null,
        ];
    }

    private function isConsoleAction(): bool
    {
        return is_array($this->data) && array_key_exists(self::CONSOLE_STAFF_KEY, $this->data);
    }
}
