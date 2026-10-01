<?php

declare(strict_types=1);

namespace Admin\Domain\App\Models;

use Admin\Domain\App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A read model over the main app's `users` table.
 *
 * Deliberately NOT the app's User model. The console needs a dozen columns and
 * one relation, and it needs them to stay cheap: the app's model drags in posts,
 * blocks, follows, conversations, family and settings, none of which are copied
 * into this app, so declaring them here would be a lie that only surfaces at
 * runtime as a missing-class error.
 *
 * This model is for reading the console's user list and the users attached to
 * reports, subscriptions and tickets. A console action that changes a user goes
 * through the main app, not through mass assignment here: there is no
 * `$fillable` on purpose, so a stray `fill()` cannot rewrite a row.
 *
 * @property-read int $id
 * @property string $username
 * @property-read string $email
 * @property string $display_name
 * @property string|null $bio
 * @property-read string|null $avatar_path
 * @property-read bool $is_verified
 * @property-read UserStatus $status
 * @property-read Carbon|null $last_seen_at
 * @property-read Carbon $created_at
 */
class User extends \Illuminate\Database\Eloquent\Model
{
    protected $connection = 'app';

    protected $table = 'users';

    use SoftDeletes;

    /**
     * No fillable, on purpose: see the class docblock. Console writes to user
     * rows happen through the main app's own API.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'status' => UserStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
