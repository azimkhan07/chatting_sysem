<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Auth\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'initiator_id',
        'kind',
        'status',
        'answered_at',
        'ended_at',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }

    public function isActive(): bool
    {
        $answeredAt = $this->answered_at !== null ? CarbonImmutable::parse($this->answered_at) : null;

        return $this->status === 'active'
            || ($this->status === 'ringing' && $answeredAt !== null);
    }
}
