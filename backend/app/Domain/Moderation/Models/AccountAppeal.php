<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Models;

use App\Domain\Admin\Models\StaffUser;
use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A suspended user's request to be reviewed and (if approved) unsuspended.
 *
 * The user cannot sign in while suspended, so the appeal is filed with the
 * same identifier + password proof used by account reactivation. The support
 * desk either approves it (user flips back to `active`, tokens deleted so the
 * stale ones die) or rejects it (suspension stands).
 *
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string $message
 * @property-read string $status
 * @property-read int|null $handled_by
 * @property-read string|null $resolution
 * @property-read Carbon|null $handled_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read User $user
 */
final class AccountAppeal extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'message',
        'status',
        'handled_by',
        'handled_by_staff',
        'resolution',
        'handled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<StaffUser, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'handled_by_staff');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}