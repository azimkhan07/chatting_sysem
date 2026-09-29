<?php

declare(strict_types=1);

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Contracts\ArchiveService;
use App\Domain\Posts\Models\Post;
use App\Domain\Stories\Models\Story;
use Illuminate\Support\Carbon;

final class EloquentArchiveService implements ArchiveService
{
    public function calendar(int $userId, int $year): array
    {
        $start = Carbon::create($year, 1, 1, 0, 0, 0);
        $end = $start->copy()->endOfYear();

        $postDays = Post::query()
            ->where('user_id', $userId)
            ->whereNotNull('archived_at')
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (Post $post): string => $post->created_at->toDateString())
            ->map->count();

        $storyDays = Story::query()
            ->where('user_id', $userId)
            ->whereBetween('expires_at', [$start, $end])
            // An unexpired story is still on the tray, not in the archive.
            ->where('expires_at', '<=', now())
            ->get(['expires_at', 'created_at'])
            // Stories archive the day they were published, not the day they
            // lapsed - otherwise a Monday story would surface on Tuesday.
            ->groupBy(
                fn (Story $story): string => $story->createdAtDay(),
            )
            ->map->count();

        $days = [];

        foreach ($postDays as $date => $count) {
            $days[(string) $date]['posts'] = (int) $count;
        }
        foreach ($storyDays as $date => $count) {
            $days[(string) $date]['stories'] = (int) $count;
        }
        foreach ($days as $date => &$entry) {
            $entry['posts'] ??= 0;
            $entry['stories'] ??= 0;
            /** @var array{posts: int, stories: int} $entry */
        }
        unset($entry);

        ksort($days);

        return $days;
    }

    public function archivedPostsOn(int $userId, Carbon $day): array
    {
        return Post::query()
            ->where('user_id', $userId)
            ->whereNotNull('archived_at')
            ->whereDate('created_at', $day->toDateString())
            ->with(['user', 'media', 'song'])
            ->withCount(['likes', 'comments', 'shares'])
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function archivedStoriesOn(int $userId, Carbon $day): array
    {
        return Story::query()
            ->where('user_id', $userId)
            ->where('expires_at', '<=', now())
            ->whereDate('created_at', $day->toDateString())
            ->with(['user', 'song', 'mentions'])
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function archivePost(Post $post, int $userId): void
    {
        $post->update(['archived_at' => now()]);
    }

    public function unarchivePost(Post $post, int $userId): void
    {
        $post->update(['archived_at' => null]);
    }
}
