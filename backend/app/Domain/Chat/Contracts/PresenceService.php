<?php

declare(strict_types=1);

namespace App\Domain\Chat\Contracts;

use App\Domain\Auth\Models\User;

interface PresenceService
{
    /**
     * Record activity for an authenticated user, throttled so a burst of API
     * calls costs a single presence write. Safe to call on every request.
     */
    public function touch(User $user): void;

    /**
     * Explicit client heartbeat. Always records (never throttled away) so an
     * idle-but-open client keeps its dot lit.
     *
     * @return array{is_online: bool, last_seen_at: ?string}
     */
    public function heartbeat(User $user): array;

    /**
     * Mark a user as gone right now (logout).
     */
    public function signOut(User $user): void;

    public function isOnline(User $user): bool;

    /**
     * Batch presence for a set of already-loaded users.
     *
     * @param  array<int, User>  $users
     * @return array<int, bool>
     */
    public function onlineMap(array $users): array;

    /**
     * Drop users who aged out of the window, pin their `last_seen_at` to the
     * last moment we actually saw them and broadcast the offline transition.
     *
     * @return int number of users expired
     */
    public function sweep(?int $limit = null): int;
}
