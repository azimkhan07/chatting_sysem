<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Family\Enums\FamilyRole;
use App\Domain\Family\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person in the family, as seen by the signed-in member.
 *
 * `permissions` is computed per viewer rather than sent as a flat role, because
 * the client's buttons depend on the viewer's own role - an adult and the owner
 * see the same member row and must be offered different actions.
 *
 * @mixin FamilyMember
 */
final class FamilyMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FamilyRole|null $viewerRole */
        $viewerRole = $request->attributes->get('family_role');
        $isOwner = (bool) $request->attributes->get('family_is_owner');
        $targetIsOwner = $this->resource->isOwner();
        $targetIsGuardian = $this->resource->role->isGuardian();

        $canChangeRole = $viewerRole?->canManageMembers() === true
            && ! $targetIsOwner
            && (! $targetIsGuardian || $isOwner);

        $canRemove = $isOwner || ($viewerRole?->canManageMembers() === true && ! $targetIsOwner);

        return [
            'id' => (int) $this->resource->id,
            'role' => $this->resource->role->value,
            'role_label' => $this->resource->role->label(),
            'is_owner' => $targetIsOwner,
            'is_self' => (int) $this->resource->user_id === (int) $request->user()?->id,
            'user' => [
                'id' => (int) $this->resource->user->id,
                'username' => (string) $this->resource->user->username,
                'display_name' => (string) $this->resource->user->display_name,
                'avatar_path' => $this->resource->user->avatar_path,
                'is_verified' => (bool) $this->resource->user->is_verified,
            ],
            'joined_at' => $this->resource->created_at?->toIso8601String(),
            'permissions' => [
                'can_change_role' => $canChangeRole,
                'can_remove' => $canRemove,
                'can_leave' => ! $targetIsOwner,
            ],
        ];
    }
}
