<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Actions\CreatePostAction;
use App\Domain\Posts\Actions\ListFeedAction;
use App\Domain\Posts\Contracts\PostService as PostServiceContract;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Post;
use Illuminate\Pagination\CursorPaginator;

final class PostService implements PostServiceContract
{
    public function __construct(
        private readonly CreatePostAction $createPostAction,
        private readonly ListFeedAction $listFeedAction,
    ) {}

    public function create(User $author, CreatePostData $data): Post
    {
        return $this->createPostAction->handle($author->id, $data);
    }

    public function feedFor(User $user, int $limit = 20, ?string $cursor = null): CursorPaginator
    {
        return $this->listFeedAction->handle($user->id, $limit, $cursor);
    }
}
