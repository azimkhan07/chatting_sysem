<?php

declare(strict_types=1);

namespace App\Domain\Stories\Repositories;

use App\Domain\Auth\Models\User;
use App\Domain\Moderation\Services\BlockService;
use App\Domain\Stories\Contracts\StoryRepository;
use App\Domain\Stories\Models\Story;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class EloquentStoryRepository implements StoryRepository
{
    public function __construct(
        private readonly BlockService $blocks,
    ) {}

    public function create(int $userId, array $attributes): Story
    {
        return Story::query()->create([
            'user_id' => $userId,
            ...$attributes,
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function activeGroupedFeed(?int $viewerId): array
    {
        $query = Story::query()
            ->with(['user', 'song', 'mentions'])
            ->where('expires_at', '>', now());

        // The story tray is the one place a block could hide someone in a
        // different way from every other surface, because it is grouped per
        // author: filtering rows would leave an author with their remaining
        // stories and an avatar but drop nothing at all if the only story they
        // had was the filtered one. Filtering the author column before
        // grouping is what removes the whole tray, so a blocked account is
        // gone rather than reshaped.
        if ($viewerId !== null) {
            $this->blocks->hideFromQuery($query, $viewerId, 'stories.user_id');
        }

        $active = $query->orderByDesc('id')->get();

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
        $mediaPath = $story->media_path;

        $story->delete();

        // Uploaded media is not garbage collected, so the row delete is the only
        // chance to reclaim the file.
        if (is_string($mediaPath) && $mediaPath !== '') {
            Storage::disk('public')->delete($mediaPath);
        }
    }
}
