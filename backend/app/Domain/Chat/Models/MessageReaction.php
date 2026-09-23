<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Enums\MessageReactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $message_id
 * @property-read int $user_id
 * @property-read MessageReactionType $reaction
 * @property-read ConversationMessage $message
 * @property-read User $user
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class MessageReaction extends Model
{
    protected $table = 'conversation_message_reactions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'user_id',
        'reaction',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reaction' => MessageReactionType::class,
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
