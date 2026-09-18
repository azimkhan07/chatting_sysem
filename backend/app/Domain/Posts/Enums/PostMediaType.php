<?php

declare(strict_types=1);

namespace App\Domain\Posts\Enums;

enum PostMediaType: string
{
    case Image = 'image';
    case Video = 'video';
}
