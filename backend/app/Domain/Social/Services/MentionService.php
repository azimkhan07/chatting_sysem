<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
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
    /**
     * Attaches every mentioned account that actually exists and is discoverable.
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

        if ($ids !== []) {
            $post->mentions()->sync($ids);
        }
    }

    /**
     * @param  list<string>  $usernames
     * @return list<int>
     */
    private function resolveIds(array $usernames, int $authorId): array
    {
        return User::query()
            ->whereIn('username', $usernames)
            ->where('status', UserStatus::Active->value)
            // The author tagging themselves is not news, and the tagged line
            // would otherwise read "You tagged yourself" on their own post.
            ->where('id', '!=', $authorId)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
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
}
