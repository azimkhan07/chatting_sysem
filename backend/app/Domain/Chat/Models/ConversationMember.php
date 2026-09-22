<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Enums\MemberRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $conversation_id
 * @property-read int $user_id
 * @property-read User|null $user
 * @property-read Conversation|null $conversation
 * @property-read MemberRole $role
 * @property-read int $last_read_message_id
 * @property-read bool $muted
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class ConversationMember extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'last_read_message_id',
        'muted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MemberRole::class,
            'muted' => 'boolean',
            'last_read_message_id' => 'integer',
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
}
