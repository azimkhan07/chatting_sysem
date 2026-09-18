<?php

declare(strict_types=1);

namespace App\Domain\Posts\Data;

final readonly class CreatePostData
{
    public function __construct(
        public string $body,
    ) {}
}
