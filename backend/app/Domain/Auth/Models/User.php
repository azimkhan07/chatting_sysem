<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
