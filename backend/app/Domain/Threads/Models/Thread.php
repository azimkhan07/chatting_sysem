<?php

declare(strict_types=1);

namespace App\Domain\Threads\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Threads\Enums\ThreadStatus;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $conversation_id
 * @property-read int $created_by
 * @property-read ThreadStatus $status
 * @property-read Carbon|null $expires_at
 * @property-read array|null $recap
 * @property-read Conversation $conversation
 * @property-read User $creator
 * @property-read Collection<int, ThreadEntry> $entries
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class Thread extends Model
{
    /** @use HasFactory<ThreadFactory> */
    use HasFactory;

    /**
     * Bound explicitly: model lives in the Domain namespace.
     */
    protected static function newFactory(): ThreadFactory
    {
        return ThreadFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'created_by',
        'status',
        'expires_at',
        'recap',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ThreadStatus::class,
            'expires_at' => 'datetime',
            'recap' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ThreadEntry::class);
    }
}
