<?php

declare(strict_types=1);

namespace Admin\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * A console account (super_admin / admin / support / moderator).
 *
 * Lives on this app's own `admin` connection, in a database the main app has
 * no connection string for. Neither the row nor the token that proves a
 * session is reachable from backend/, so the support team's deployment cannot
 * read a single console credential even if it is fully compromised.
 *
 * @property-read int $id
 * @property string $username
 * @property string $display_name
 * @property string $password
 * @property string $role
 * @property Carbon|null $last_seen_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class StaffUser extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'admin';

    protected $table = 'staff_users';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_SUPPORT = 'support';

    public const ROLE_MODERATOR = 'moderator';

    /**
     * Roles that may change data rather than only read it. A moderator is
     * deliberately absent: it sees the whole console and writes nothing.
     *
     * @return list<string>
     */
    public static function operatorRoles(): array
    {
        return [self::ROLE_SUPPORT, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];
    }

    /**
     * Roles that may mint another admin-level account. Support hiring is an
     * admin job, but only a super_admin can create an admin or a super_admin.
     *
     * @return list<string>
     */
    public static function adminRoles(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'display_name',
        'password',
        'role',
        'last_seen_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function supportProfile(): HasOne
    {
        return $this->hasOne(SupportStaff::class, 'staff_user_id');
    }
}
