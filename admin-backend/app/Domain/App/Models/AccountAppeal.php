<?php

declare(strict_types=1);

namespace Admin\Domain\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A suspended user's request to be reviewed, as the support desk sees it.
 *
 * The user cannot sign in while suspended, so the appeal is filed with proof of
 * identity elsewhere. The desk either approves it (account flips back to
 * `active`) or rejects it (suspension stands).
 *
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string $message
 * @property-read string $status
 * @property-read string|null $resolution
 * @property-read Carbon|null $handled_at
 * @property-read Carbon $created_at
 * @property-read User|null $user
 */
final class AccountAppeal extends Model
{
    protected $connection = 'app';

    protected $table = 'account_appeals';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'handled_by_staff',
        'resolution',
        'handled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The console account that decided it, resolved by id across the staff
     * database so a deleted account shows as "removed" rather than erroring.
     */
    public function handlerName(): ?string
    {
        if ($this->handled_by_staff === null) {
            return null;
        }

        return \Admin\Domain\Admin\Models\StaffUser::query()
            ->whereKey($this->handled_by_staff)
            ->value('username');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
