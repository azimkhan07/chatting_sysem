<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Post;

final class CreatePostAction
{
    public function __construct(private readonly PostRepository $repository) {}

    public function handle(int $userId, CreatePostData $data): Post
    {
        return $this->repository->create($userId, $data);
    }
}
