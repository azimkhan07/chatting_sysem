<?php

declare(strict_types=1);

namespace Admin\Http\Resources;

use Admin\Domain\App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A report as staff see it.
 *
 * This is the full row, unlike ReportResource. The queue is the only surface
 * that names the reported account and the target's author, because deciding
 * whether to act means knowing who is behind it.
 *
 * @mixin Report
 */
final class ReportAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $targetType = $this->targetTypeEnum();
        $targetUser = $this->targetUser();

        return [
            'id' => $this->id,
            'target_type' => $targetType->value,
            'target_type_label' => $targetType->label(),
            'target_id' => $this->target_id,
            'reason' => $this->reasonEnum()->value,
            'reason_label' => $this->reasonEnum()->label(),
            'is_urgent' => $this->isUrgent(),
            'details' => $this->details,
            'status' => $this->statusEnum()->value,
            'reporter' => $this->whenLoaded('reporter', fn (): array => [
                'id' => $this->reporter->id,
                'username' => $this->reporter->username,
                'display_name' => $this->reporter->display_name,
            ]),
            // Null when the reported account was deleted. A queue that 500s on
            // a missing account is a queue staff learn to distrust.
            'target_user' => $targetUser === null ? null : [
                'id' => $targetUser->id,
                'username' => $targetUser->username,
                'display_name' => $targetUser->display_name,
                'status' => $targetUser->status->value,
            ],
            // Resolved by id across the staff database rather than eager
            // loaded: a relation would join two databases and find nothing.
            'handled_by' => $this->handled_by_staff === null ? null : [
                'id' => $this->handled_by_staff,
                'username' => $this->handlerName() ?? 'removed',
            ],
            'resolution' => $this->resolution,
            'handled_at' => $this->handled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
