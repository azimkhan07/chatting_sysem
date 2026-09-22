<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Domain\Admin\Models\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Social\Models\Follow;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $username
 * @property-read int $id
 * @property-read string $email
 * @property-read string|null $mobile
 * @property-read string $display_name
 * @property-read string $password
 * @property-read string|null $avatar_path
 * @property-read bool $is_verified
 * @property-read UserStatus $status
 * @property-read Carbon|null $last_seen_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'mobile',
        'display_name',
        'password',
        'avatar_path',
        'is_verified',
        'status',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'status' => UserStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Bind the database factory explicitly (model lives in the Domain
     * namespace, not the conventional App\Models location).
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    /**
     * The people following this user.
     */
    public function followers(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            Follow::class,
            'following_id',
            'id',
            'id',
            'follower_id'
        );
    }

    /**
     * The people this user follows.
     */
    public function following(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            Follow::class,
            'follower_id',
            'id',
            'id',
            'following_id'
        );
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * The "booted" method of the model. Works here as a template for the
     * domain: register observers / global scopes once, in one place.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->username = trim($user->username);
        });
    }
}
