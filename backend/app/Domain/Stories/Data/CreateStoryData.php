<?php

declare(strict_types=1);

namespace App\Domain\Stories\Data;

use Illuminate\Http\UploadedFile;

/**
 * Everything one story needs, in one object.
 *
 * This used to be seven positional arguments on the service. A seventh of the
 * same kind is how you end up passing a story's caption into its effects slot
 * and only finding out when a caption renders in the wrong font. Adding
 * `location` was the eighth; at that point the argument list was the bug.
 */
final readonly class CreateStoryData
{
    public function __construct(
        public ?UploadedFile $file = null,
        public ?string $mediaUrl = null,
        public ?string $caption = null,
        public ?string $effects = null,
        public ?int $songId = null,
        public ?array $textStyle = null,
        public ?string $location = null,
    ) {}
}
