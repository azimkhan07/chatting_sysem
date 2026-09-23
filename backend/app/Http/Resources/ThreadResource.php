<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Threads\Enums\ThreadStatus;
use App\Domain\Threads\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Thread
 */
final class ThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'status' => $this->status->value,
            'active' => $this->status === ThreadStatus::Active,
            'created_by' => $this->created_by,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'entry_count' => $this->when(
                $this->relationLoaded('entries'),
                $this->entries->count(),
            ),
            'recap' => $this->recap,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
