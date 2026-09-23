<?php

declare(strict_types=1);

namespace App\Domain\Threads\Models;

use App\Domain\Auth\Models\User;
use Database\Factories\ThreadEntryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $thread_id
 * @property-read int $user_id
 * @property-read string|null $body
 * @property-read string|null $media_path
 * @property-read string|null $media_mime
 * @property-read string|null $media_type
 * @property-read User $author
 * @property-read Thread $thread
 * @property-read Collection<int, ThreadReaction> $reactions
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class ThreadEntry extends Model
{
    /** @use HasFactory<ThreadEntryFactory> */
    use HasFactory;

    /**
     * Bound explicitly: model lives in the Domain namespace.
     */
    protected static function newFactory(): ThreadEntryFactory
    {
        return ThreadEntryFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'user_id',
        'body',
        'media_path',
        'media_mime',
        'media_type',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ThreadReaction::class);
    }
}
