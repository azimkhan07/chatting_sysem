<?php

declare(strict_types=1);

namespace App\Domain\Hashtags\Repositories;

use App\Domain\Hashtags\Contracts\HashtagRepository;
use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Collection;

final class EloquentHashtagRepository implements HashtagRepository
{
    public function attachToPost(Post $post, string $body): void
    {
        $found = [];
        if (preg_match_all('/(?:^|\s)#([A-Za-z0-9_]+)/', $body, $matches)) {
            foreach ($matches[1] as $name) {
                $found[] = $name;
            }
        }

        if ($found === []) {
            return;
        }

        $ids = array_map(
            fn (string $name): int => Hashtag::query()->firstOrCreate(['name' => $name])->id,
            array_unique($found),
        );

        $post->hashtags()->sync($ids);
    }

    public function findByName(string $name): ?Hashtag
    {
        return Hashtag::query()
            ->withCount('posts')
            ->where('name', $name)
            ->first();
    }

    public function suggest(string $prefix, int $limit = 8): Collection
    {
        return Hashtag::query()
            ->withCount('posts')
            ->where('name', 'like', "{$prefix}%")
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit(max(1, $limit))
            ->get();
    }
}
