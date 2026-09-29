<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person choosing not to see another.
 *
 * Deliberately its own table rather than a nullable `blocked_by` on follows.
 * A block is not the absence of a follow: you can block someone you never
 * followed, you can block someone mid-follow, and unblocking must not
 * silently restore a follow the other person would have to accept again.
 *
 * @property-read int $id
 * @property-read int $blocker_id
 * @property-read int $blocked_id
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class UserBlock extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'blocker_id',
        'blocked_id',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
