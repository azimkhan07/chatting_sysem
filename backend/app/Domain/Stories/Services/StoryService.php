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

    public function create(
        User $user,
        ?UploadedFile $file,
        ?string $mediaUrl = null,
        ?string $caption = null,
        ?string $effects = null,
        ?int $songId = null,
        ?array $textStyle = null,
    ): Story {
        if ($file !== null) {
            $media = $this->mediaProcessor->process($user->id, $file);
            $attributes = [
                'media_path' => $media['file_path'],
                'media_url' => null,
                'type' => $media['type']->value,
                'mime' => $media['mime'],
                'width' => $media['width'],
                'height' => $media['height'],
            ];
        } else {
            $attributes = [
                'media_path' => null,
                'media_url' => $mediaUrl,
                'type' => 'image',
                'mime' => null,
                'width' => null,
                'height' => null,
            ];
        }

        return $this->storyRepository->create($user->id, [
            ...$attributes,
            'caption' => $caption,
            'effects' => $effects,
            'text_style' => $textStyle,
            'song_id' => $songId,
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
