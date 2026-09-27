<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Enums\MessageType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $conversation_id
 * @property-read int $user_id
 * @property-read User|null $user
 * @property-read Conversation|null $conversation
 * @property-read Collection<int, MessageReaction> $reactions
 * @property-read MessageType $type
 * @property-read string $body
 * @property-read string|null $media_url
 * @property-read string|null $client_id
 * @property Carbon|null $pinned_at
 * @property int|null $pinned_by
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class ConversationMessage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'user_id',
        'type',
        'body',
        'media_url',
        'client_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'pinned_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }
}
