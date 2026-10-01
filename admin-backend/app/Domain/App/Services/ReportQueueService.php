<?php

declare(strict_types=1);

namespace Admin\Domain\App\Services;

use Admin\Domain\App\Enums\ReportReason;
use Admin\Domain\App\Enums\ReportStatus;
use Admin\Domain\App\Models\Report;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The staff report queue.
 *
 * Filing a report stays in backend/: deciding whether someone is even allowed
 * to report a thing needs the app's post, comment and conversation rules, and
 * that is app logic, not console logic. This service owns the two console jobs:
 * showing the queue and closing a report.
 */
final class ReportQueueService
{
    /**
     * The queue, urgent first.
     *
     * Sorted by urgency then age rather than age alone. Child-safety reports
     * are a small fraction of the volume and the whole point of the queue is
     * that the worst thing in it is what gets worked first.
     *
     * `reporter` is eager loaded because the queue names who reported what.
     * The handler is not: it crosses into the staff database, so it is
     * resolved by id per row through `Report::handlerName()`.
     */
    public function queue(int $limit, ?string $cursor): CursorPaginator
    {
        return Report::query()
            ->with(['reporter'])
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

    public function resolve(int $handlerId, int $reportId, ReportStatus $status, ?string $resolution): Report
    {
        // A missing report is a 404, not a validation failure. Reporting it as
        // a field error would tell the console "your input was wrong, fix the
        // form" when the truth is "somebody else closed this row, reload the
        // queue" - which is the one message that actually changes what the
        // staff member does next.
        $report = Report::query()->find($reportId);

        if ($report === null) {
            throw (new ModelNotFoundException)->setModel(Report::class, [$reportId]);
        }

        $report->forceFill([
            'status' => $status->value,
            'handled_by_staff' => $handlerId,
            'resolution' => $resolution !== null && trim($resolution) !== '' ? trim($resolution) : null,
            'handled_at' => now(),
        ])->save();

        return $report;
    }
}
