<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Models;

use App\Domain\Admin\Models\StaffUser;
use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Moderation\Enums\ReportReason;
use App\Domain\Moderation\Enums\ReportStatus;
use App\Domain\Moderation\Enums\ReportTargetType;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's complaint about one thing, for staff to look at.
 *
 * The target is polymorphic rather than a set of nullable foreign keys. The
 * alternative cannot express "exactly one target" in the database: four
 * nullable columns and an application check is four ways for a bug to produce
 * a report about nothing, or about two things at once.
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
 * @property-read Carbon $updated_at
 */
final class Report extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reporter_id',
        'target_type',
        'target_id',
        'reason',
        'details',
        'status',
        'handled_by',
        'handled_by_staff',
        'resolution',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<StaffUser, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'handled_by_staff');
    }

    /**
     * The account that wrote whatever was reported.
     *
     * The staff queue needs the person being reviewed, and it needs them for
     * every target type. Resolving it here rather than in the queue means the
     * queue has one shape to render, and a deleted account comes back as null
     * instead of taking the whole list down with an error.
     */
    public function targetUser(): ?User
    {
        $authorId = match ($this->targetTypeEnum()) {
            ReportTargetType::User => $this->target_id,
            ReportTargetType::Post => Post::query()->whereKey($this->target_id)->value('user_id'),
            ReportTargetType::Comment => Comment::query()->whereKey($this->target_id)->value('user_id'),
            ReportTargetType::Message => ConversationMessage::query()->whereKey($this->target_id)->value('user_id'),
        };

        return $authorId === null ? null : User::query()->find($authorId);
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
