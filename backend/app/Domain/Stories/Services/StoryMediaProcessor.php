<?php

declare(strict_types=1);

namespace App\Domain\Stories\Services;

use App\Domain\Posts\Enums\PostMediaType;
use App\Domain\Posts\Exceptions\InvalidPostMediaException;
use App\Support\Media\MediaInspector;
use App\Support\Media\MediaKind;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class StoryMediaProcessor
{
    private const IMAGE_MAX_BYTES = 8 * 1024 * 1024;

    private const VIDEO_MAX_BYTES = 100 * 1024 * 1024;

    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly MediaInspector $inspector,
    ) {}

    /**
     * @return array{type: PostMediaType, file_path: string, mime: string, size: int, width: ?int, height: ?int}
     */
    public function process(int $userId, UploadedFile $file): array
    {
        $media = $this->inspector->describe($file);

        if ($media === null) {
            throw new InvalidPostMediaException(
                'Unsupported file type. Use an image (jpg, png, webp, gif) or a video (mp4, webm, mov).',
            );
        }

        $type = $media['kind'] === MediaKind::Image ? PostMediaType::Image : PostMediaType::Video;

        $this->assertWithinLimit(
            $file,
            $type === PostMediaType::Image ? self::IMAGE_MAX_BYTES : self::VIDEO_MAX_BYTES,
            $type->value,
        );

        $path = $this->filesystem->disk('public')->putFileAs(
            "stories/{$userId}",
            $file,
            Str::uuid()->toString().'.'.$media['extension'],
        );

        if ($path === false) {
            throw new InvalidPostMediaException('We could not store that file. Please try again.');
        }

        return [
            'type' => $type,
            'file_path' => $path,
            'mime' => $media['mime'],
            'size' => $file->getSize(),
            'width' => $media['width'],
            'height' => $media['height'],
        ];
    }

    private function assertWithinLimit(UploadedFile $file, int $maxBytes, string $label): void
    {
        if ($file->getSize() > $maxBytes) {
            throw new InvalidPostMediaException(
                "The {$label} is too large (max ".($maxBytes / 1024 / 1024).' MB).',
            );
        }
    }
}
