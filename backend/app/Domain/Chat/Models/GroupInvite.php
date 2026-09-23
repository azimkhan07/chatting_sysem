<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $conversation_id
 * @property-read int $created_by
 * @property-read string $code
 * @property-read Carbon|null $expires_at
 * @property-read Carbon|null $revoked_at
 * @property-read Conversation $conversation
 * @property-read User $creator
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class GroupInvite extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'created_by',
        'code',
        'expires_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
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
}
