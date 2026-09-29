<?php

declare(strict_types=1);

namespace App\Domain\Stories\Contracts;

use App\Domain\Auth\Models\User;
use App\Domain\Stories\Models\Story;

interface StoryRepository
{
    public function create(int $userId, array $attributes): Story;

    /**
     * Active (unexpired) stories grouped per user, newest first.
     *
     * `$viewerId` is null for a guest, in which case no block filter applies
     * because there is nobody to filter for.
     *
     * @return array<int, array{user: User, stories: list<Story>}>
     */
    public function activeGroupedFeed(?int $viewerId): array;

    public function destroy(Story $story): void;
}
