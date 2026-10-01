<?php

declare(strict_types=1);

namespace Admin\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A staff account flagged as staffing the support desk.
 *
 * Support accounts are written to both `staff_users` and `support_staff`; the
 * second row is what the Support screen's "agents" list reads.
 *
 * @property-read int $id
 * @property-read int $staff_user_id
 * @property-read StaffUser $staff
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class SupportStaff extends Model
{
    protected $connection = 'admin';

    protected $table = 'support_staff';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_user_id',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'staff_user_id');
    }
}
