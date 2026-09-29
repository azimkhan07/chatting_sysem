<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Moderation\Services\BlockService;
use App\Domain\Posts\Models\Post;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Enums\NotificationType;
use App\Domain\Stories\Models\Story;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Turning the `@words` in a body into rows.
 *
 * The same way hashtags work: parsed out of the text on write, never supplied
 * by the client as a separate list. If the client sent both a body and a
 * mention list, the two could disagree, and a tag that does not appear in the
 * caption is a tag nobody typed.
 */
final class MentionService
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly BlockService $blocks,
    ) {}

    /**
     * Attaches every mentioned account that actually exists and is discoverable,
     * and tells each one they were named.
     *
     * A name that matches no account is skipped rather than reported. Someone
     * writing `@to @do` mid-word should not be blocked by it, and the text they
     * typed is preserved either way.
     */
    public function attachToPost(Post $post, string $body): void
    {
        $usernames = MentionSuggestionService::parse($body);

        if ($usernames === []) {
            return;
        }

        $ids = $this->resolveIds($usernames, (int) $post->user_id);

        if ($ids === []) {
            return;
        }

        $post->mentions()->sync($ids);

        // Persisted before notifying, and only once, so the notification cannot
        // point at a tag that a rollback takes with it.
        foreach ($ids as $id) {
            $this->notifications->create($id, (int) $post->user_id, NotificationType::Mention, [
                'post_id' => (int) $post->id,
            ]);
        }
    }

    /**
     * @param  list<string>  $usernames
     * @return list<int>
     */
    private function resolveIds(array $usernames, int $authorId): array
    {
        $ids = User::query()
            ->whereIn('username', $usernames)
            ->where('status', UserStatus::Active->value)
            // The author tagging themselves is not news, and the tagged line
            // would otherwise read "You tagged yourself" on their own post.
            ->where('id', '!=', $authorId)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        // A blocked pair is not tagged. Tagging someone is a signal that says
        // "I want your attention", which is exactly the interaction a block
        // refused - never mind that the tag is on a post the tagged person may
        // see in a feed the block already filtered.
        return array_values(array_filter(
            $ids,
            fn (int $id): bool => ! $this->blocks->blocksEitherWay($authorId, $id),
        ));
    }

    /**
     * Stories name people the same way posts do.
     *
     * The composer already offers the same picker, so without this a tag
     * typed into a story would look like it worked and quietly not exist.
     */
    public function attachToStory(Story $story, string $caption): void
    {
        $usernames = MentionSuggestionService::parse($caption);

        if ($usernames === []) {
            return;
        }

        $ids = $this->resolveIds($usernames, (int) $story->user_id);

        if ($ids === []) {
            return;
        }

        $story->mentions()->sync($ids);

        foreach ($ids as $id) {
            $this->notifications->create($id, (int) $story->user_id, NotificationType::Mention, [
                'story_id' => (int) $story->id,
            ]);
        }
    }

    /**
     * The accounts a post says it is about, for the "tagged" line.
     *
     * @return EloquentCollection<int, User>
     */
    public function forPost(Post $post): EloquentCollection
    {
        return $post->mentions()->orderBy('users.username')->get();
    }

    /**
     * The accounts a story says it is about, for the "tagged" line.
     *
     * @return EloquentCollection<int, User>
     */
    public function forStory(Story $story): EloquentCollection
    {
        return $story->mentions()->orderBy('users.username')->get();
    }
}
