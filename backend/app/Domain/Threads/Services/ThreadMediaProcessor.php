<?php

declare(strict_types=1);

namespace App\Domain\Threads\Services;

use App\Domain\Posts\Exceptions\InvalidPostMediaException;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class ThreadMediaProcessor
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const IMAGE_MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private readonly FilesystemFactory $filesystem) {}

    /**
     * @return array{file_path: string, mime: string, type: string}
     */
    public function process(int $userId, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            throw new InvalidPostMediaException(
                'Unsupported file type. Use an image (jpg, png, webp, gif).',
            );
        }

        if ($file->getSize() > self::IMAGE_MAX_BYTES) {
            throw new InvalidPostMediaException('The image is too large (max 8 MB).');
        }

        $info = @getimagesize($file->getPathname());
        if ($info === false) {
            throw new InvalidPostMediaException('The image appears to be corrupted or invalid.');
        }

        $path = $this->filesystem->disk('public')->putFileAs(
            "threads/{$userId}",
            $file,
            Str::uuid()->toString().".{$extension}",
        );

        if ($path === false) {
            throw new InvalidPostMediaException('We could not store that file. Please try again.');
        }

        return [
            'file_path' => $path,
            'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
            'type' => 'image',
        ];
    }
}
