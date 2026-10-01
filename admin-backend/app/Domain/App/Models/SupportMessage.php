<?php

declare(strict_types=1);

namespace Admin\Domain\App\Models;

use Admin\Domain\Admin\Models\StaffUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a support thread. `from_support` distinguishes staff replies
 * from the user's own follow-ups; `emailed` records whether the reply was also
 * sent through the configured mail transport.
 *
 * Support replies reference the author by staff id (`staff_user_id`), which
 * belongs to the console's own database. That is a cross-database id, so it is
 * resolved with a query rather than an Eloquent relation: a relation would try
 * to join the staff table onto the app connection and find nothing.
 *
 * @property-read int $id
 * @property-read int $ticket_id
 * @property-read int|null $user_id
 * @property-read int|null $staff_user_id
 * @property-read bool $from_support
 * @property-read string $body
 * @property-read bool $emailed
 */
final class SupportMessage extends Model
{
    protected $connection = 'app';

    protected $table = 'support_messages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_user_id',
        'from_support',
        'body',
        'emailed',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The support agent who wrote a staff reply, or null when that account has
     * since been removed.
     */
    public function staffUser(): ?StaffUser
    {
        if ($this->staff_user_id === null) {
            return null;
        }

        return StaffUser::query()->find($this->staff_user_id);
    }
}
