<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
}
