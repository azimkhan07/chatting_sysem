<?php

declare(strict_types=1);

namespace App\Domain\Chat\Data;

use App\Domain\Chat\Enums\ConversationType;

final readonly class CreateConversationData
{
    /**
     * @param  list<int>  $memberIds
     */
    public function __construct(
        public ConversationType $type,
        public ?int $targetUserId,
        public ?string $name,
        public array $memberIds,
    ) {}
}
