<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Domain\Admin\Models\Role;
use App\Domain\Auth\Enums\AccountType;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Posts\Models\Post;
use App\Domain\Settings\Models\UserSettings;
use App\Domain\Social\Models\Follow;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property string $username
 * @property-read int $id
 * @property-read string $email
 * @property-read string|null $mobile
 * @property string $display_name
 * @property string|null $bio
 * @property-read string $password
 * @property-read string|null $avatar_path
 * @property-read string|null $cover_path
 * @property-read bool $is_verified
 * @property-read UserStatus $status
 * @property AccountType $account_type
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property bool $show_contact
 * @property-read Carbon|null $last_seen_at
 * @property Carbon|null $deactivated_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * New accounts are active and unverified. `status` and `is_verified` are
     * not fillable, so this default is what keeps registration from needing to
     * pass them — and what stops a request payload from setting them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => UserStatus::Active->value,
        'is_verified' => false,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * `is_verified` and `status` are deliberately absent: they are privilege
     * columns driven by the subscription/admin flows, never by user input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'mobile',
        'display_name',
        'bio',
        'password',
        'avatar_path',
        'cover_path',
        'last_seen_at',
        'account_type',
        'contact_email',
        'contact_phone',
        'show_contact',
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
            'show_contact' => 'boolean',
            'last_seen_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * The column is NOT NULL with a default, so a null here can only mean the
     * model was never refreshed after the insert - which is exactly the state a
     * freshly registered user is in. Defaulting on read keeps
     * `$user->account_type` total, instead of letting the first request of a new
     * account die on a null enum.
     */
    protected function accountType(): Attribute
    {
        return Attribute::make(
            // Accepts the enum as well as the raw string, because a caller may
            // assign either and the getter then sees whatever it assigned.
            get: static fn (AccountType|string|null $value): AccountType => match (true) {
                $value instanceof AccountType => $value,
                is_string($value) && $value !== '' => AccountType::from($value),
                default => AccountType::Personal,
            },
        );
    }

    /**
     * A self-service deactivation, as opposed to an admin suspension.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * The id of the Sanctum token backing the current request, or null.
     *
     * Null is meaningful and not the same as "no token": `Sanctum::actingAs()`
     * installs a `TransientToken` that has no id, so the "keep me, revoke the
     * rest" operations correctly fall back to revoking every stored row.
     */
    public function currentTokenId(): ?int
    {
        $token = $this->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token->getKey() : null;
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
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    /**
     * The people following this user.
     *
     * @return HasManyThrough<User, Follow, $this>
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
     *
     * @return HasManyThrough<User, Follow, $this>
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

    /**
     * Chats this user is a member of. The membership row is the source of truth
     * for access, not a conversation owner column, so this goes through the
     * pivot rather than `conversations.created_by`.
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_members')
            ->withPivot(['role', 'last_read_message_id', 'muted']);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class);
    }

    /**
     * This user's place in their family, if any.
     *
     * `family_members.user_id` is unique, so this is always a HasOne and
     * `relationLoaded` is never a lie.
     */
    public function familyMembership(): HasOne
    {
        return $this->hasOne(FamilyMember::class);
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
