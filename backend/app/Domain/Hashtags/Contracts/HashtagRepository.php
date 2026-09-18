<?php

declare(strict_types=1);

namespace App\Domain\Hashtags\Contracts;

use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Collection;

interface HashtagRepository
{
    /**
     * Parse hashtags in a post body and attach them to the post.
     */
    public function attachToPost(Post $post, string $body): void;

    public function findByName(string $name): ?Hashtag;

    /**
     * Active hashtags whose name starts with the given prefix, most used first.
     *
     * @return Collection<int, Hashtag>
     */
    public function suggest(string $prefix, int $limit = 8): Collection;
}
