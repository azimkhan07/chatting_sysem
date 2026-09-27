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
        /**
         * Remote or CDN URL for image/video/gif/drawing payloads. Null for text.
         * This used to be hard-coded to null in the service, which silently made
         * every non-text message type unreachable.
         */
        public ?string $mediaUrl = null,
    ) {}
}
