<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a support thread. `from_support` distinguishes staff replies
 * from the user's own follow-ups; `emailed` records whether the reply was also
 * sent through the configured mail transport.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property bool $from_support
 * @property string $body
 * @property bool $emailed
 */
final class SupportMessage extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'from_support', 'body', 'emailed'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}