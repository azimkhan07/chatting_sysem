<?php

declare(strict_types=1);

namespace App\Domain\Threads\Enums;

enum ThreadStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
}
