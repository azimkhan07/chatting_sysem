<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Moderation\Enums\ReportReason;
use App\Domain\Moderation\Enums\ReportStatus;
use App\Domain\Moderation\Enums\ReportTargetType;
use App\Domain\Moderation\Exceptions\CannotReportException;
use App\Domain\Moderation\Models\Report;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use Illuminate\Pagination\CursorPaginator;

/**
 * Filing a complaint about content, and the staff queue that works it.
 *
 * The one rule that shapes the design: nobody reports their own content. Not
 * for tidiness - a user reporting themselves is either confused or is a way to
 * get their own post into the moderation queue, and a queue that can be
 * filled with self-reports is a queue nobody trusts.
 */
final class ReportService
{
    /**
     * The one entry point the API exposes, so the four target types cannot drift
     * apart in how they are validated.
     */
    public function report(
        User $reporter,
        ReportTargetType $targetType,
        int $targetId,
        ReportReason $reason,
        ?string $details,
    ): Report {
        $authorId = $this->resolveAuthor($reporter, $targetType, $targetId);

        if ((int) $reporter->id === $authorId) {
            throw new CannotReportException('You cannot report your own content.');
        }

        // `firstOrCreate` rather than `create`: the unique key is
        // (reporter, target, reason), so a second report of the same thing is
        // the same row. A user who reports a post twice has not made two
        // complaints, and duplicating it only buries other people's reports.
        return Report::query()->firstOrCreate(
            [
                'reporter_id' => $reporter->id,
                'target_type' => $targetType->value,
                'target_id' => $targetId,
                'reason' => $reason->value,
            ],
            [
                'details' => $details !== null && trim($details) !== '' ? trim($details) : null,
                'status' => ReportStatus::Pending->value,
            ],
        );
    }

    /**
     * Confirms the target exists, that the reporter is allowed to see it, and
     * returns the account that wrote it.
     *
     * The visibility half is not an optimisation. Without it, the id is a
     * capability: any authenticated user could report a private message in
     * someone else's conversation, or a draft they have never seen, purely by
     * incrementing a number.
     */
    private function resolveAuthor(User $reporter, ReportTargetType $targetType, int $targetId): int
    {
        return match ($targetType) {
            ReportTargetType::User => $this->authorOfUser($reporter, $targetId),
            ReportTargetType::Post => $this->authorOfPost($targetId),
            ReportTargetType::Comment => $this->authorOfComment($targetId),
            ReportTargetType::Message => $this->authorOfMessage($reporter, $targetId),
        };
    }

    private function authorOfUser(User $reporter, int $targetId): int
    {
        $target = User::query()->find($targetId);

        if ($target === null) {
            throw new CannotReportException('That account does not exist.');
        }

        return (int) $target->id;
    }

    private function authorOfPost(int $postId): int
    {
        $post = Post::query()->find($postId);

        if ($post === null) {
            throw new CannotReportException('That post no longer exists.');
        }

        return (int) $post->user_id;
    }

    private function authorOfComment(int $commentId): int
    {
        $comment = Comment::query()->find($commentId);

        if ($comment === null) {
            throw new CannotReportException('That comment no longer exists.');
        }

        return (int) $comment->user_id;
    }

    private function authorOfMessage(User $reporter, int $messageId): int
    {
        $message = ConversationMessage::query()->find($messageId);

        if ($message === null) {
            throw new CannotReportException('That message no longer exists.');
        }

        // A message can only be reported from inside its own conversation.
        $isMember = Conversation::query()
            ->whereKey((int) $message->conversation_id)
            ->whereHas('members', static fn ($query) => $query->where('user_id', $reporter->id))
            ->exists();

        if (! $isMember) {
            throw new CannotReportException('That message is not available to you.');
        }

        return (int) $message->user_id;
    }

    /**
     * The queue, urgent first.
     *
     * Sorted by urgency then age rather than age alone. Child-safety reports
     * are a small fraction of the volume and the whole point of the queue is
     * that the worst thing in it is what gets worked first.
     */
    public function queue(int $limit, ?string $cursor): CursorPaginator
    {
        return Report::query()
            ->with(['reporter', 'handler'])
            ->whereIn('status', [ReportStatus::Pending->value, ReportStatus::Reviewing->value])
            ->orderByRaw(
                'CASE WHEN reason IN (?, ?) THEN 0 ELSE 1 END',
                [
                    ReportReason::SelfHarm->value,
                    ReportReason::Abuse->value,
                ],
            )
            ->orderBy('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    public function resolve(User $handler, int $reportId, ReportStatus $status, ?string $resolution): Report
    {
        $report = Report::query()->find($reportId);

        if ($report === null) {
            throw new CannotReportException('That report no longer exists.');
        }

        $report->forceFill([
            'status' => $status->value,
            'handled_by' => (int) $handler->id,
            'resolution' => $resolution !== null && trim($resolution) !== '' ? trim($resolution) : null,
            'handled_at' => now(),
        ])->save();

        return $report;
    }
}
