<?php

declare(strict_types=1);

namespace App\Domain\Stories\Repositories;

use App\Domain\Auth\Models\User;
use App\Domain\Stories\Contracts\StoryRepository;
use App\Domain\Stories\Models\Story;
use Illuminate\Support\Collection;

final class EloquentStoryRepository implements StoryRepository
{
    public function create(int $userId, array $attributes): Story
    {
        return Story::query()->create([
            'user_id' => $userId,
            ...$attributes,
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function activeGroupedFeed(): array
    {
        $active = Story::query()
            ->with(['user', 'song'])
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->get();

        $grouped = $active->groupBy('user_id');

        $items = [];
        foreach ($grouped as $userId => $stories) {
            /** @var Collection<int, Story> $stories */
            /** @var User|null $user */
            $user = $stories->first()?->user;
            if ($user === null) {
                continue;
            }
            $items[] = [
                'user' => $user,
                'stories' => $stories->all(),
            ];
        }

        return $items;
    }

    public function destroy(Story $story): void
    {
        $story->delete();
    }
}
