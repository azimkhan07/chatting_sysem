<?php

declare(strict_types=1);

namespace App\Domain\Stories\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Social\Services\MentionService;
use App\Domain\Stories\Contracts\StoryRepository;
use App\Domain\Stories\Data\CreateStoryData;
use App\Domain\Stories\Exceptions\StoryNotAuthorizedException;
use App\Domain\Stories\Models\Story;

final class StoryService
{
    public function __construct(
        private readonly StoryRepository $storyRepository,
        private readonly StoryMediaProcessor $mediaProcessor,
        private readonly MentionService $mentions,
    ) {}

    public function create(User $user, CreateStoryData $data): Story
    {
        if ($data->file !== null) {
            $media = $this->mediaProcessor->process($user->id, $data->file);
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
                'media_url' => $data->mediaUrl,
                'type' => 'image',
                'mime' => null,
                'width' => null,
                'height' => null,
            ];
        }

        $story = $this->storyRepository->create($user->id, [
            ...$attributes,
            'caption' => $data->caption,
            'effects' => $data->effects,
            'text_style' => $data->textStyle,
            'song_id' => $data->songId,
            'location' => $data->location,
        ]);

        // After the row exists, so the tag and the notification can both point
        // at a real story id.
        $this->mentions->attachToStory($story, (string) $data->caption);

        return $story;
    }

    /**
     * @return array<int, array{user: User, stories: list<Story>}>
     */
    public function feed(?User $viewer): array
    {
        return $this->storyRepository->activeGroupedFeed($viewer?->id === null ? null : (int) $viewer->id);
    }

    public function destroy(User $user, Story $story): void
    {
        if ($story->user_id !== $user->id) {
            throw new StoryNotAuthorizedException('You may only delete your own story.');
        }

        $this->storyRepository->destroy($story);
    }
}
