<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Hashtags\Services\HashtagService;
use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Services\PostMediaProcessor;

final class CreatePostAction
{
    public function __construct(
        private readonly PostRepository $repository,
        private readonly PostMediaProcessor $mediaProcessor,
        private readonly HashtagService $hashtags,
    ) {}

    public function handle(int $userId, CreatePostData $data): Post
    {
        $post = $this->repository->create($userId, $data);

        if ($data->media !== []) {
            $media = $this->mediaProcessor->processAll($userId, $data->media);
            $this->repository->attachMedia($post, $media);
            $post->load('media');
        }

        $this->hashtags->attachToPost($post, $data->body);

        return $post;
    }
}
