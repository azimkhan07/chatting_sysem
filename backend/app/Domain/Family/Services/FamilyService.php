<?php

declare(strict_types=1);

namespace App\Domain\Family\Services;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Family\Enums\FamilyRole;
use App\Domain\Family\Exceptions\FamilyNotAllowedException;
use App\Domain\Family\Exceptions\FamilyNotFoundException;
use App\Domain\Family\Exceptions\FamilyPermissionException;
use App\Domain\Family\Models\FamilyGroup;
use App\Domain\Family\Models\FamilyMember;
use Illuminate\Support\Facades\DB;

/**
 * Family Center.
 *
 * The membership row is the only authority: every action resolves the actor's
 * own `FamilyMember` and then asks that row what it may do. There is no
 * separate "is this user a parent" flag to fall out of sync with the roster.
 *
 * Two invariants hold everywhere, and both are re-checked rather than assumed:
 *  1. The owner is always a guardian and can never be removed or demoted.
 *  2. A family always has at least one guardian (guaranteed by 1).
 *
 * v1 adds a member directly by username, with no pending-invite step. That
 * matches the supervision use case (a guardian setting up a teen's account)
 * and keeps the table free of tokens that have to expire, be resent, or leak.
 * A consent-pending flow is a deliberate follow-up, not an oversight.
 */
final class FamilyService
{
    public function membershipFor(int $userId): ?FamilyMember
    {
        return FamilyMember::query()
            ->with(['family', 'user'])
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * @throws FamilyNotAllowedException when the user is already in a family
     */
    public function createFor(User $user, string $name): FamilyGroup
    {
        if ($this->membershipFor((int) $user->id) !== null) {
            throw new FamilyNotAllowedException('You already belong to a family.');
        }

        return DB::transaction(function () use ($user, $name): FamilyGroup {
            $family = FamilyGroup::query()->forceCreate([
                'owner_id' => $user->id,
                'name' => trim($name),
            ]);

            // The owner is a member like everyone else - same row, same rules -
            // so a guardian-only action never has to special-case the owner.
            FamilyMember::query()->forceCreate([
                'family_group_id' => $family->id,
                'user_id' => $user->id,
                'role' => FamilyRole::Guardian->value,
            ]);

            return $family->load('owner');
        });
    }

    /**
     * @throws FamilyPermissionException when the actor is not the owner
     */
    public function rename(FamilyMember $actor, string $name): FamilyGroup
    {
        $this->assertOwner($actor, 'Only the family owner can rename the family.');

        $actor->family->update(['name' => trim($name)]);

        return $actor->family->refresh()->load('owner');
    }

    /**
     * @throws FamilyPermissionException when the actor's role cannot add members,
     *                                   or cannot add another guardian
     * @throws FamilyNotFoundException when the username does not resolve to an active user
     * @throws FamilyNotAllowedException when the target is already in a family
     */
    public function addMember(FamilyMember $actor, string $username, FamilyRole $role): FamilyMember
    {
        if (! $actor->canManageMembers()) {
            throw new FamilyPermissionException('Only a guardian or adult can add family members.');
        }

        // Granting guardianship is an owner power: an adult must not be able to
        // mint another guardian and out-vote the owner.
        if ($role->isGuardian() && ! $actor->isOwner()) {
            throw new FamilyPermissionException('Only the family owner can add another guardian.');
        }

        $target = User::query()->where('username', trim($username))->first();

        if ($target === null || $target->status !== UserStatus::Active || $target->isDeactivated()) {
            throw new FamilyNotFoundException('We could not find an active account with that username.');
        }

        if ((int) $target->id === (int) $actor->user_id) {
            throw new FamilyNotAllowedException('You are already in your own family.');
        }

        if ($this->membershipFor((int) $target->id) !== null) {
            throw new FamilyNotAllowedException('That person already belongs to a family.');
        }

        return FamilyMember::query()->forceCreate([
            'family_group_id' => $actor->family_group_id,
            'user_id' => $target->id,
            'role' => $role->value,
        ])->load(['user', 'family']);
    }

    /**
     * @throws FamilyPermissionException when the actor cannot set this role
     * @throws FamilyNotAllowedException when the target is the owner
     */
    public function changeRole(FamilyMember $actor, FamilyMember $target, FamilyRole $role): FamilyMember
    {
        if (! $actor->canManageMembers()) {
            throw new FamilyPermissionException('Only a guardian or adult can change a role.');
        }

        $this->assertSameFamily($actor, $target);

        if ($target->isOwner()) {
            throw new FamilyNotAllowedException('The owner\'s role cannot be changed.');
        }

        // Guardian -> guardian is a no-op, and guardian -> something else is the
        // owner's call; everything below guardian is fair game for a guardian
        // or an adult.
        if ($target->role->isGuardian() && ! $actor->isOwner()) {
            throw new FamilyPermissionException('Only the family owner can change a guardian\'s role.');
        }

        if ($target->role === $role) {
            return $target;
        }

        $target->update(['role' => $role->value]);

        return $target->refresh()->load(['user', 'family']);
    }

    /**
     * Removes someone from the family. A member removing themselves is a leave;
     * anyone else needs roster permission.
     *
     * @throws FamilyPermissionException when the actor may not remove this member
     * @throws FamilyNotAllowedException when the target is the owner
     */
    public function remove(FamilyMember $actor, FamilyMember $target): void
    {
        $this->assertSameFamily($actor, $target);

        if ($target->isOwner()) {
            throw new FamilyNotAllowedException(
                'The owner cannot be removed. Dissolve the family instead.'
            );
        }

        if ((int) $actor->id !== (int) $target->id) {
            if (! $actor->canManageMembers()) {
                throw new FamilyPermissionException('Only a guardian or adult can remove a member.');
            }

            if ($target->role->isGuardian() && ! $actor->isOwner()) {
                throw new FamilyPermissionException('Only the family owner can remove a guardian.');
            }
        }

        $target->delete();
    }

    /**
     * @throws FamilyNotAllowedException when the caller is the owner
     */
    public function leave(FamilyMember $actor): void
    {
        if ($actor->isOwner()) {
            throw new FamilyNotAllowedException(
                'You own this family. Dissolve it instead of leaving.'
            );
        }

        $actor->delete();
    }

    /**
     * Removes the family and every membership with it.
     *
     * @throws FamilyPermissionException when the actor is not the owner
     */
    public function dissolve(FamilyMember $actor): FamilyGroup
    {
        $this->assertOwner($actor, 'Only the family owner can dissolve the family.');

        $family = $actor->family;

        $family->members()->delete();
        $family->delete();

        return $family;
    }

    /**
     * Spending approval is deliberately guardian-only, not "any adult", so a
     * charge cannot be waved through by the same generation it protects.
     */
    public function canApproveSpending(FamilyMember $actor): bool
    {
        return $actor->role->isGuardian();
    }

    private function assertOwner(FamilyMember $actor, string $message): void
    {
        if (! $actor->isOwner()) {
            throw new FamilyPermissionException($message);
        }
    }

    private function assertSameFamily(FamilyMember $actor, FamilyMember $target): void
    {
        if ((int) $actor->family_group_id !== (int) $target->family_group_id) {
            // Same rule as a missing family: a stranger must not be able to tell
            // the difference between "not yours" and "does not exist".
            throw new FamilyNotFoundException('That member is not in your family.');
        }
    }
}
