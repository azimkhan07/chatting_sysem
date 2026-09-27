<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Domain\Posts\Exceptions\InvalidPostMediaException;
use App\Support\Media\MediaInspector;
use App\Support\Media\MediaKind;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Drawings are canvas PNGs pasted by the client. The bytes are sniffed exactly
 * like any other upload, so a client cannot smuggle an arbitrary file (or an
 * inline data: payload) into chat media by calling this directly.
 */
final class ChatDrawingProcessor
{
    private const MAX_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly MediaInspector $inspector,
    ) {}

    /**
     * @return array{file_path: string, mime: string}
     */
    public function process(int $userId, UploadedFile $file): array
    {
        $media = $this->inspector->describe($file);

        if ($media === null || $media['kind'] !== MediaKind::Image) {
            throw new InvalidPostMediaException(
                'Unsupported drawing. Send a PNG, JPG, GIF or WebP image.',
            );
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidPostMediaException('The drawing is too large (max 4 MB).');
        }

        $path = $this->filesystem->disk('public')->putFileAs(
            "drawings/{$userId}",
            $file,
            Str::uuid()->toString().'.'.$media['extension'],
        );

        if ($path === false) {
            throw new InvalidPostMediaException('We could not store that drawing. Please try again.');
        }

        return ['file_path' => $path, 'mime' => $media['mime']];
    }
}
