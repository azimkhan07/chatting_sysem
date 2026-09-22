<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Social\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read int $actor_id
 * @property-read NotificationType $type
 * @property-read array $data
 * @property-read Carbon|null $read_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class UserNotification extends Model
{
    /**
     * Eloquent derives "user_notifications" from the class name, but the
     * migration deliberately named the table "notifications".
     */
    protected $table = 'notifications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'data',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
