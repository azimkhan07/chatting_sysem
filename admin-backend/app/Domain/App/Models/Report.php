<?php

declare(strict_types=1);

namespace Admin\Domain\App\Models;

use Admin\Domain\App\Enums\ReportReason;
use Admin\Domain\App\Enums\ReportStatus;
use Admin\Domain\App\Enums\ReportTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's complaint about one thing, as the console sees it.
 *
 * A read/write model over the main app's `reports` table: staff genuinely close
 * reports, so this cannot be read-only, but the schema is owned by backend/ and
 * this app never migrates it.
 *
 * `targetUser()` resolves the reported author with two queries against tables
 * this app does not model (posts, comments, messages). It reads the author id
 * out of the target row directly rather than through an Eloquent relation,
 * because there is no model here to hang the relation on.
 *
 * @property-read int $id
 * @property-read int $reporter_id
 * @property-read string $target_type
 * @property-read int $target_id
 * @property-read string $reason
 * @property-read string|null $details
 * @property-read string $status
 * @property-read int|null $handled_by
 * @property-read string|null $resolution
 * @property-read Carbon|null $handled_at
 * @property-read Carbon $created_at
 */
final class Report extends Model
{
    protected $connection = 'app';

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

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * The console account that closed it. This crosses databases by id, which
     * is why the queue shows the handler's username only when the row still
     * exists: a deleted staff account must not take the queue down.
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

    /**
     * The account that wrote whatever was reported.
     *
     * The staff queue needs the person being reviewed for every target type. A
     * deleted account comes back as null instead of taking the list down.
     */
    public function targetUser(): ?User
    {
        $authorId = $this->authorIdForTarget();

        return $authorId === null ? null : User::query()->find($authorId);
    }

    private function authorIdForTarget(): ?int
    {
        // A user target *is* the account, so the id is already the answer. Every
        // other target is a row that carries a `user_id` author column, and
        // `users` has no such column — treating it like the rest would ask
        // `users.user_id` for a column that does not exist and take down the
        // whole queue on the first account report.
        if ($this->targetTypeEnum() === ReportTargetType::User) {
            return $this->target_id;
        }

        $row = match ($this->targetTypeEnum()) {
            ReportTargetType::User => ['users', $this->target_id],
            ReportTargetType::Post => ['posts', $this->target_id],
            ReportTargetType::Comment => ['comments', $this->target_id],
            ReportTargetType::Message => ['conversation_messages', $this->target_id],
        };

        $authorId = static::query()
            ->getConnection()
            ->table($row[0])
            ->where('id', $row[1])
            ->value('user_id');

        return $authorId === null ? null : (int) $authorId;
    }

    public function targetTypeEnum(): ReportTargetType
    {
        return ReportTargetType::from($this->target_type);
    }

    public function reasonEnum(): ReportReason
    {
        return ReportReason::from($this->reason);
    }

    public function statusEnum(): ReportStatus
    {
        return ReportStatus::from($this->status);
    }

    /**
     * The most severe reasons the queue has to surface first.
     */
    public function isUrgent(): bool
    {
        return $this->reasonEnum()->isUrgent();
    }
}
