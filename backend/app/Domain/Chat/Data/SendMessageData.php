<?php

declare(strict_types=1);

namespace App\Domain\Chat\Data;

use App\Domain\Chat\Enums\MessageType;

final readonly class SendMessageData
{
    public function __construct(
        public MessageType $type,
        public ?string $body,
        public ?string $clientId,
    ) {}
}
