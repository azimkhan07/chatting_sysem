<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $follower_id
 * @property-read int $following_id
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class Follow extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'follower_id',
        'following_id',
    ];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
