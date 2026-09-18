<?php

declare(strict_types=1);

namespace App\Domain\Posts\Data;

use Illuminate\Http\UploadedFile;

final readonly class CreatePostData
{
    /**
     * @param  list<UploadedFile>  $media
     */
    public function __construct(
        public string $body = '',
        public array $media = [],
    ) {}
}
