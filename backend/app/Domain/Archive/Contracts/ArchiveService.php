<?php

declare(strict_types=1);

namespace App\Domain\Archive\Contracts;

use App\Domain\Posts\Models\Post;
use App\Domain\Stories\Models\Story;
use Illuminate\Support\Carbon;

interface ArchiveService
{
    /**
     * Days (within `year`) that hold any archived posts or stories, as a map
     * of `Y-m-d` -> ['posts' => int, 'stories' => int].
     *
     * @return array<string, array{posts: int, stories: int}>
     */
    public function calendar(int $userId, int $year): array;

    /**
     * Archived (or expired-exceeding) posts by the user created on that day.
     *
     * @return array<int, Post>
     */
    public function archivedPostsOn(int $userId, Carbon $day): array;

    /**
     * The user's stories that expired on a given day — the "archive" a story
     * becomes once its 24h window closes.
     *
     * @return array<int, Story>
     */
    public function archivedStoriesOn(int $userId, Carbon $day): array;

    public function archivePost(Post $post, int $userId): void;

    public function unarchivePost(Post $post, int $userId): void;
}
