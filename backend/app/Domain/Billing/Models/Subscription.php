<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'plan',
        'amount_paisa',
        'status',
        'verified_at',
        'approved_by',
        'starts_at',
        'expires_at',
        'auto_renew',
        'payment_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'status' => SubscriptionStatus::class,
            'amount_paisa' => 'integer',
            'verified_at' => 'datetime',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPending(): bool
    {
        return $this->status === SubscriptionStatus::Pending;
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    public function isPaid(): bool
    {
        return $this->payment_token !== null;
    }
}