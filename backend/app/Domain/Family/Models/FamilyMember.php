<?php

declare(strict_types=1);

namespace App\Domain\Family\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Family\Enums\FamilyRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's place in one family.
 *
 * `family_members.user_id` is unique across the whole table, so this row is
 * also the answer to "is this user already in a family?".
 *
 * @property int $id
 * @property int $family_group_id
 * @property int $user_id
 * @property FamilyRole $role
 */
class FamilyMember extends Model
{
    /**
     * `family_group_id` and `user_id` are absent on purpose: they are the
     * identity of the row, and only `FamilyService` may write them (via
     * `forceCreate`). `role` is the only client-influenced column, and even that
     * goes through the service's permission checks.
     *
     * @var list<string>
     */
    protected $fillable = ['role'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => FamilyRole::class];
    }

    /**
     * @return BelongsTo<FamilyGroup, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(FamilyGroup::class, 'family_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return (int) $this->family->owner_id === (int) $this->user_id;
    }

    public function canManageMembers(): bool
    {
        return $this->role->canManageMembers();
    }
}
