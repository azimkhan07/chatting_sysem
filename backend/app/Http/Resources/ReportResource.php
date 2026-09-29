<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Moderation\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the person who filed the report gets back.
 *
 * Deliberately thin. A reporter does not need the report id, the status, or
 * the target's account: they filed it and the only thing they are owed is
 * confirmation. The full row, including whatever the reporter typed and the
 * account it points at, is staff information and belongs to the queue.
 *
 * @mixin Report
 */
final class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->targetTypeEnum()->value,
            'target_type_label' => $this->targetTypeEnum()->label(),
            'status' => $this->statusEnum()->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
