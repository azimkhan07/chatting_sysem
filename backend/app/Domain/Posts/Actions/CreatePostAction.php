<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Hashtags\Services\HashtagService;
use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Data\CreatePostData;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Services\PostMediaProcessor;
use App\Domain\Social\Services\MentionService;

final class CreatePostAction
{
    public function __construct(
        private readonly PostRepository $repository,
        private readonly PostMediaProcessor $mediaProcessor,
        private readonly HashtagService $hashtags,
        private readonly MentionService $mentions,
    ) {}

    public function handle(int $userId, CreatePostData $data): Post
    {
        $post = $this->repository->create($userId, $data);

        if ($data->media !== []) {
            $media = $this->mediaProcessor->processAll($userId, $data->media);
            $this->repository->attachMedia($post, $media);
            $post->load('media');
        }

        // Both parsed from the body, never from a separate list the client
        // could disagree with the text about.
        $this->hashtags->attachToPost($post, $data->body);
        $this->mentions->attachToPost($post, $data->body);

        if ($data->songId !== null) {
            $post->load('song');
        }

        return $post;
    }
}
