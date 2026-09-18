<?php

declare(strict_types=1);

namespace App\Domain\Hashtags\Services;

use App\Domain\Hashtags\Contracts\HashtagRepository;
use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Models\Post;

final class HashtagService
{
    public function __construct(private readonly HashtagRepository $hashtags) {}

    public function attachToPost(Post $post, string $body): void
    {
        $this->hashtags->attachToPost($post, $body);
    }

    public function findByName(string $name): ?Hashtag
    {
        return $this->hashtags->findByName($name);
    }

    /**
     * @return array<int, array{name: string, posts_count: int}>
     */
    public function suggest(string $prefix, int $limit = 8): array
    {
        return $this->hashtags->suggest($prefix, $limit)
            ->map(fn (Hashtag $hashtag): array => [
                'name' => $hashtag->name,
                'posts_count' => (int) $hashtag->posts_count,
            ])
            ->values()
            ->all();
    }
}
