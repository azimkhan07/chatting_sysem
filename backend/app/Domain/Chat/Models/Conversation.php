<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Enums\ConversationType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read ConversationType $type
 * @property-read string|null $name
 * @property-read int $created_by
 * @property-read Collection<int, ConversationMember> $members
 * @property-read ConversationMessage|null $lastMessage
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class Conversation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
