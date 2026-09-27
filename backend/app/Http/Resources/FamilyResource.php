<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Family\Enums\FamilyRole;
use App\Domain\Family\Models\FamilyGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The family, from the signed-in member's point of view.
 *
 * `viewer` tells the client what it may do without a second round trip: the
 * member list needs the role list to render the "add as" picker, and the
 * dissolve button only exists for the owner.
 *
 * @mixin FamilyGroup
 */
final class FamilyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FamilyRole|null $viewerRole */
        $viewerRole = $request->attributes->get('family_role');
        $isOwner = (bool) $request->attributes->get('family_is_owner');

        return [
            'id' => (int) $this->resource->id,
            'name' => (string) $this->resource->name,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'counts' => [
                'members' => $this->resource->members()->count(),
                'guardians' => $this->resource->guardianCount(),
            ],
            'viewer' => [
                'role' => $viewerRole?->value,
                'role_label' => $viewerRole?->label(),
                'is_owner' => $isOwner,
                'can_manage_members' => $viewerRole?->canManageMembers() ?? false,
                // Guardians and adults can add members, but only the owner can
                // add another guardian - which is why the client filters the
                // picker rather than sending the whole enum.
                'can_add_guardian' => $isOwner,
                'can_rename' => $isOwner,
                'can_dissolve' => $isOwner,
                'can_leave' => ! $isOwner,
                'can_approve_spending' => $viewerRole?->isGuardian() ?? false,
            ],
            'roles' => array_map(
                static fn (FamilyRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                    'blurb' => $role->blurb(),
                ],
                FamilyRole::cases(),
            ),
            'members' => FamilyMemberResource::collection($this->whenLoaded('members')),
        ];
    }
}
