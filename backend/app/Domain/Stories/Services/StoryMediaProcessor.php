<?php

declare(strict_types=1);

namespace App\Domain\Stories\Services;

use App\Domain\Posts\Enums\PostMediaType;
use App\Domain\Posts\Exceptions\InvalidPostMediaException;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class StoryMediaProcessor
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov'];

    private const IMAGE_MAX_BYTES = 8 * 1024 * 1024;

    private const VIDEO_MAX_BYTES = 100 * 1024 * 1024;

    public function __construct(private readonly FilesystemFactory $filesystem) {}

    /**
     * @return array{type: PostMediaType, file_path: string, mime: string, size: int, width: ?int, height: ?int}
     */
    public function process(int $userId, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $type = $this->resolveType($extension);

        if ($type === PostMediaType::Image) {
            $this->assertWithinLimit($file, self::IMAGE_MAX_BYTES, 'image');
            [$width, $height] = $this->readDimensions($file);
        } else {
            $this->assertWithinLimit($file, self::VIDEO_MAX_BYTES, 'video');
            $width = $height = null;
        }

        $path = $this->filesystem->disk('public')->putFileAs(
            "stories/{$userId}",
            $file,
            Str::uuid()->toString().".{$extension}",
        );

        if ($path === false) {
            throw new InvalidPostMediaException('We could not store that file. Please try again.');
        }

        return [
            'type' => $type,
            'file_path' => $path,
            'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ];
    }

    private function resolveType(string $extension): PostMediaType
    {
        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return PostMediaType::Image;
        }

        if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            return PostMediaType::Video;
        }

        throw new InvalidPostMediaException(
            'Unsupported file type. Use an image (jpg, png, webp, gif) or a video (mp4, webm, mov).',
        );
    }

    private function assertWithinLimit(UploadedFile $file, int $maxBytes, string $label): void
    {
        if ($file->getSize() > $maxBytes) {
            throw new InvalidPostMediaException(
                "The {$label} is too large (max ".($maxBytes / 1024 / 1024).' MB).',
            );
        }
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function readDimensions(UploadedFile $file): array
    {
        $info = @getimagesize($file->getPathname());

        if ($info === false) {
            throw new InvalidPostMediaException('The image appears to be corrupted or invalid.');
        }

        return [$info[0], $info[1]];
    }
}
