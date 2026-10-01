<?php

declare(strict_types=1);

namespace Admin\Domain\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user-filed query/complaint, as the support desk sees it.
 *
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string $subject
 * @property-read string $message
 * @property-read string $status
 * @property-read \Illuminate\Support\Carbon $created_at
 */
final class SupportTicket extends Model
{
    protected $connection = 'app';

    protected $table = 'support_tickets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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
