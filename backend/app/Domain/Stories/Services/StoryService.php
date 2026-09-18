<?php

declare(strict_types=1);

namespace App\Domain\Stories\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Stories\Contracts\StoryRepository;
use App\Domain\Stories\Exceptions\StoryNotAuthorizedException;
use App\Domain\Stories\Models\Story;
use Illuminate\Http\UploadedFile;

final class StoryService
{
    public function __construct(
        private readonly StoryRepository $storyRepository,
        private readonly StoryMediaProcessor $mediaProcessor,
    ) {}

    public function create(User $user, UploadedFile $file, ?string $caption, ?string $effects = null): Story
    {
        $media = $this->mediaProcessor->process($user->id, $file);

        return $this->storyRepository->create($user->id, [
            'media_path' => $media['file_path'],
            'type' => $media['type']->value,
            'mime' => $media['mime'],
            'width' => $media['width'],
            'height' => $media['height'],
            'caption' => $caption,
            'effects' => $effects,
        ]);
    }

    /**
     * @return array<int, array{user: User, stories: list<Story>}>
     */
    public function feed(): array
    {
        return $this->storyRepository->activeGroupedFeed();
    }

    public function destroy(User $user, Story $story): void
    {
        if ($story->user_id !== $user->id) {
            throw new StoryNotAuthorizedException('You may only delete your own story.');
        }

        $this->storyRepository->destroy($story);
    }
}
