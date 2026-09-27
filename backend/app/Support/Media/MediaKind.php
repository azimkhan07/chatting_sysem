<?php

declare(strict_types=1);

namespace App\Support\Media;

enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
}
