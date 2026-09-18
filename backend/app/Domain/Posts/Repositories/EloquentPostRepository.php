<?php

declare(strict_types=1);

namespace App\Domain\Posts\Repositories;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Post;
use Illuminate\Pagination\CursorPaginator;

final class EloquentPostRepository implements PostRepository
{
    public function create(int $userId, CreatePostData $data): Post
    {
        return Post::query()->create([
            'user_id' => $userId,
            'body' => $data->body,
        ]);
    }

    public function attachMedia(Post $post, array $media): void
    {
        $post->media()->createMany($media);
    }

    public function feedFor(int $userId, int $limit, ?string $cursor): CursorPaginator
    {
        return Post::query()
            ->with(['user', 'media'])
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }
}
