<?php

declare(strict_types=1);

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * A console account (admin / super_admin / support).
 *
 * Lives on the dedicated `admin` connection, physically separate from the app
 * users table. Staff tokens still live in the app's personal_access_tokens
 * table (Sanctum writes them on the default connection), but the account that
 * owns a token always resolves here — the password hashes a compromised app DB
 * would leak never see the app database.
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