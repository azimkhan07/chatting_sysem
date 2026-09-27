<?php

declare(strict_types=1);

namespace App\Domain\Family\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Family\Enums\FamilyRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A household: the people who share one supervision policy.
 *
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property-read Carbon|null $created_at
 */
class FamilyGroup extends Model
{
    /**
     * `owner_id` is deliberately absent: it is a privilege column, and the only
     * writer is `FamilyService::createFor()` via `forceCreate`. A mass-assigned
     * owner would let a request appoint itself head of someone else's household.
     *
     * @var list<string>
     */
    protected $fillable = ['name'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The roster, in a stable order: guardians first, then by join time.
     *
     * Without this the order is whatever the database returns, so a member
     * would jump rows when anyone joined. The CASE expression is built from
     * `FamilyRole::cases()` rather than hand-written, so it cannot drift from
     * the enum.
     *
     * @return HasMany<FamilyMember, $this>
     */
    public function members(): HasMany
    {
        $branches = [];
        $cases = FamilyRole::cases();

        foreach ($cases as $index => $role) {
            $branches[] = "WHEN '{$role->value}' THEN {$index}";
        }

        return $this->hasMany(FamilyMember::class)
            ->orderByRaw('CASE role '.implode(' ', $branches).' ELSE '.count($cases).' END')
            ->orderBy('id');
    }

    public function guardianCount(): int
    {
        return $this->members()->where('role', FamilyRole::Guardian->value)->count();
    }
}
