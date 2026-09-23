<?php

declare(strict_types=1);

namespace App\Domain\Threads\Enums;

enum ThreadReactionType: string
{
    case Like = 'like';
    case Love = 'love';
    case Haha = 'haha';
    case Wow = 'wow';
    case Sad = 'sad';
    case Angry = 'angry';
}
