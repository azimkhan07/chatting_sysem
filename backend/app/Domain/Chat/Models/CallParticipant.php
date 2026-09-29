<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class CallParticipant extends Pivot
{
    public $incrementing = true;

    public $timestamps = true;

    protected $table = 'call_participants';

    protected $fillable = [
        'call_id',
        'user_id',
        'status',
        'joined_at',
        'left_at',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
