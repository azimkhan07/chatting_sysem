<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user-filed query/complaint, engaged by the support role.
 *
 * @property int $id
 * @property int $user_id
 * @property string $subject
 * @property string $message
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 */
final class SupportTicket extends Model
{
    protected $fillable = ['user_id', 'subject', 'message', 'status', 'priority'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SupportMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }
}