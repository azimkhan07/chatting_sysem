<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Enums\ConversationState;
use App\Domain\Chat\Enums\ConversationType;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read ConversationType $type
 * @property ConversationState $state
 * @property int|null $requested_by
 * @property-read string|null $name
 * @property-read int $created_by
 * @property-read Collection<int, ConversationMember> $members
 * @property-read ConversationMessage|null $lastMessage
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * Bound explicitly: model lives in the Domain namespace.
     */
    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }

    /**
     * A new conversation is an active chat unless a caller explicitly opens it
     * as a message request. Defaulting here means every creation path — service,
     * factory, seeder — has a state without repeating it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'state' => ConversationState::Active->value,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'state',
        'name',
        'created_by',
        'requested_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'state' => ConversationState::class,
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

    public function invites(): HasMany
    {
        return $this->hasMany(GroupInvite::class);
    }
}
